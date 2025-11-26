<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PrimaryRoute;
use App\Models\VoiceUpload;
use App\Models\VoiceUploadSentGateway;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;
use Illuminate\Support\Facades\Http;

class VoiceFileProcessController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:voice-file-process');
        $this->middleware('permission:bulk-voice-file-action', ['only' => ['bulkVoiceFileAction']]);
        $this->middleware('permission:sync-voice-template-to-vendor', ['only' => ['syncVoiceTemplateToVendor']]);
    }

    public function getAllVoiceFileByStatus(Request $request)
    {
        try {
            $query = VoiceUploadSentGateway::select('voice_upload_sent_gateways.id','voice_upload_sent_gateways.voice_id','voice_upload_sent_gateways.file_status','voice_uploads.title','voice_uploads.file_location','primary_routes.route_name','users.name','users.email')
                ->join('voice_uploads', 'voice_upload_sent_gateways.voice_upload_id', 'voice_uploads.id')
                ->join('primary_routes', 'voice_upload_sent_gateways.primary_route_id', 'primary_routes.id')
                ->join('users', 'voice_uploads.user_id', 'users.id')
                ->orderBy('id', 'DESC');

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('voice_uploads.title', 'LIKE', '%'.$search.'%')
                    ->orWhere('voice_upload_sent_gateways.file_send_to_smsc_id', 'LIKE', '%'.$search.'%')
                    ->orWhere('primary_routes.route_name', 'LIKE', '%'.$search.'%');
                });
            }

            if(!empty($request->title))
            {
                $query->where('voice_uploads.title', $request->title);
            }

            if(!empty($request->file_status))
            {
                $query->where('voice_upload_sent_gateways.file_status', $request->file_status);
            }

            if(!empty($request->primary_routes_id))
            {
                $query->where('voice_upload_sent_gateways.primary_route_id', $request->primary_routes_id);
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

    public function voiceFileProcess(Request $request)
    {
        try {
            $primaryRoute = PrimaryRoute::select('id', 'api_url_for_voice', 'ip_address', 'smsc_username','smsc_password','smpp_credit', 'route_name', 'smsc_id')
                ->where('gateway_type', 4)
                ->find($request->primary_route_id);

            $voiceupload = VoiceUpload::find($request->voice_upload_id);
            if($primaryRoute && $voiceupload)
            {
                $response = voiceFileUploadToGateway($primaryRoute, $voiceupload);
            }
            return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function checkVoiceFileStatus($voice_id)
    {
        try {
            $voiceUploadSentGateway = VoiceUploadSentGateway::where('voice_id', $voice_id)
                ->first();
            if($voiceUploadSentGateway)
            {
                $primaryRoute = PrimaryRoute::select('id', 'api_url_for_voice', 'ip_address', 'smsc_username','smsc_password','smpp_credit', 'route_name', 'smsc_id')
                ->where('gateway_type', 4)
                ->find($voiceUploadSentGateway->primary_route_id);
                
                $response = null;

                if($primaryRoute && $primaryRoute->smsc_id=='videocon')
                {
                    $requestUrl = "http://103.132.146.183/VoxUpload/api/Values/CheckStatus";
                    $response = Http::asForm()->post($requestUrl, [
                        'UserName'  => $primaryRoute->smsc_username,
                        'Password'  => $primaryRoute->smsc_password,
                        'voiceid'   => $voice_id,
                    ]);
                    $response = $response->body();

                    $status = str_replace("Voice File Status is ", "",$response);
                    $file_status = str_replace(".", "",$status);
                    $voiceUploadSentGateway->file_status = convertFileStatus($file_status);
                    $voiceUploadSentGateway->save();
                }
            }
            

            return response()->json(prepareResult(false, $voiceUploadSentGateway, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function bulkVoiceFileAction(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'file_status'      => 'required|in:1,2,3,4',
            'voice_upload_sent_gateway_id'     => 'required|array|min:1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $users = VoiceUploadSentGateway::whereIn('id', $request->voice_upload_sent_gateway_id)
                ->update(['file_status' => $request->file_status]);

            return response()->json(prepareResult(false, [], trans('translate.updated'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function syncVoiceTemplateToVendor(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'primary_route_id' => 'required|exists:primary_routes,id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $voiceuploads = VoiceUpload::get();
            foreach ($voiceuploads as $key => $voiceupload) 
            {
                $voice_upload_sent_gateway = VoiceUploadSentGateway::where('voice_upload_id', $voiceupload->id)
                    ->where('primary_route_id', $request->primary_route_id)
                    ->whereNull('voice_id')
                    ->first();
                if(!$voice_upload_sent_gateway)
                {
                    $primaryRoute = PrimaryRoute::select('id', 'api_url_for_voice', 'ip_address', 'smsc_username','smsc_password','smpp_credit', 'route_name', 'smsc_id')
                        ->where('gateway_type', 4)
                        ->find($request->primary_route_id);

                    if(!$primaryRoute)
                    {
                        return response()->json(apiPrepareResult(true, [], trans('translate.route_not_support_voice_sms'), $intime), config('httpcodes.unprocessable_entity'));
                    }
                    
                    $response = voiceFileUploadToGateway($primaryRoute, $voiceupload);
                }
            }

            return response()->json(prepareResult(false, [], trans('translate.successfully_processed'), $this->intime), config('httpcodes.success'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
