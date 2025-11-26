<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\VoiceUpload;
use Illuminate\Support\Str;
use Mail;
use Auth;
use DB;
use Exception;

class VoiceUploadController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:voice-file-list');
        $this->middleware('permission:voice-upload-create', ['only' => ['store']]);
        $this->middleware('permission:voice-upload-view', ['only' => ['show']]);
        $this->middleware('permission:voice-upload-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $query = VoiceUpload::orderBy('id', 'DESC');

            if(!empty($request->title))
            {
                $query->where('title', 'LIKE', '%'.$request->title.'%');
            }

            if(!empty($request->voice_id))
            {
                $query->where('voice_id', $request->voice_id);
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', '%' . $search. '%')
                    ->orWhere('voiceId', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->user_id))
            {
                $query->where('user_id', $request->user_id);
            }

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('user_id', auth()->id());
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
                if(in_array(loggedInUserType(), [1,2]))
                {
                    $query = $query->get();
                }
                else
                {
                    $query = $query->with('voiceUploadSentGateways')->get();
                }
            }

            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function store(Request $request)
    {
        $validation = \Validator::make($request->all(),[ 
            'user_id'   => 'required|exists:users,id',
            'title'     => 'required|string',
            'file_location'      => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $file = $request->file_location;
            $fileArray = array();
            $formatCheck = ['wav','mp3'];

            $fileName   = strtolower(Str::slug($request->title)).'-'.time().'.' . $file->getClientOriginalExtension();
            $extension = strtolower($file->getClientOriginalExtension());
            $fileSize = $file->getSize();
            $fileMimeType = $file->getClientMimeType();
            
            if(!in_array($extension, $formatCheck))
            {
                return response()->json(prepareResult(true, [], trans('translate.file_not_allowed').'Only allowed : wav or mp3 file.', $this->intime), config('httpcodes.internal_server_error'));
            }

            //********************************
            //scan all files
            $finfo = finfo_open(FILEINFO_MIME_TYPE); // Return MIME type
            $fileActCheck = finfo_file($finfo, $file);
            finfo_close($finfo);
            if($fileActCheck=='application/x-dosexec')
            {
                return response()->json(prepareResult(true, trans('translate.malicious_file'), trans('translate.malicious_file'), $this->intime), config('httpcodes.internal_server_error'));
            }
            //********************************

            $destinationPath = 'voice/';

            $file->move($destinationPath, $fileName);
            $file_location  = $destinationPath.$fileName;

            $voiceUpload = new VoiceUpload;
            $voiceUpload->user_id = $request->user_id;
            $voiceUpload->voiceId = time().rand(1,9);
            $voiceUpload->fileStatus = 1;
            $voiceUpload->title = $request->title;
            $voiceUpload->file_location = $destinationPath.$fileName;
            $voiceUpload->file_time_duration = $request->file_time_duration;
            $voiceUpload->exact_file_duration = $request->exact_file_duration;
            $voiceUpload->file_mime_type = $fileMimeType;
            $voiceUpload->file_extension = $extension;
            $voiceUpload->save();

            return response()->json(prepareResult(false, $voiceUpload, trans('translate.created'), $this->intime), config('httpcodes.created'));   
        }
        catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function show(VoiceUpload $voiceUpload)
    {
        try {
            if(in_array(loggedInUserType(), [0,3]))
            {
                $voiceUpload = VoiceUpload::with('voiceUploadSentGateways')->find($voiceUpload->id);
            }
            else
            {
                $voiceUpload = $voiceUpload;
            }
            
            return response()->json(prepareResult(false, $voiceUpload, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy(VoiceUpload $voiceUpload)
    {
        try {
            if(in_array(loggedInUserType(), [1,2]) && $voiceUpload->user_id==auth()->id())
            {
                $voiceUpload->delete();
                return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            }
            elseif(in_array(loggedInUserType(), [0,3]))
            {
                $voiceUpload->delete();
                return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.unauthorized_delete'), $this->intime), config('httpcodes.internal_server_error'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
