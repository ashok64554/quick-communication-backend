<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WhatsAppConfiguration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Auth;
use DB;
use Exception;

class WAConfigurationController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:whatsapp-configurations');
        $this->middleware('permission:whatsapp-configuration-create', ['only' => ['store']]);
        $this->middleware('permission:whatsapp-configuration-edit', ['only' => ['update']]);
        $this->middleware('permission:whatsapp-configuration-view', ['only' => ['show']]);
        $this->middleware('permission:whatsapp-configuration-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $query = \DB::table('whats_app_configurations')
            ->select('whats_app_configurations.*','users.id as user_id', 'users.name')
            ->orderBy('whats_app_configurations.id', 'DESC')
            ->join('users', 'whats_app_configurations.user_id', 'users.id')
            ->whereNull('users.deleted_at');

            if(in_array(loggedInUserType(), [1,2]))
            {
                $query->where('whats_app_configurations.user_id', auth()->id());
            }

            if(!empty($request->search))
            {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('whats_app_configurations.sender_number', 'LIKE', '%' . $search. '%')
                    ->orWhere('whats_app_configurations.business_account_id', 'LIKE', '%' . $search. '%')
                    ->orWhere('whats_app_configurations.verified_name', 'LIKE', '%' . $search. '%');
                });
            }

            if(!empty($request->user_id))
            {
                $query->where('whats_app_configurations.user_id', $request->user_id);
            }

            if(!empty($request->sender_number))
            {
                $query->where('whats_app_configurations.sender_number', $request->sender_number);
            }

            if(!empty($request->business_account_id))
            {
                $query->where('whats_app_configurations.business_account_id', 'LIKE', '%'.$request->business_account_id.'%');
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
            'sender_number'    => 'required|numeric|min:10',
            'business_account_id'    => 'required',
            'waba_id'    => 'required',
            'fb_code'    => 'required',
            'business_category' => 'required',
            'privacy_read_receipt' => 'required|in:0,1',
            'privacy_deregister_mobile' => 'required|in:0,1',
            'enable_auto_response' => 'required|in:0,1',
            'wa_status' => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {

            $user_id = $request->user_id;
            if(in_array(loggedInUserType(), [1,2]))
            {
                $user_id =  auth()->id();
            }

            $checkDuplicate = WhatsAppConfiguration::where('user_id', $user_id)
            ->where('sender_number', $request->sender_number)
            ->first();
            if($checkDuplicate) 
            {
                $wa_account_configuration = $checkDuplicate;
            }
            else
            {
                $wa_account_configuration = new WhatsAppConfiguration;
                $wa_account_configuration->user_id  = $user_id;
                $wa_account_configuration->app_id  = env('FB_APP_ID', '598395012455883');
                $wa_account_configuration->app_version  = env('FB_APP_VERSION', 'v17.0');
            }
            
            $wa_account_configuration->display_phone_number  = $request->display_phone_number;
            $wa_account_configuration->display_phone_number_req  = justNumber($request->display_phone_number);

            $wa_account_configuration->sender_number  = $request->sender_number;
            $wa_account_configuration->business_account_id  = $request->business_account_id;
            $wa_account_configuration->waba_id  = $request->waba_id;
            $wa_account_configuration->business_category  = $request->business_category;
            $wa_account_configuration->wa_business_page  = $request->wa_business_page;
            $wa_account_configuration->privacy_read_receipt  = $request->privacy_read_receipt;
            $wa_account_configuration->privacy_deregister_mobile  = $request->privacy_deregister_mobile;
            $wa_account_configuration->enable_auto_response  = $request->enable_auto_response;
            $wa_account_configuration->auto_response_message  = $request->auto_response_message;
            $wa_account_configuration->wa_status  = ($request->wa_status=='connected') ? strtoupper($request->wa_status) : 'DISCONNECTED';
            $wa_account_configuration->save();
            DB::commit();


            $wa_flow_signup_access_token = $this->waFlowSignup($request->fb_code, $request->sender_number);  
            if($wa_flow_signup_access_token['error']==false)
            {
                $userInfo = $wa_account_configuration->user()->select('id', 'name')->first();

                $wa_account_configuration->access_token  = base64_encode($wa_flow_signup_access_token['response']['access_token']);
                $wa_account_configuration->name  = $userInfo->name;
                $wa_account_configuration->save();

                
                $wa_account_configuration['name'] = $userInfo->name;
                $wa_account_configuration['user_id'] = $userInfo->id;

                // Subscribe app to the WhatsApp Business Account
                $subscribed = SubscribeWaApp(base64_decode($wa_account_configuration->access_token), $request->waba_id,$wa_account_configuration->app_version);

                // Register app to the WhatsApp Business Account
                $otp_for_register_buss_no = (!empty($request->waPhoneNumberRequestToRegister) ? $request->waPhoneNumberRequestToRegister : 123456);
                $registerBussNo = waPhoneNumberRequestToRegister(base64_decode($wa_account_configuration->access_token), $request->waba_id,$wa_account_configuration->app_version, $otp_for_register_buss_no);

                $return = checkQualitySignalReport(base64_decode($wa_account_configuration->access_token), $wa_account_configuration->sender_number, $wa_account_configuration->app_version);
                if($return)
                {
                    $json_decode = json_decode($return, true);
                    if(array_key_exists('error', $json_decode))
                    {
                        \Log::error('Display phone number not added due to error.');
                        \Log::error($json_decode['error']);
                        return response()->json(prepareResult(true, $json_decode['error'], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
                    }

                    $wa_account_configuration->display_phone_number = $json_decode['display_phone_number'];
                    $wa_account_configuration->display_phone_number_req = justNumber($json_decode['display_phone_number']);
                    $wa_account_configuration->verified_name = $json_decode['verified_name'];
                    $wa_account_configuration->code_verification_status = $json_decode['code_verification_status'];
                    $wa_account_configuration->quality_rating = $json_decode['quality_rating'];
                    $wa_account_configuration->platform_type = $json_decode['platform_type'];
                    $wa_account_configuration->last_quality_checked = date('Y-m-d H:i:s');
                    $wa_account_configuration->save();
                }

                return response()->json(prepareResult(false, $wa_account_configuration, trans('translate.created'), $this->intime), config('httpcodes.created'));
            }
            else
            {
                \Log::error($wa_flow_signup_access_token);
                return response()->json(prepareResult(true, 'Access token not generated, Please try again.', trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }

        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }

    }

    /*
    public function store(Request $request)
    {
        // Start Payload
        {
            "user_id": "2",
            "sender_number": "105736689271434",
            "business_account_id": "114715205025905",
            "access_token": "EAAIgPLInHcsBO9ci6q43CGa0sAOnN8ieFOPd59DIUGZBS3LsXenfKmUjcenMT960TLGpQTrChSNjHsyPepFDYoM4EsF4jtLUPXbZBM2XwdkZAdsZBzFTfj0EZAirttF4yMlFqoCoWJs96jMqG39GbHbDGpJDp8ELmnz4GvDaJYb0WsZC5mvI4WfhfEJXhFD6yDTpoUYS8dCMrb7uYcQiwZD",
            "facebook_app_id": "598395012455883",
            "facebook_app_version": "v17.0",
            "business_category": "PROF_SERVICES",
            "wa_business_page": null,
            "privacy_read_receipt": 1,
            "privacy_deregister_mobile": 0,
            "enable_auto_response": 1,
            "auto_response_message": "Thanks for message, we ll connect you ASAP."
        }
        // End Payload

        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'sender_number'    => 'required|numeric|min:10',
            'business_account_id'    => 'required',
            'access_token'    => 'required',
            'facebook_app_id' => 'required|numeric|min:10',
            'facebook_app_version' => 'required',
            'business_category' => 'required',
            'privacy_read_receipt' => 'required|in:0,1',
            'privacy_deregister_mobile' => 'required|in:0,1',
            'enable_auto_response' => 'required|in:0,1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $checkDuplicate = WhatsAppConfiguration::where('user_id', $request->user_id)
            ->where('sender_number', $request->sender_number)
            ->first();
        if($checkDuplicate) 
        {
            return response()->json(prepareResult(true, [], trans('translate.record_already_exist'), $this->intime), config('httpcodes.bad_request'));
        }

        DB::beginTransaction();
        try {
            $wa_account_configuration = new WhatsAppConfiguration;
            $wa_account_configuration->user_id  = $request->user_id;
            $wa_account_configuration->display_phone_number  = $request->display_phone_number;
            $wa_account_configuration->sender_number  = $request->sender_number;
            $wa_account_configuration->business_account_id  = $request->business_account_id;
            $wa_account_configuration->access_token  = base64_encode($request->access_token);
            $wa_account_configuration->app_id  = $request->facebook_app_id;
            $wa_account_configuration->app_version  = $request->facebook_app_version;

            $wa_account_configuration->business_category  = $request->business_category;
            $wa_account_configuration->wa_business_page  = $request->wa_business_page;
            //$wa_account_configuration->messsage_limit  = $request->messsage_limit;
            //$wa_account_configuration->wa_status  = $request->wa_status;
            $wa_account_configuration->privacy_read_receipt  = $request->privacy_read_receipt;
            $wa_account_configuration->privacy_deregister_mobile  = $request->privacy_deregister_mobile;
            $wa_account_configuration->enable_auto_response  = $request->enable_auto_response;
            $wa_account_configuration->auto_response_message  = $request->auto_response_message;
            $wa_account_configuration->save();
            DB::commit();

            $userInfo = $wa_account_configuration->user()->select('id', 'name')->first();
            $wa_account_configuration['name'] = $userInfo->name;
            $wa_account_configuration['user_id'] = $userInfo->id;

            $return = checkQualitySignalReport($request->access_token, $request->sender_number, $request->facebook_app_version);
            if($return)
            {
                $json_decode = json_decode($return, true);
                if(array_key_exists('error', $json_decode))
                {
                    \Log::error('Display phone number not added due to error.');
                    \Log::error($json_decode['error']);
                }
                $wa_account_configuration->display_phone_number = $json_decode['display_phone_number'];
                $wa_account_configuration->verified_name = $json_decode['verified_name'];
                $wa_account_configuration->code_verification_status = $json_decode['code_verification_status'];
                $wa_account_configuration->quality_rating = $json_decode['quality_rating'];
                $wa_account_configuration->platform_type = $json_decode['platform_type'];
                $wa_account_configuration->last_quality_checked = date('Y-m-d H:i:s');
                $wa_account_configuration->save();
            }

            return response()->json(prepareResult(false, $wa_account_configuration, trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }
    */

    public function show($id)
    {
        try {
            $wa_account_configuration = WhatsAppConfiguration::query();
            if(in_array(loggedInUserType(), [1,2]))
            {
                $wa_account_configuration->where('user_id', auth()->id());
            }
            $wa_account_configuration = $wa_account_configuration->where('id', $id)->first();
            if($wa_account_configuration)
            {
                $userInfo = $wa_account_configuration->user()->select('id', 'name')->first();
                $wa_account_configuration['name'] = $userInfo->name;
                $wa_account_configuration['user_id'] = $userInfo->id;

                return response()->json(prepareResult(false, $wa_account_configuration, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function update(Request $request, $id)
    {
        $validation = \Validator::make($request->all(), [
            'user_id'    => 'required|exists:users,id',
            'business_category' => 'required',
            'privacy_read_receipt' => 'required|in:0,1',
            'privacy_deregister_mobile' => 'required|in:0,1',
            'enable_auto_response' => 'required|in:0,1',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }


        $wa_account_configuration = WhatsAppConfiguration::where('id', $id);
        if(in_array(loggedInUserType(), [1,2]))
        {
            $wa_account_configuration->where('user_id', auth()->id());
        }
        $wa_account_configuration = $wa_account_configuration->first();
        if(!$wa_account_configuration)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        DB::beginTransaction();
        try {
            $wa_account_configuration->user_id  = $request->user_id;
            $wa_account_configuration->business_category  = $request->business_category;
            $wa_account_configuration->wa_business_page  = $request->wa_business_page;
            $wa_account_configuration->privacy_read_receipt  = $request->privacy_read_receipt;
            $wa_account_configuration->privacy_deregister_mobile  = $request->privacy_deregister_mobile;
            $wa_account_configuration->enable_auto_response  = $request->enable_auto_response;
            $wa_account_configuration->auto_response_message  = $request->auto_response_message;
            $wa_account_configuration->save();
            DB::commit();

            $userInfo = $wa_account_configuration->user()->select('id', 'name')->first();
            $wa_account_configuration['name'] = $userInfo->name;
            $wa_account_configuration['user_id'] = $userInfo->id;
            return response()->json(prepareResult(false, $wa_account_configuration, trans('translate.updated'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function destroy($id)
    {
        try {
            $wa_account_configuration = WhatsAppConfiguration::query();
            if(in_array(loggedInUserType(), [1,2]))
            {
                $wa_account_configuration->where('user_id', auth()->id());
            }
            $wa_account_configuration = $wa_account_configuration->first();
            if($wa_account_configuration)
            { 
                WhatsAppConfiguration::where('id', $id)->delete();
                return response()->json(prepareResult(false, [], trans('translate.deleted'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waAccountQualityCheck($whats_app_configuration_id)
    {
        $getConfInfo = WhatsAppConfiguration::find($whats_app_configuration_id);
        if(!$getConfInfo)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        $appVersion = $getConfInfo->app_version;
        $sender_number = $getConfInfo->sender_number;
        $access_token = base64_decode($getConfInfo->access_token);
        $return = checkQualitySignalReport($access_token, $sender_number, $appVersion);
        if($return)
        {
            $json_decode = json_decode($return, true);
            if(array_key_exists('error', $json_decode))
            {
                return response()->json(prepareResult(true, $json_decode['error'], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.bad_request'));
            }
            $getConfInfo->display_phone_number = $json_decode['display_phone_number'];
            $getConfInfo->display_phone_number_req = justNumber($json_decode['display_phone_number']);
            $getConfInfo->verified_name = $json_decode['verified_name'];
            $getConfInfo->code_verification_status = $json_decode['code_verification_status'];
            $getConfInfo->quality_rating = $json_decode['quality_rating'];
            $getConfInfo->platform_type = $json_decode['platform_type'];
            $getConfInfo->last_quality_checked = date('Y-m-d H:i:s');
            $getConfInfo->save();

        }
        return response()->json(prepareResult(false, $getConfInfo, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
    }

    public function waPhoneNumberRegister($whats_app_configuration_id)
    {
        $getConfInfo = WhatsAppConfiguration::find($whats_app_configuration_id);
        if(!$getConfInfo)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        if($getConfInfo->platform_type != 'NOT_APPLICABLE')
        {
            return response()->json(prepareResult(true, [], trans('translate.plateform_status_already_changed'). $getConfInfo->platform_type, $this->intime), config('httpcodes.bad_request'));
        }
        $set_otp_for_register_business_ph_number = (empty($request->set_otp_for_register_business_ph_number) ? 123456 : $request->set_otp_for_register_business_ph_number);
        $appVersion = $getConfInfo->app_version;
        $sender_number = $getConfInfo->sender_number;
        $access_token = base64_decode($getConfInfo->access_token);
        $return = waPhoneNumberRequestToRegister($access_token, $sender_number, $appVersion, $set_otp_for_register_business_ph_number);
        if($return)
        {
            $json_decode = json_decode($return, true);
            if(array_key_exists('error', $json_decode))
            {
                \Log::error($json_decode);
                return response()->json(prepareResult(true, $json_decode['error'], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        return $this->waAccountQualityCheck($whats_app_configuration_id);

        /*return response()->json(prepareResult(false, trans('translate.whats_app_verification_code_sent_to_your_number'), trans('translate.whats_app_verification_code_sent_to_your_number'), $this->intime), config('httpcodes.success'));*/
    }

    public function waPhoneNumberRequestToVerify(Request $request, $whats_app_configuration_id)
    {
        $getConfInfo = WhatsAppConfiguration::find($whats_app_configuration_id);
        if(!$getConfInfo)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        $appVersion = $getConfInfo->app_version;
        $sender_number = $getConfInfo->sender_number;
        $access_token = base64_decode($getConfInfo->access_token);
        $otp_method = (empty($request->otp_method) ? 'SMS' : $request->otp_method);
        $language = (empty($request->language) ? 'en_US' : $request->language);
        $return = waPhoneNumberRequestToVerify($access_token, $sender_number, $appVersion, $otp_method, $language);
        if($return)
        {
            $json_decode = json_decode($return, true);
            if(array_key_exists('error', $json_decode))
            {
                return response()->json(prepareResult(true, $json_decode['error'], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.bad_request'));
            }
        }
        return response()->json(prepareResult(false, trans('translate.whats_app_verification_code_sent_to_your_number'), trans('translate.whats_app_verification_code_sent_to_your_number'), $this->intime), config('httpcodes.success'));
    }

    public function waPhoneNumberVerify(Request $request, $whats_app_configuration_id)
    {
        $validation = \Validator::make($request->all(), [
            'code'    => 'required|numeric|min:4',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        $getConfInfo = WhatsAppConfiguration::find($whats_app_configuration_id);
        if(!$getConfInfo)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }

        $access_token = base64_decode($getConfInfo->access_token);
        $sender_number = $getConfInfo->sender_number;
        $appVersion = $getConfInfo->app_version;
        $return = waPhoneNumberVerify($access_token, $sender_number, $appVersion, $request->code);
        if($return)
        {
            $json_decode = json_decode($return, true);
            if(array_key_exists('error', $json_decode))
            {
                return response()->json(prepareResult(true, $json_decode['error'], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.bad_request'));
            }
            elseif(array_key_exists('success', $json_decode) && $json_decode['success']==true)
            {
                $getConfInfo->code_verification_status = 'VERIFIED';
                $getConfInfo->save();
            }
            else
            {
                return response()->json(prepareResult(true, trans('translate.something_went_wrong'), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.bad_request'));
            }
        }
        return response()->json(prepareResult(false, $getConfInfo, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
    }

    public function waBusinessCategory()
    {
        try {
            $query = [
                'OTHER' => 'OTHER',
                'AUTO' => 'AUTO',
                'BEAUTY' => 'BEAUTY',
                'APPAREL' => 'APPAREL',
                'EDU' => 'EDU',
                'ENTERTAIN' => 'ENTERTAIN',
                'EVENT_PLAN' => 'EVENT_PLAN',
                'FINANCE' => 'FINANCE',
                'GROCERY' => 'GROCERY',
                'GOVT' => 'GOVT',
                'HOTEL' => 'HOTEL',
                'HEALTH' => 'HEALTH',
                'NONPROFIT' => 'NONPROFIT',
                'PROF_SERVICES' => 'PROF_SERVICES',
                'RETAIL' => 'RETAIL',
                'TRAVEL' => 'TRAVEL',
                'RESTAURANT' => 'RESTAURANT'
            ];

            return response()->json(prepareResult(false, $query, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waFlowSignup($fb_code, $sender_number)
    {
        try {
            $url = "https://graph.facebook.com/v22.0/oauth/access_token";
            $payload = [
              "client_id" => env('FB_CLIENT_ID', '598395012455883'),
              "client_secret" => env('FB_CLIENT_SECRET', 'd7ed017c3433ddac3c73afe572bbd78e'),
              "code" => $fb_code,
              "grant_type" => "authorization_code",
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])
            /*->withOptions([
                "verify" => base_path("public/whatsapp-file/certificates/$sender_number.pem")
            ])*/
            ->post($url, $payload)->throw();
            if($response->successful())
            {
                return [
                    'error' => false,
                    'response' => json_decode($response->body(), true)
                ];
            }
            else
            {
                \Log::error('FB Flow Signup error');
                return [
                    'error' => true,
                    'response' => $response['error']['message']
                ];
            }
        } catch (\Throwable $e) {
            \Log::error($e);
            return [
                'error' => true,
                'response' => $e->getMessage()
            ];
        }
    }

    public function waSubscribedApps(Request $request, $whats_app_configuration_id)
    {
        try {
            if(in_array(loggedInUserType(), [1,2]))
            {
                $user_id = auth()->id();
            }
            else
            {
                $user_id = $request->user_id;
            }

            $getConfInfo = WhatsAppConfiguration::where('user_id', $user_id)->find($whats_app_configuration_id);
            if(!$getConfInfo)
            {
                return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
            }

            $access_token = base64_decode($getConfInfo->access_token);
            $waba_id = $getConfInfo->waba_id;
            $appVersion = $getConfInfo->app_version;

            $url = "https://graph.facebook.com/$appVersion/$waba_id/subscribed_apps";
            $response = Http::withHeaders([
                'Authorization' =>  'Bearer ' . $access_token,
                'Content-Type' => 'application/json' 
            ])
            ->get($url)->throw();
            if($response->successful())
            {
                $response = json_decode($response->body(), true);
                return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            else
            {
                $response = $response['error']['message'];
                return response()->json(prepareResult(true, $response, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function waDebugToken(Request $request, $whats_app_configuration_id)
    {
        try {
                if(in_array(loggedInUserType(), [1,2]))
                {
                    $user_id = auth()->id();
                }
                else
                {
                    $user_id = $request->user_id;
                }

                $getConfInfo = WhatsAppConfiguration::where('user_id', $user_id)->find($whats_app_configuration_id);
                if(!$getConfInfo)
                {
                    return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
                }

                $access_token = base64_decode($getConfInfo->access_token);
                $waba_id = $getConfInfo->waba_id;
                $appVersion = $getConfInfo->app_version;

                $url = "https://graph.facebook.com/$appVersion/debug_token?input_token=$access_token";

                $response = Http::withHeaders([
                    'Authorization' =>  'Bearer ' . $access_token,
                    'Content-Type' => 'application/json' 
                ])
                ->get($url)->throw();
                if($response->successful())
                {
                    $response = json_decode($response->body(), true);
                    return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
                }
                else
                {
                    $response = $response['error']['message'];
                    return response()->json(prepareResult(true, $response, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
                }
            } catch (\Throwable $e) {
                \Log::error($e);
                return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
    }

    public function waGetCommerceSettings(Request $request, $whats_app_configuration_id)
    {
        try {
                if(in_array(loggedInUserType(), [1,2]))
                {
                    $user_id = auth()->id();
                }
                else
                {
                    $user_id = $request->user_id;
                }

                $getConfInfo = WhatsAppConfiguration::where('user_id', $user_id)->find($whats_app_configuration_id);
                if(!$getConfInfo)
                {
                    return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
                }

                $access_token = base64_decode($getConfInfo->access_token);
                $sender_number = $getConfInfo->sender_number;
                $appVersion = $getConfInfo->app_version;

                $url = "https://graph.facebook.com/$appVersion/$sender_number/whatsapp_commerce_settings";

                $response = Http::withHeaders([
                    'Authorization' =>  'Bearer ' . $access_token,
                    'Content-Type' => 'application/json' 
                ])
                ->get($url)->throw();
                if($response->successful())
                {
                    $response = json_decode($response->body(), true);

                    $getConfInfo->is_cart_enabled = @$response['data'][0]['is_cart_enabled'];
                    $getConfInfo->is_catalog_visible = @$response['data'][0]['is_catalog_visible'];
                    $getConfInfo->wa_commerce_setting_id = @$response['data'][0]['id'];
                    $getConfInfo->save();

                    return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
                }
                else
                {
                    $response = $response['error']['message'];
                    return response()->json(prepareResult(true, $response, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
                }
            } catch (\Throwable $e) {
                \Log::error($e);
                return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
    }

    public function waSetCommerceSettings(Request $request, $whats_app_configuration_id)
    {
        try {
                if(in_array(loggedInUserType(), [1,2]))
                {
                    $user_id = auth()->id();
                }
                else
                {
                    $user_id = $request->user_id;
                }

                $getConfInfo = WhatsAppConfiguration::where('user_id', $user_id)->find($whats_app_configuration_id);
                if(!$getConfInfo)
                {
                    return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
                }

                $access_token = base64_decode($getConfInfo->access_token);
                $sender_number = $getConfInfo->sender_number;
                $appVersion = $getConfInfo->app_version;

                $is_cart_enabled = $request->is_cart_enabled;
                $is_catalog_visible = $request->is_catalog_visible;

                $url = "https://graph.facebook.com/$appVersion/$sender_number/whatsapp_commerce_settings?is_cart_enabled=$is_cart_enabled&is_catalog_visible=$is_catalog_visible";

                $response = Http::withHeaders([
                    'Authorization' =>  'Bearer ' . $access_token,
                    'Content-Type' => 'application/json' 
                ])
                ->post($url)->throw();
                if($response->successful())
                {
                    $response = json_decode($response->body(), true);

                    if($response['success']==true)
                    {
                        $url = "https://graph.facebook.com/$appVersion/$sender_number/whatsapp_commerce_settings";

                        $nresponse = Http::withHeaders([
                            'Authorization' =>  'Bearer ' . $access_token,
                            'Content-Type' => 'application/json' 
                        ])
                        ->get($url)->throw();

                        $nresponse = json_decode($nresponse->body(), true);

                        $getConfInfo->is_cart_enabled = @$nresponse['data'][0]['is_cart_enabled'];
                        $getConfInfo->is_catalog_visible = @$nresponse['data'][0]['is_catalog_visible'];
                        $getConfInfo->wa_commerce_setting_id = @$nresponse['data'][0]['id'];
                        $getConfInfo->save();
                        return response()->json(prepareResult(false, $nresponse, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
                    }
                    else
                    {
                        $response = $response['error']['message'];
                        return response()->json(prepareResult(true, $response, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
                    }
                }
                else
                {
                    $response = $response['error']['message'];
                    return response()->json(prepareResult(true, $response, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
                }
            } catch (\Throwable $e) {
                \Log::error($e);
                return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
    }

    public function updateWaToken(Request $request, $whats_app_configuration_id)
    {
        $getConfInfo = WhatsAppConfiguration::where('user_id', 3)->find($whats_app_configuration_id);
        if(!$getConfInfo)
        {
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
        }
        $getConfInfo->access_token = base64_encode($request->access_token);
        $getConfInfo->save();
        return response()->json(prepareResult(false, base64_encode($request->access_token), trans('translate.updated'), $this->intime), config('httpcodes.success'));
    }

    public function waBusinessProfile(Request $request, $whats_app_configuration_id)
    {
        try {
                if(in_array(loggedInUserType(), [1,2]))
                {
                    $user_id = auth()->id();
                }
                else
                {
                    $user_id = $request->user_id;
                }

                $getConfInfo = WhatsAppConfiguration::where('user_id', $user_id)->find($whats_app_configuration_id);
                if(!$getConfInfo)
                {
                    return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
                }

                $access_token = base64_decode($getConfInfo->access_token);
                $business_account_id = $getConfInfo->business_account_id;
                $appVersion = $getConfInfo->app_version;

                $url = "https://graph.facebook.com/$appVersion/$business_account_id/whatsapp_business_profile?fields=about,address,description,email,profile_picture_url,websites,vertical";

                $response = Http::withHeaders([
                    'Authorization' =>  'Bearer ' . $access_token,
                    'Content-Type' => 'application/json' 
                ])
                ->get($url)->throw();
                if($response->successful())
                {
                    $response = json_decode($response->body(), true);
                    return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
                }
                else
                {
                    $response = $response['error']['message'];
                    return response()->json(prepareResult(true, $response, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
                }
            } catch (\Throwable $e) {
                \Log::error($e);
                return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
    }

    public function waCallingSettingChange(Request $request, $whats_app_configuration_id)
    {
        $validation = \Validator::make($request->all(), [
            'calling.status' => 'required|in:ENABLED,DISABLED',
            'calling.call_icon_visibility' => 'nullable|in:DEFAULT,DISABLE_ALL',
            'calling.callback_permission_status' => 'required|in:ENABLED,DISABLED',

            // Call Hours
            'calling.call_hours.status' => 'required|in:ENABLED,DISABLED',
            'calling.call_hours.timezone_id' => 'required',

            'calling.call_hours.weekly_operating_hours' => 'array',
            'calling.call_hours.weekly_operating_hours.*.day_of_week' => 'required|in:MONDAY,TUESDAY,WEDNESDAY,THURSDAY,FRIDAY,SATURDAY,SUNDAY',
            'calling.call_hours.weekly_operating_hours.*.open_time' => 'required|regex:/^\d{4}$/|between:0000,2359',
            'calling.call_hours.weekly_operating_hours.*.close_time' => 'required|regex:/^\d{4}$/|between:0000,2359',

            'calling.call_hours.holiday_schedule' => 'array',
            'calling.call_hours.holiday_schedule.*.date' => 'required|date_format:Y-m-d',
            'calling.call_hours.holiday_schedule.*.start_time' => 'required|regex:/^\d{4}$/|between:0000,2359',
            'calling.call_hours.holiday_schedule.*.end_time' => 'required|regex:/^\d{4}$/|between:0000,2359',

            // SIP Config
            'calling.sip.status' => 'required|in:ENABLED,DISABLED',
            'calling.sip.servers' => 'required_if:calling.sip.status,ENABLED|array',
            'calling.sip.servers.*.hostname' => 'required_if:calling.sip.status,ENABLED|string',
            'calling.sip.servers.*.sip_user_password' => 'required_if:calling.sip.status,ENABLED|string',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
                if(in_array(loggedInUserType(), [1,2]))
                {
                    $user_id = auth()->id();
                    $getConfInfo = WhatsAppConfiguration::where('user_id', $user_id)->find($whats_app_configuration_id);
                }
                else
                {
                    $getConfInfo = WhatsAppConfiguration::find($whats_app_configuration_id);
                }

                if(!$getConfInfo)
                {
                    return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
                }

                $access_token = base64_decode($getConfInfo->access_token);
                $sender_number = $getConfInfo->sender_number;
                $appVersion = $getConfInfo->app_version;

                $payload = $request->all();

                $url = "https://graph.facebook.com/$appVersion/$sender_number/settings";

                $response = Http::withHeaders([
                    'Authorization' =>  'Bearer ' . $access_token,
                    'Content-Type' => 'application/json' 
                ])
                ->post($url, $payload)->throw();
                if($response->successful())
                {
                    $response = json_decode($response->body(), true);
                    if($response['success']==true)
                    {
                        $getConfInfo->calling_setting = $request->all();
                        $getConfInfo->save();

                        return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
                    }
                    return response()->json(prepareResult(true, $response, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
                }
                else
                {
                    $response = $response['error']['message'];
                    return response()->json(prepareResult(true, $response, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
                }
            } catch (\Throwable $e) {
                \Log::error($e);
                return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
    }

    public function waCallingSettingGet(Request $request, $whats_app_configuration_id)
    {
        try {
                if(in_array(loggedInUserType(), [1,2]))
                {
                    $user_id = auth()->id();
                    $getConfInfo = WhatsAppConfiguration::where('user_id', $user_id)->find($whats_app_configuration_id);
                }
                else
                {
                    $getConfInfo = WhatsAppConfiguration::find($whats_app_configuration_id);
                }

                if(!$getConfInfo)
                {
                    return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
                }

                $access_token = base64_decode($getConfInfo->access_token);
                $sender_number = $getConfInfo->sender_number;
                $appVersion = $getConfInfo->app_version;

                $url = "https://graph.facebook.com/$appVersion/$sender_number/settings";

                $response = Http::withHeaders([
                    'Authorization' =>  'Bearer ' . $access_token,
                    'Content-Type' => 'application/json' 
                ])
                ->get($url)->throw();
                if($response->successful())
                {
                    $response = json_decode($response->body(), true);
                    $getConfInfo->calling_setting = $response;
                    $getConfInfo->save();
                    return response()->json(prepareResult(false, $response, trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
                }
                else
                {
                    $response = $response['error']['message'];
                    return response()->json(prepareResult(true, $response, trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
                }
            } catch (\Throwable $e) {
                \Log::error($e);
                return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
            }
    }
}
