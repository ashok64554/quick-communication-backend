<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use App\Models\User;
use App\Models\WhatsAppFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class WAFileUploadController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:whatsapp-file-upload');
    }

    public function index(Request $request)
    {
        try {
            $query = \DB::table('whats_app_files')
            ->select('whats_app_files.*','users.id as user_id', 'users.name')
            ->orderBy('whats_app_files.id', 'DESC')
            ->join('users', 'whats_app_files.user_id', 'users.id')
            ->whereNull('users.deleted_at');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('whats_app_files.user_id', auth()->id());
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('whats_app_files.file_caption', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->user_id))
            {
                $query->where('whats_app_files.user_id', $request->user_id);
            }

            if(!empty($request->file_type))
            {
                $query->where('whats_app_files.file_type', $request->file_type);
            }

            if(!empty($request->per_page_record))
            {
                $perPage = $request->per_page_record;
                $page = $request->input('page', 1);
                $total = $query->count();
                $result = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

                $pagination =  [
                    'data' => $result,
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => ceil($total / $perPage)
                ];
                $query = $pagination;
                if($request->other_function)
                {
                    return $pagination;
                }
            }
            else
            {
                $query = $query->get();
            }

            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function store(Request $request)
    {
        try {
            $validation = \Validator::make($request->all(), [
                'user_id'    => 'required|exists:users,id',
                'file_type'    => 'required|in:image,video,audio,document,sticker',
                'file' => 'required_without:file_url',
                'file_url' => 'required_without:file',
            ]);
           
            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }
            
            $destinationFolder = 'whatsapp-file/';

            if(!empty($request->file_url))
            {
                $file = $request->file_url;
                $fileDetails = $this->getFileDetailsFromUrl($file);
                if(!$fileDetails)
                {
                    return response()->json(prepareResult(true, [], trans('translate.url_not_allowed'), $this->intime), config('httpcodes.bad_request'));
                }

                $mediaFilePath = $request->file_url;
                $fileSize = $fileDetails['size'];
                $mimeType = $fileDetails['mime_type'];
                $extension = File::extension($mediaFilePath);
                $binaryImageData = file_get_contents($mediaFilePath);
                $act_name = (Str::length(basename($mediaFilePath)) >= 20 ? Str::substr(basename($mediaFilePath), 0,16).'.'.$extension : basename($mediaFilePath));
                $fileName   = 'wa'.'-'.time().'-'.$act_name;
                File::put("{$destinationFolder}{$fileName}",$binaryImageData);
            } 
            else
            {
                $file = $request->file('file');
                $mediaFilePath = $request->file('file');
                // The file size and mime type of your media
                $fileSize =  $file->getSize(); // Replace with the actual file size
                $mimeType =  $file->getMimeType(); // Replace with the actual mime type
                $extension = $file->getClientOriginalExtension();
                $binaryImageData = file_get_contents($file->path());
                $fileName   = 'wa'.'-'.time().'-'.rand(100, 999) . '.' . $file->getClientOriginalExtension();
                $file->move($destinationFolder, $fileName);
            }
            
            $filePath = $destinationFolder.$fileName;

            //$binaryFile =  file_get_contents($filePath);
            //$contentType = mime_content_type($filePath);

            $formatCheck = validateWAFile($file, $request->file_type, $mimeType, $fileSize);
            if(!$formatCheck)
            {
                if(file_exists($filePath))
                {
                    unlink($filePath);
                }
                return response()->json(prepareResult(true, [], trans('translate.file_not_allowed'), $this->intime), config('httpcodes.bad_request'));
            }

            if(!$formatCheck['is_allowed'])
            {
                if(file_exists($filePath))
                {
                    unlink($filePath);
                }
                $allowed_format = implode(', ', $formatCheck['allowed_format']);
                $allowed_size_mb = $formatCheck['allowed_size_mb'];
                $error = trans('translate.file_not_allowed').' '.trans('translate.allowed_file_formats').' '.$allowed_format.' '.trans('translate.and_file_size_must_be_less_than_or_equals_to').' '.$allowed_size_mb.'MB';
                return response()->json(prepareResult(true, $formatCheck, $error, $this->intime), config('httpcodes.internal_server_error'));
            }

            if(!$formatCheck['is_RGB_A'])
            {
                if(file_exists($filePath))
                {
                    unlink($filePath);
                }
                $error = trans('translate.images_must_be_8_bit_RGB_or_RGBA');
                return response()->json(prepareResult(true, $formatCheck, $error, $this->intime), config('httpcodes.internal_server_error'));
            }

            // Create record
            $waFile = new WhatsAppFile;
            $waFile->user_id = $request->user_id;
            $waFile->file_path = $filePath;
            $waFile->file_type = $request->file_type;
            $waFile->mime_type = $mimeType;
            $waFile->file_size = $fileSize;
            $waFile->file_caption = $request->file_caption;
            $waFile->save();

            // file upload wa server
            /*
            $user = User::find($request->user_id);
            $wa_config = whatsAppConfiguration($request->user_id);

            // Your API Version
            $apiVersion = @$wa_config->app_version; // Replace with the desired version
            // Your User Access Token
            $userAccessToken = @$wa_config->access_token;
            $appId = @$wa_config->app_id;
            
            $contentType = mime_content_type($filePath);

            // Step 1: Upload the media file
            $uploadUrl = "https://graph.facebook.com/{$apiVersion}/{$appId}/uploads";
            $uploadUrl .= "?file_length={$fileSize}&file_type={$mimeType}";;
            $uploadResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $userAccessToken,
            ])->post($uploadUrl);

            // Decode the JSON response
            $uploadData = $uploadResponse->json();
             
            // Extract the upload ID
            $sessionId = @$uploadData['id'];
           
            // Step 2: Get information about the uploaded file using the session ID
            $sessionEndpoint = "https://graph.facebook.com/{$apiVersion}/{$sessionId}";

            $headers = [
                'Authorization' => 'Bearer ' . $userAccessToken,
                'Content-Type' => $contentType, // Adjust based on your image format
            ];

            $sessionResponse = Http::withHeaders($headers)->attach(
                'attachment', $binaryImageData,$fileName
            )->post($sessionEndpoint);

            // Decode the JSON response
            $sessionData = $sessionResponse->json();
            return $sessionData;
            if($sessionResponse->ok()){
                return response()->json(prepareResult(false, $sessionData, trans('translate.created'), $this->intime), config('httpcodes.created'));

            } else{
                 return response()->json(prepareResult(true, $sessionData, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
            */

            return response()->json(prepareResult(false, $waFile, trans('translate.created'), $this->intime), config('httpcodes.created')); 
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show($id)
    {
        try {
            $whats_app_file = WhatsAppFile::query();
            if(in_array(loggedInUserType(), [1,2]))
            {
                $whats_app_file->where('user_id', auth()->id());
            }
            $whats_app_file = $whats_app_file->first();
            if($whats_app_file)
            {
                $userInfo = $whats_app_file->user()->select('id', 'name')->first();
                $whats_app_file['name'] = $userInfo->name;
                $whats_app_file['user_id'] = $userInfo->id;
                return response()->json(prepareResult(false, $whats_app_file, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy($id)
    {
        try {
            $whats_app_file = WhatsAppFile::query();
            if(in_array(loggedInUserType(), [1,2]))
            {
                $whats_app_file->where('user_id', auth()->id());
            }
            $whats_app_file = $whats_app_file->where('id', $id)->first();
            if($whats_app_file)
            {
                if(file_exists($whats_app_file->file_path))
                {
                    unlink($whats_app_file->file_path);
                }
                $whats_app_file->delete();
                return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getFileDetailsFromUrl($fileUrl)
    {
        try {
            // Make a HEAD request to fetch only the headers
            $response = Http::head($fileUrl);

            // Check if the request was successful
            if (!$response->successful()) {
                return false;
            }

            // Get the 'Content-Length' header for file size
            $fileSize = $response->header('Content-Length');

            // Get the 'Content-Type' header for MIME type
            $mimeType = $response->header('Content-Type');

            return ['size' => $fileSize, 'mime_type' => $mimeType];
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function uploadWaFile(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'configuration_id'    => 'required|exists:whats_app_configurations,id',
            'file_type'    => 'required|in:image,video,audio,document,sticker',
            'file' => 'required',
        ]);
       
        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $destinationFolder = 'whatsapp-file/';
            $file = $request->file('file');
            $mediaFilePath = $request->file('file');
            $fileSize =  $file->getSize(); 
            $mimeType =  $file->getMimeType();
            $extension = $file->getClientOriginalExtension();
            $binaryImageData = file_get_contents($file->path());
            $fileName   = 'wa'.'-'.time().'-'.rand(100, 999) . '.' . $file->getClientOriginalExtension();
            $file->move($destinationFolder, $fileName);
            $filePath = $destinationFolder.$fileName;

            $formatCheck = validateWAFile($file, $request->file_type, $mimeType, $fileSize);
            if(!$formatCheck)
            {
                if(file_exists($filePath))
                {
                    unlink($filePath);
                }
                return response()->json(prepareResult(true, [], trans('translate.file_not_allowed'), $this->intime), config('httpcodes.bad_request'));
            }

            if(!$formatCheck['is_allowed'])
            {
                if(file_exists($filePath))
                {
                    unlink($filePath);
                }
                $allowed_format = implode(', ', $formatCheck['allowed_format']);
                $allowed_size_mb = $formatCheck['allowed_size_mb'];
                $error = trans('translate.file_not_allowed').' '.trans('translate.allowed_file_formats').' '.$allowed_format.' '.trans('translate.and_file_size_must_be_less_than_or_equals_to').' '.$allowed_size_mb.'MB';
                return response()->json(prepareResult(true, $formatCheck, $error, $this->intime), config('httpcodes.internal_server_error'));
            }

            if(!$formatCheck['is_RGB_A'])
            {
                if(file_exists($filePath))
                {
                    unlink($filePath);
                }
                $error = trans('translate.images_must_be_8_bit_RGB_or_RGBA');
                return response()->json(prepareResult(true, $formatCheck, $error, $this->intime), config('httpcodes.internal_server_error'));
            }

            $user = User::find($request->user_id);
            $wa_config = whatsAppConfiguration($request->configuration_id, $request->user_id);

            $apiVersion = @$wa_config->app_version;
            $userAccessToken = base64_decode($wa_config->access_token);
            $appId = @$wa_config->app_id;
            
            $contentType = mime_content_type($filePath);
            
            // Step 1: Upload the media file
            $uploadUrl = "https://graph.facebook.com/{$apiVersion}/{$appId}/uploads";
            $uploadUrl .= "?file_length={$fileSize}&file_type={$mimeType}";
            $uploadResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $userAccessToken,
            ])->post($uploadUrl);

            if(!$uploadResponse->ok())
            {
                \Log::error($uploadResponse);
                 return response()->json(prepareResult(true, $uploadResponse, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }

            $uploadData = $uploadResponse->json();
            $sessionId = @$uploadData['id'];
           
            // Step 2: Get information about the uploaded file using the session ID
            $sessionEndpoint = "https://graph.facebook.com/{$apiVersion}/{$sessionId}";

            $headers = [
                'Authorization' => 'Bearer ' . $userAccessToken,
                'Content-Type' => $contentType,
            ];

            $sessionResponse = Http::withHeaders($headers)->attach(
                'attachment', $binaryImageData,$fileName
            )->post($sessionEndpoint);

            $sessionData = $sessionResponse->json();
            if($sessionResponse->ok()){
                return response()->json(prepareResult(false, $sessionData, trans('translate.created'), $this->intime), config('httpcodes.created'));

            } else{
                \Log::error($sessionData);
                 return response()->json(prepareResult(true, $sessionData, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

}
