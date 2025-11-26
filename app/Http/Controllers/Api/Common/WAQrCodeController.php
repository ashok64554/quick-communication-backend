<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WhatsAppQrCode;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Auth;
use DB;
use Exception;

class WAQrCodeController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
       // $this->middleware('permission:whatsapp-qrcode-list');
       // $this->middleware('permission:whatsapp-qrcode-create', ['only' => ['store']]);
       // $this->middleware('permission:whatsapp-qrcode-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $query = WhatsAppQrCode::select('whats_app_qr_codes.*','users.id as user_id', 'users.name',)
            ->orderBy('whats_app_qr_codes.id', 'DESC')
            ->join('users', 'whats_app_qr_codes.user_id', 'users.id')
            ->whereNull('users.deleted_at');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('whats_app_qr_codes.user_id', auth()->id());
            }

            if(!empty($request->user_id))
            {
                $query->where('whats_app_qr_codes.user_id', $request->user_id);
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('whats_app_qr_codes.code', 'LIKE', '%' . $search. '%')
                    ->orWhere('whats_app_qr_codes.prefilled_message', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->code))
            {
                $query->where('whats_app_templates.code', $request->code);
            }

            if(!empty($request->prefilled_message))
            {
                $query->where('whats_app_templates.prefilled_message', $request->prefilled_message);
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
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'configuration_id'    => 'required|exists:whats_app_configurations,id',
            'prefilled_message'    => 'required|string',
            'qr_image_format'    => 'required|in:SVG,PNG',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $user_id = (!empty($request->user_id) ? $request->user_id : auth()->id());

        $wa_config = whatsAppConfiguration($request->configuration_id, $user_id);
        if(!$wa_config)
        {
            return response()->json(prepareResult(true, [], trans('translate.whats_app_configurations_not_found'), $this->intime), config('httpcodes.bad_request'));
        }

        $accessToken = base64_decode($wa_config->access_token);
        $sender_number = $wa_config->sender_number;
        $apiVersion = (!empty($wa_config->app_version) ? $wa_config->app_version : 'v17.0');

        DB::beginTransaction();
        try {
            $createQR = waGenerateQRCode($accessToken, $sender_number, $apiVersion, $request->prefilled_message, $request->qr_image_format);
            if($createQR['error']==false)
            {
                $getRes = $createQR['response'];
                $whatsAppQrCode = new WhatsAppQrCode;
                $whatsAppQrCode->user_id = $request->user_id;
                $whatsAppQrCode->whats_app_configuration_id = $request->configuration_id;
                $whatsAppQrCode->qr_image_format = $request->qr_image_format;
                $whatsAppQrCode->prefilled_message = $request->prefilled_message;
                $whatsAppQrCode->code = $getRes['code'];
                $whatsAppQrCode->deep_link_url = $getRes['deep_link_url'];
                $whatsAppQrCode->qr_image_url = $getRes['qr_image_url'];
                $whatsAppQrCode->save();
                DB::commit();
                return response()->json(prepareResult(false, $whatsAppQrCode, trans('translate.created'), $this->intime), config('httpcodes.created'));
            }
            \Log::error($createQR['response']);
            return response()->json(prepareResult(true, $createQR['response'], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy($id)
    {
        try {
            $whatsAppQrCode = WhatsAppQrCode::query();
            if(in_array(loggedInUserType(), [1,2]))
            {
                $whatsAppQrCode->where('user_id', auth()->id());
            }
            $whatsAppQrCode = $whatsAppQrCode->where('id', $id)->first();
            if($whatsAppQrCode)
            {
                $wa_config = whatsAppConfiguration($whatsAppQrCode->whats_app_configuration_id, $whatsAppQrCode->user_id);
                if(!$wa_config)
                {
                    return response()->json(prepareResult(true, [], trans('translate.whats_app_configurations_not_found'), $this->intime), config('httpcodes.bad_request'));
                }

                $accessToken = base64_decode($wa_config->access_token);
                $sender_number = $wa_config->sender_number;
                $apiVersion = (!empty($wa_config->app_version) ? $wa_config->app_version : 'v17.0');

                $deleteQR = waGenerateQRCode($accessToken, $sender_number, $apiVersion, $whatsAppQrCode->code);
                if($deleteQR['error']==false)
                {
                    $whatsAppQrCode->delete();
                    return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
                }
                \Log::error($deleteQR['response']);
                return response()->json(prepareResult(true, $deleteQR['response'], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waQrcodesSync(Request $request)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'configuration_id'    => 'required|exists:whats_app_configurations,id',
            'qr_image_format'    => 'required|in:SVG,PNG',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $user_id = (!empty($request->user_id) ? $request->user_id : auth()->id());

        $wa_config = whatsAppConfiguration($request->configuration_id, $user_id);
        if(!$wa_config)
        {
            return response()->json(prepareResult(true, [], trans('translate.whats_app_configurations_not_found'), $this->intime), config('httpcodes.bad_request'));
        }

        $accessToken = base64_decode($wa_config->access_token);
        $sender_number = $wa_config->sender_number;
        $apiVersion = (!empty($wa_config->app_version) ? $wa_config->app_version : 'v17.0');

        DB::beginTransaction();
        try {
            $syncQR = waQrcodesSync($accessToken, $sender_number, $apiVersion, $request->qr_image_format);
            if($syncQR['error']==false)
            {
                $removeOldQrs = WhatsAppQrCode::where('user_id', $user_id)
                    ->where('qr_image_format', $request->qr_image_format)
                    ->delete();

                $getRes = $syncQR['response'];
                foreach ($getRes as $key => $qrs) 
                {
                    foreach ($qrs as $nkey => $qr) 
                    {
                        $whatsAppQrCode = new WhatsAppQrCode;
                        $whatsAppQrCode->user_id = $user_id;
                        $whatsAppQrCode->whats_app_configuration_id = $request->configuration_id;
                        $whatsAppQrCode->qr_image_format = $request->qr_image_format;
                        $whatsAppQrCode->prefilled_message = $qr['prefilled_message'];
                        $whatsAppQrCode->code = $qr['code'];
                        $whatsAppQrCode->deep_link_url = $qr['deep_link_url'];
                        $whatsAppQrCode->qr_image_url = $qr['qr_image_url'];
                        $whatsAppQrCode->save();
                        DB::commit();
                    }
                }
                return response()->json(prepareResult(false, [], trans('translate.synced'), $this->intime), config('httpcodes.created'));
            }
            \Log::error($syncQR['response']);
            return response()->json(prepareResult(true, $syncQR['response'], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
}
