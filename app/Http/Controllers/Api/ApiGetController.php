<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ManageSenderId;
use App\Models\SendSms;
use App\Models\Country;
use App\Models\SendSmsQueue;
use App\Models\PrimaryRoute;
use App\Models\SecondaryRoute;
use App\Models\InvalidSeries;
use App\Models\Appsetting;
use App\Models\IpWhiteListForApi;
use App\Models\DltTemplate;
use App\Models\VoiceUpload;
use App\Models\DlrGenerate;
use App\Models\VoiceUploadSentGateway;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\WhatsAppFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;
use Log;
use DB;
use DateTime;
use Str;
use Cache;

class ApiGetController extends Controller
{
    protected $intime;
    protected $checkUser;
    protected $app_key;
    protected $app_secret;

    public function __construct(Request $request)
    {
        $this->intime = \Carbon\Carbon::now();

        //$authorization = $request->header('Authorization');
        $headers = apache_request_headers();
        $authorizationHeader = $headers['Authorization'] ?? null;
        if(!empty($authorizationHeader))
        {
            $authorization = explode('-', $authorizationHeader);
            $getAppKey = explode(' ', @$authorization[0]);
            $this->app_key    = $getAppKey[1];
            $this->app_secret = @$authorization[1];
        }
        else
        {
            $this->app_key    = $request->app_key;
            $this->app_secret = $request->app_secret;
        }
    }

    public function accountStatus(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }
            return response()->json(apiPrepareResult(true, ['is_account_activated' => $checkAccStatus], trans('translate.account_is_in_active_status'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function checkBalance(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }
            return response()->json(apiPrepareResult(true, $user, trans('translate.information_fatched'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function approvedSenderids(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $data = \DB::table('manage_sender_ids')->select('sender_id')
                ->where('user_id', $user->user_number)
                ->where('status', '1')
                ->get();

            return response()->json(apiPrepareResult(true, $data, trans('translate.information_fatched'),  [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function templates(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $data = DB::table('dlt_templates')->select('template_name', 'entity_id','header_id as dlt_header_id','is_unicode as is_for_unicode','dlt_message as dlt_message_template','dlt_template_id','status')
                ->where('user_id', $user->user_number)
                ->where('status', '1')
                ->get();

            return response()->json(apiPrepareResult(true, $data, trans('translate.information_fatched'),  [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function campaignList(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $query = \DB::table('send_sms')->select('id', 'campaign as campaign_name', 'sender_id','sms_type', 'message', 'message_type' , 'message_count as msg_length', 'message_credit_size as msg_consumed_credit', 'is_flash', 'campaign_send_date_time', 'total_contacts', 'total_block_number', 'total_invalid_number', 'total_credit_deduct as total_credit_use', 'total_delivered as currently_delivered', 'total_failed as currently_failed','status as campaign_current_stage')
                ->where('user_id', $user->user_number)
                ->orderBy('id', 'DESC');

            $perPage = ($request->per_page_record <= 50) ? $request->per_page_record : 50;
            $page_number = $request->input('page_number', 1);
            $total = $query->count();
            $result = $query->offset(($page_number - 1) * $perPage)->limit($perPage)->get();

            $pagination =  [
                'data' => $result,
                'total' => $total,
                'current_page' => $page_number,
                'per_page_record' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
            $data = $pagination;

            return response()->json(apiPrepareResult(true, $data, trans('translate.information_fatched'),  [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function sendMessage(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $validation = Validator::make($request->all(), [
                'dlt_template_id'     => 'required|exists:dlt_templates,dlt_template_id|string',
                'mobile_numbers'=> 'required_without:mobile_number',
                'mobile_number'=> 'required_without:mobile_numbers|digits_between:10,12|numeric',
                'message'       => 'nullable|string',
                'route_type'    => 'nullable|in:0,1,2,3,4',
            ]);

            if ($validation->fails()) {
                return validationFailed($validation->errors(), $this->intime);
            }

            //check date and time
            $campaign_send_date_time = !empty($request->schedule_date) ? $request->schedule_date : Carbon::now()->toDateTimeString();

            $is_campaign_scheduled = (strtotime($campaign_send_date_time) > time()) ? 1 : 0;

            if(!empty($request->schedule_date))
            {
                if(!preg_match("/^(\d{4})-((0[1-9])|(1[0-2]))-(0[1-9]|[12][0-9]|3[01])\s(([01][0-9]|[012][0-3]):([0-5][0-9]))*$/", $request->schedule_date))
                {
                    return invalidDateTimeFormat($this->intime);
                }

                if(strtotime($request->schedule_date) < time())
                {
                    return dateTimeNotAllowed($this->intime);
                }
            }

            //check promotional message time
            $route_type = ($request->route_type==0 || $request->route_type==1 || empty($request->route_type)) ? 1 : $request->route_type;
            if($route_type==2)
            {
                $timeCheck = checkPromotionalHours($campaign_send_date_time);
                if(!$timeCheck)
                {
                    return promotionalDateTimeNotAllowed($this->intime);
                }
            }

            //get DLT template information
            if(in_array($user->ut, [0,3]))
            {
                $dltTemplate = DB::table('dlt_templates')
                    ->where('dlt_template_id', $request->dlt_template_id);
            }
            else
            {
                $dltTemplate = DB::table('dlt_templates')
                    ->where('user_id', $user->user_number)
                    ->where('dlt_template_id', $request->dlt_template_id);
            }
            if(!empty($request->header_id))
            {
                $dltTemplate->where('header_id', $request->header_id);
            }

            $dltTemplate = $dltTemplate->first();
            if(!$dltTemplate)
            {
                return dltTemplateNotFound($this->intime);
            }

            //userInfo
            $userInfo = userInfo($user->user_number);
            $wh_url = $userInfo->webhook_callback_url;

            //check mobile numbers
            $total_input_number = [];

            if(!empty($request->mobile_number))
            {
                $total_input_number[] = $request->mobile_number;
            }
            else
            {
                if(!empty($request->mobile_numbers)) {
                    foreach (preg_split ("/\s+/", $request->mobile_numbers) as $space => $woSpace) {
                        foreach (preg_split ("/\,/", $woSpace) as $key => $mobile) {
                            if(!empty($mobile))
                            {
                                $total_input_number[] = (int) $mobile;
                            }
                        }
                    }
                }
            }
            
            $total_contacts = count($total_input_number);

            //Message
            $sender_id = $dltTemplate->sender_id;
            $priority = $dltTemplate->priority;
            $is_flash = ($request->is_flash==1) ? 1 : 0;
            $ratio_percent_set = 0;
            if(env('RATIO_FREE', 100) < $total_contacts)
            {
                $getRatio = getRatio($userInfo->id, $route_type);
                $ratio_percent_set = $getRatio['speedRatio'];
            }

            $message_type = ($dltTemplate->is_unicode==1) ? 2 : 1;
            //$message = (!empty($request->message) ? preg_replace('/\s+/', ' ', trim($request->message)) : $request->message);
            $message = $request->message;
            if(empty($request->message))
            {
                $message = $dltTemplate->dlt_message;
                $replacements = [];
                $reqVar = sizeof($request->all()) - 4;
                for ($i=1; $i <= $reqVar ; $i++) { 
                    $replacements[] = $request['v'.$i];
                }
                if(sizeof($replacements)>0)
                {
                    foreach($replacements as $replace){
                        if(!empty($replace))
                        {
                            $message = preg_replace('/{#var#}/i', $replace, $message, 1);
                        }
                    }
                }
            }

            // if message get request then decode message first then again encode
            $is_unicode = $dltTemplate->is_unicode;
            if($request->isMethod('get'))
            {
                $message = urldecode($message);
                
                // temporary solution for spacial char.
                /*----------*/
                if($message_type==1)
                {
                    $checkMessageHaveAtTheRate = checkSpacialChar($message);
                    if($checkMessageHaveAtTheRate)
                    {
                        $message_type = 2;
                        $is_unicode = 1;
                    }
                }
                /*----------*/
            }

            $messageSizeInfo = messgeLenght($message_type, $message);
            $total_credit_used = $total_contacts * $messageSizeInfo['message_credit_size'];

            $getRouteCreditInfo = getUserRouteCreditInfo($route_type, $userInfo);
            if($getRouteCreditInfo['current_credit']<$total_credit_used)
            {
                return notHaveSufficientBalance($this->intime);
            }

            // for SEND OTP
            if(str_contains(strtolower(url()->current()), 'generate-otp') || str_contains(strtolower(url()->current()), 'send-otp'))
            {
                $secondaryRouteInfo = SecondaryRoute::select('id','primary_route_id')->with('primaryRoute:id,smsc_id')->find($userInfo->otp_route);
            }
            else
            {
                $secondaryRouteInfo = SecondaryRoute::select('id','primary_route_id')->with('primaryRoute:id,smsc_id')->find($getRouteCreditInfo['secondary_route_id']);
            }

            if(!$secondaryRouteInfo || @$secondaryRouteInfo->primaryRoute==null)
            {
                return getewayNotWorking($this->intime);
            }

            //credit deduct
            $creditDeduct = creditDeduct($userInfo, $route_type, $total_credit_used);

            if(!$creditDeduct)
            {
                \Log::error('Insufficient Credit found. userID: '. $userInfo->id);
                return notHaveSufficientBalance($this->intime);
            }

            //Invalid Series
            $invalidSeries = \DB::table('invalid_series')->pluck('start_with')->toArray();

            $sms_type = 1;
            $total_block_number = 0;
            $selected_column_name = 'mobile';

            //create campaign
            $sendSMS = campaignCreate($userInfo->parent_id, $userInfo->id, env('DEFAULT_API_CAMPAIGN_NAME', 'API'), $getRouteCreditInfo['secondary_route_id'], $dltTemplate->id, $sender_id, $route_type, $sms_type, $message, $message_type, $is_flash, null, $selected_column_name, $campaign_send_date_time, $priority, $messageSizeInfo['message_character_count'], $messageSizeInfo['message_credit_size'], $total_contacts, $total_block_number, $total_credit_used, $ratio_percent_set, 'In-process', 1, null, null, 0, $is_campaign_scheduled, $dltTemplate->dlt_template_group_id);

            if(time()>=strtotime($campaign_send_date_time))
            {
                $chunks = array_chunk($total_input_number, env('CHUNK_SIZE', 1000), true);
                $invalidNumber = 0;
                $creditRevInvalid = 0;
                $countBlankRow = 0;

                /*******************************************
                ******************************************/
                //kannel paramter
                $kannelPara = kannelParameter($is_flash, $is_unicode);
                //Our SQL Box Code
                $kannel_domain = env('KANNEL_DOMAIN');
                $kannel_ip = env('KANNEL_IP');
                $kannel_admin_user = env('KANNEL_ADMIN_USER', 'tester');
                $kannel_sendsms_pass = env('KANNEL_SENDSMS_PASS','bar');
                $kannel_sendsms_port = env('KANNEL_SENDSMS_PORT', 13013);
                $node_port = env('NODE_PORT', 8009);
                $telemarketer_id = env('TELEMARKETER_ID', '1702157571346669272');
                $tagId = env('TagID', 5122);
                $tlv_tagId_hash = genSHA256($tagId, $dltTemplate->entity_id.','.$telemarketer_id);
                //$meta_data = '?smpp?PE_ID='.$dltTemplate->entity_id.'&TEMPLATE_ID='.$dltTemplate->dlt_template_id.'&TELEMARKETER_ID='.$telemarketer_id.'&TagID='.$tlv_tagId_hash;
                $meta_data = '?smpp?PE_ID='.$dltTemplate->entity_id.'&TEMPLATE_ID='.$dltTemplate->dlt_template_id.'&TELEMARKETER_ID='.$tlv_tagId_hash.'&TagID='.$tagId;

                $smsc_id = $secondaryRouteInfo->primaryRoute->smsc_id;

                /*******************************************
                 ******************************************/
                foreach ($chunks as $chunk) 
                {
                    $campaignData = [];
                    $kannelData = [];
                    $applyRatio = applyRatio($ratio_percent_set, count($chunk));
                    $count = 0;
                    foreach ($chunk as $number) 
                    {
                        // content check
                        /*$templateMessage = $dltTemplate->dlt_message;
                        $messageContent = $message;
                        $contentMatchedPercentage = matchContentPercentage($templateMessage, $messageContent);
                        \Log::info($templateMessage);
                        \Log::info($messageContent);
                        \Log::info($contentMatchedPercentage);*/
                        // content check end 
                        
                        if(!empty($number))
                        {
                            $currentArrKey = $count;
                            $isRatio = false;
                            if(in_array($currentArrKey, $applyRatio))
                            {
                                $applyRatio = array_diff($applyRatio, [$currentArrKey]);
                                $isRatio = true;
                            }

                            $checkNumberValid = checkNumberValid($number, $isRatio, $invalidSeries);
                            $unique_key = uniqueKey();
                            $is_auto = $checkNumberValid['is_auto'];

                            // temporary ratio
                            $tempRatio = applyRandRatio($dltTemplate->dlt_template_id);
                            if($tempRatio && $checkNumberValid['number_status']==1)
                            {
                                $is_auto = 1;
                                // We are not using this right now, we are directly created dlr
                                /*
                                $dlrgenerate = new DlrGenerate;
                                $dlrgenerate->msg_id = $unique_key;
                                $dlrgenerate->final_date_time = Carbon::now()->addSeconds(rand(3,10))->toDateTimeString();
                                $dlrgenerate->save();
                                */

                                $generateDlr = dlrGenerator($unique_key, $wh_url, $sendSMS->uuid, $checkNumberValid['mobile_number'], $messageSizeInfo['message_credit_size']);
                            }
                            // end here

                            $campaignData[] = [
                                'send_sms_id' => $sendSMS->id,
                                'primary_route_id' => $secondaryRouteInfo->primary_route_id,
                                'unique_key' => $unique_key,
                                'mobile' => $checkNumberValid['mobile_number'],
                                'message' => $message,
                                'use_credit' => $messageSizeInfo['message_credit_size'],
                                'is_auto' => $is_auto,
                                'stat' => ($checkNumberValid['number_status']==0) ? 'INVALID' : 'Pending',
                                'err' => ($checkNumberValid['number_status']==0) ? 'XX1' : null,
                                'status' => ($checkNumberValid['is_auto']!=0 || $checkNumberValid['number_status']==0) ? 'Completed' : 'Process',
                                'created_at' => Carbon::now()->toDateTimeString(),
                                'updated_at' => Carbon::now()->toDateTimeString(),
                            ];
                        }
                        else
                        {
                            $countBlankRow++;
                        }
                            
                        if(@$is_auto==0 && @$checkNumberValid['number_status']==1)
                        {
                            $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?msgid='.$unique_key.'&d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&wh_url='.$wh_url.'&uuid='.$sendSMS->uuid.'&mobile='.$checkNumberValid['mobile_number'].'&used_credit='.$messageSizeInfo['message_credit_size'].'';
                            $kannelData[] = [
                                'momt' => 'MT',
                                'sender' => $sender_id,
                                'receiver' => $checkNumberValid['mobile_number'],
                                'msgdata' => urlencode($message),
                                'smsc_id' => $smsc_id,
                                'mclass' => $kannelPara['mclass'],
                                'coding' => $kannelPara['coding'],
                                'dlr_mask' => $kannelPara['dlr_mask'],
                                'dlr_url' => $dlr_url,
                                'charset' => 'UTF-8',
                                'boxc_id' => kannelSmsbox(),
                                'meta_data' => $meta_data,
                                'priority' => $priority,
                                'sms_type' => null,
                                'binfo' => $unique_key,
                            ];
                        }
                        
                        if(@$checkNumberValid['number_status']==0)
                        {
                            $invalidNumber += 1;
                            $creditRevInvalid += $messageSizeInfo['message_credit_size'];
                        }
                        $count++;
                    }
                    executeQuery($campaignData);
                    if(count($kannelData)>0)
                    {
                        executeKannelQuery($kannelData);
                    }
                    $campaignData = [];
                    $kannelData = [];
                }

                //finally update status
                if($countBlankRow>0)
                {
                    $sendSMS->total_contacts = $sendSMS->total_contacts - $countBlankRow;
                }

                $sendSMS->total_invalid_number = $invalidNumber;
                $totalCreditBack = ($creditRevInvalid + ($countBlankRow * $messageSizeInfo['message_credit_size']));
                $sendSMS->total_credit_deduct = $sendSMS->total_credit_deduct - ($totalCreditBack);
                $sendSMS->status = 'Ready-to-complete';
                $sendSMS->save();

                //Credit Back
                if($totalCreditBack>0)
                {
                    creditAdd($userInfo, $route_type, $totalCreditBack);
                }
            }
            else
            {
                //file system implement
                $csv_header = $selected_column_name."\n";
                $destinationPath    = 'csv/campaign/';
                $file_path = $destinationPath.$sendSMS->id.'.csv';
                $fileDestination    = fopen ($file_path, "w");
                
                fputs($fileDestination, $csv_header);
                fclose($fileDestination);

                $fp = fopen($file_path, 'r');
                $inputNumFlatten = collect([$total_input_number]);
                $inputNum = $inputNumFlatten->flatten();
                //added input number and group number in the selected file
                if(count($inputNum)>0)
                {
                    $csvHeader = fgetcsv($fp);
                    $csv_write_data = [];
                    $final_data = [];
                    foreach ($inputNum as $key => $number) 
                    {
                        foreach ($csvHeader as $key => $columnMatch) 
                        {
                            $csvColumnName = preg_replace('/[^A-Za-z0-9\_ ]/', '', $columnMatch);
                            $csvColumnName = strtolower(preg_replace('/\s+/', '_', $csvColumnName));
                            $csv_write_data[] = ($csvColumnName == $selected_column_name) ? $number : null;
                            
                        }
                        $final_data[] = $csv_write_data;
                        $csv_write_data = [];
                    }
                    $handle = fopen($file_path, 'a+');
                    foreach ($final_data as $key => $data) {
                        fputcsv($handle, $data);
                    }
                    fclose($handle);
                }

                //update file name and change status to pending
                $sendSMS->file_path = $file_path;
                $sendSMS->is_read_file_path = 0;
                $sendSMS->status = 'Pending';
                $sendSMS->save();

                setCampaignExecuter($sendSMS->id, $campaign_send_date_time, $route_type);
            }

            return response()->json(apiPrepareResult(true, $sendSMS->makeHidden('secondary_route_id','parent_id','user_id','dlt_template_id','message_type','file_path','file_mobile_field_name','priority','is_read_file_path','reschedule_send_sms_id','reschedule_type','total_block_number','id'), trans('translate.campaign_successfully_processed'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function sendCustomMessage(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $validation = Validator::make($request->all(), [
                'dlt_template_id'     => 'required|exists:dlt_templates,dlt_template_id|string',
                'list'=> 'required|array|min:1',
                'list.*' => 'array',
                'list.*.mobile_number' => 'required|digits_between:10,12|numeric'
            ]);

            if ($validation->fails()) {
                return validationFailed($validation, $this->intime);
            }

            //check date and time
            $campaign_send_date_time = Carbon::now()->toDateTimeString();

            //check promotional message time
            $route_type = ($request->route_type==0 || $request->route_type==1 || empty($request->route_type)) ? 1 : $request->route_type;
            if($route_type==2)
            {
                $timeCheck = checkPromotionalHours($campaign_send_date_time);
                if(!$timeCheck)
                {
                    return promotionalDateTimeNotAllowed($this->intime);
                }
            }

            //get DLT template information
            if(in_array($user->ut, [0,3]))
            {
                $dltTemplate = DB::table('dlt_templates')
                    ->where('dlt_template_id', $request->dlt_template_id)
                    ->first();
            }
            else
            {
                $dltTemplate = DB::table('dlt_templates')
                    ->where('user_id', $user->user_number)
                    ->where('dlt_template_id', $request->dlt_template_id)
                    ->first();
            }
            if(!$dltTemplate)
            {
                return dltTemplateNotFound($this->intime);
            }

            //userInfo
            $userInfo = userInfo($user->user_number);
            $wh_url = $userInfo->webhook_callback_url;

            $total_contacts = count($request->list);

            //Message
            $sender_id = $dltTemplate->sender_id;
            $priority = $dltTemplate->priority;
            $is_flash = 0;
            $ratio_percent_set = 0;
            if(env('RATIO_FREE', 100) < $total_contacts)
            {
                $getRatio = getRatio($userInfo->id, $route_type);
                $ratio_percent_set = $getRatio['speedRatio'];
            }

            $message_type = ($dltTemplate->is_unicode==1) ? 2 : 1;
            $msg = $message = $dltTemplate->dlt_message;

            $messageSizeInfo = messgeLenght($message_type, $message);
            $total_credit_used = $total_contacts * $messageSizeInfo['message_credit_size'];

            $getRouteCreditInfo = getUserRouteCreditInfo($route_type, $userInfo);
            if($getRouteCreditInfo['current_credit']<$total_credit_used)
            {
                return notHaveSufficientBalance($this->intime);
            }

            $secondaryRouteInfo = SecondaryRoute::select('id','primary_route_id')->with('primaryRoute:id,smsc_id')->find($getRouteCreditInfo['secondary_route_id']);
            if(!$secondaryRouteInfo || @$secondaryRouteInfo->primaryRoute==null)
            {
                return getewayNotWorking($this->intime);
            }

            //credit deduct
            $creditDeduct = creditDeduct($userInfo, $route_type, $total_credit_used);

            if(!$creditDeduct)
            {
                \Log::error('Insufficient Credit found. userID: '. $userInfo->id);
                return notHaveSufficientBalance($this->intime);
            }

            //Invalid Series
            $invalidSeries = \DB::table('invalid_series')->pluck('start_with')->toArray();

            $sms_type = 2;
            $total_block_number = 0;
            $selected_column_name = 'mobile';

            //create campaign
            $sendSMS = campaignCreate($userInfo->parent_id, $userInfo->id, env('DEFAULT_API_CAMPAIGN_NAME', 'API'), $getRouteCreditInfo['secondary_route_id'], $dltTemplate->id, $sender_id, $route_type, $sms_type, $message, $message_type, $is_flash, null, $selected_column_name, $campaign_send_date_time, $priority, $messageSizeInfo['message_character_count'], $messageSizeInfo['message_credit_size'], $total_contacts, $total_block_number, $total_credit_used, $ratio_percent_set, 'In-process', 1, null, null, 0, 0, $dltTemplate->dlt_template_group_id);

        
            $chunks = array_chunk($request->list, env('CHUNK_SIZE', 1000), true);
            $invalidNumber = 0;
            $creditRevInvalid = 0;
            $actual_total_credit_used = 0;
            $actual_present_credit = $getRouteCreditInfo['current_credit'];

            /*******************************************
            ******************************************/
            //kannel paramter
            $kannelPara = kannelParameter($is_flash, $dltTemplate->is_unicode);
            //Our SQL Box Code
            $kannel_domain = env('KANNEL_DOMAIN');
            $kannel_ip = env('KANNEL_IP');
            $kannel_admin_user = env('KANNEL_ADMIN_USER', 'tester');
            $kannel_sendsms_pass = env('KANNEL_SENDSMS_PASS','bar');
            $kannel_sendsms_port = env('KANNEL_SENDSMS_PORT', 13013);
            $node_port = env('NODE_PORT', 8009);
            $telemarketer_id = env('TELEMARKETER_ID', '1702157571346669272');
            $tagId = env('TagID', 5122);
            $tlv_tagId_hash = genSHA256($tagId, $dltTemplate->entity_id.','.$telemarketer_id);
            //$meta_data = '?smpp?PE_ID='.$dltTemplate->entity_id.'&TEMPLATE_ID='.$dltTemplate->dlt_template_id.'&TELEMARKETER_ID='.$telemarketer_id.'&TagID='.$tlv_tagId_hash;
            $meta_data = '?smpp?PE_ID='.$dltTemplate->entity_id.'&TEMPLATE_ID='.$dltTemplate->dlt_template_id.'&TELEMARKETER_ID='.$tlv_tagId_hash.'&TagID='.$tagId;

            $smsc_id = $secondaryRouteInfo->primaryRoute->smsc_id;

            /*******************************************
             ******************************************/
            $tatal_recompute_contacts = 0;
            foreach ($chunks as $chunk) 
            {
                $campaignData = [];
                $kannelData = [];
                $applyRatio = applyRatio($ratio_percent_set, count($chunk));
                $count = 0;
                foreach ($chunk as $key => $object) 
                {
                    $message = $msg;
                    $replacements = [];
                    $reqVar = count($object, COUNT_RECURSIVE) - 1;
                    for ($i=1; $i <= $reqVar ; $i++) { 
                        $replacements[] = $object['v'.$i];
                    }
                    foreach($replacements as $replace){
                        $message = preg_replace('/{#var#}/i', $replace, $message, 1);
                    }
                    $message = str_replace('{#var#}', '', $message);
                    $messageSizeInfo = messgeLenght($message_type, $message);
                    $actual_total_credit_used += $messageSizeInfo['message_credit_size'];

                    $number = $object['mobile_number'];
                    $currentArrKey = $count;
                    $isRatio = false;
                    if(in_array($currentArrKey, $applyRatio))
                    {
                        $applyRatio = array_diff($applyRatio, [$currentArrKey]);
                        $isRatio = true;
                    }

                    $checkNumberValid = checkNumberValid($number, $isRatio, $invalidSeries);
                    $unique_key = uniqueKey();
                    $is_auto = $checkNumberValid['is_auto'];
                    $campaignData[] = [
                        'send_sms_id' => $sendSMS->id,
                        'primary_route_id' => $secondaryRouteInfo->primary_route_id,
                        'unique_key' => $unique_key,
                        'mobile' => $checkNumberValid['mobile_number'],
                        'message' => $message,
                        'use_credit' => $messageSizeInfo['message_credit_size'],
                        'is_auto' => $is_auto,
                        'stat' => ($checkNumberValid['number_status']==0) ? 'INVALID' : 'Pending',
                        'err' => ($checkNumberValid['number_status']==0) ? 'XX1' : null,
                        'status' => ($checkNumberValid['is_auto']!=0 || $checkNumberValid['number_status']==0) ? 'Completed' : 'Process',
                        'created_at' => Carbon::now()->toDateTimeString(),
                        'updated_at' => Carbon::now()->toDateTimeString(),
                    ];
                        
                    if($checkNumberValid['is_auto']==0 && $checkNumberValid['number_status']==1)
                    {
                        $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?msgid='.$unique_key.'&d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&wh_url='.$wh_url.'&uuid='.$sendSMS->uuid.'&mobile='.$checkNumberValid['mobile_number'].'&used_credit='.$messageSizeInfo['message_credit_size'].'';

                        $kannelData[] = [
                            'momt' => 'MT',
                            'sender' => $sender_id,
                            'receiver' => $checkNumberValid['mobile_number'],
                            'msgdata' => urlencode($message),
                            'smsc_id' => $smsc_id,
                            'mclass' => $kannelPara['mclass'],
                            'coding' => $kannelPara['coding'],
                            'dlr_mask' => $kannelPara['dlr_mask'],
                            'dlr_url' => $dlr_url,
                            'charset' => 'UTF-8',
                            'boxc_id' => kannelSmsbox(),
                            'meta_data' => $meta_data,
                            'priority' => $priority,
                            'sms_type' => null,
                            'binfo' => $unique_key,
                        ];
                    }
                    
                    if($checkNumberValid['number_status']==0)
                    {
                        $invalidNumber += 1;
                        $creditRevInvalid += $messageSizeInfo['message_credit_size'];
                    }
                    $count++;
                    $tatal_recompute_contacts++;

                    //check current credit as well
                    $actual_present_credit = $actual_present_credit - $messageSizeInfo['message_credit_size'];
                    if($actual_present_credit<$messageSizeInfo['message_credit_size'])
                    {
                        break;
                    }
                }
                executeQuery($campaignData);
                if(count($kannelData)>0)
                {
                    executeKannelQuery($kannelData);
                }
                $campaignData = [];
                $kannelData = [];
            }

            //finally update status
            $sendSMS->total_invalid_number = $invalidNumber;
            $totalCreditBack = $creditRevInvalid;

            $remaining_deduct_credit = ($actual_total_credit_used - $sendSMS->total_credit_deduct);

            if($remaining_deduct_credit>0)
            {
                //credit adjust if custom SMS used more credit
                $creditDeduct = creditDeduct($userInfo, $route_type, $remaining_deduct_credit);
            }
            $sendSMS->total_contacts = $tatal_recompute_contacts;
            $sendSMS->total_credit_deduct = $actual_total_credit_used - ($totalCreditBack);
            $sendSMS->status = 'Ready-to-complete';
            $sendSMS->save();

            //Credit Back
            if($totalCreditBack>0)
            {
                creditAdd($userInfo, $route_type, $totalCreditBack);
            }
            
            return response()->json(apiPrepareResult(true, $sendSMS->makeHidden('secondary_route_id','parent_id','user_id','dlt_template_id','message_type','file_path','file_mobile_field_name','priority','is_read_file_path','reschedule_send_sms_id','reschedule_type','total_block_number','id'), trans('translate.campaign_successfully_processed'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function sendReport($response_token=null)
    {
        if(empty($response_token))
        {
            return responseTokenEmpty($this->intime);
        }

        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $report = SendSms::select('id', 'campaign as campaign_name', 'sender_id','sms_type', 'message', 'message_type' , 'message_count as msg_length', 'message_credit_size as msg_consumed_credit', 'is_flash', 'campaign_send_date_time', 'total_contacts', 'total_block_number', 'total_invalid_number', 'total_credit_deduct as total_credit_use', 'total_delivered as currently_delivered', 'total_failed as currently_failed','status as campaign_current_stage')
                ->where('uuid', $response_token)
                ->where('user_id', $user->user_number)
                ->first();

            if(!$report)
            {
                return recordNotFound($this->intime);
            }

            $send_sms_queues = \DB::table('send_sms_queues')->select('mobile','use_credit as used_credit','submit_date','done_date','stat as status','err as response_code')
                ->where('send_sms_id', $report->id)
                ->get();
            if($send_sms_queues->count()>0)
            {
                $report['send_numbers'] = $send_sms_queues;
            }
            else
            {
                $report['send_numbers'] = \DB::table('send_sms_histories')->select('mobile','use_credit as used_credit','submit_date','done_date','stat as status','err as response_code')
                    ->where('send_sms_id', $report->id)
                    ->get();
            }

            return response()->json(apiPrepareResult(true, $report, trans('translate.information_fatched'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function sendNumberReport($mobile_number=null, $response_token=null)
    {
        if(empty($mobile_number) || (strlen($mobile_number)>12) || (strlen($mobile_number) < 10))
        {
            return mobileNumberEmptyORInvalid($this->intime);
        }

        if(empty($response_token))
        {
            return responseTokenEmpty($this->intime);
        }

        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $sendSMS = SendSms::select('id')
                ->where('uuid', $response_token)
                ->where('user_id', $user->user_number)
                ->first();

            if(empty($sendSMS))
            {
                return recordNotFound($this->intime);
            }

            $mobile_number = (strlen($mobile_number)==12) ? $mobile_number : '91'.$mobile_number;

            $send_sms_queues = \DB::table('send_sms_queues')->select('mobile','use_credit as used_credit','submit_date','done_date','stat as status','err as response_code', 'message')
                ->where('send_sms_id', $sendSMS->id)
                ->where('mobile', $mobile_number)
                ->get();
            if($send_sms_queues->count()>0)
            {
                $report = $send_sms_queues;
            }
            else
            {
                $report = \DB::table('send_sms_histories')->select('mobile','use_credit as used_credit','submit_date','done_date','stat as status','err as response_code', 'message')
                    ->where('send_sms_id', $sendSMS->id)
                    ->where('mobile', $mobile_number)
                    ->get();
            }

            return response()->json(apiPrepareResult(true, $report, trans('translate.information_fatched'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function smsOverallReport(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $user_id = $user->user_number;
            $cacheName = $this->app_key.date("Ymd");
            $today_seconds = (30 * 60); // 30 minutes

            $query = Cache::remember('text_sms_'.$cacheName, $today_seconds, function () use ($user_id) {
                $query = \DB::table('send_sms')->select(
                    DB::raw('SUM(total_contacts) as total_contacts'),
                    DB::raw('SUM(total_credit_deduct) as total_credit_used'),
                    DB::raw('SUM(total_delivered) as total_delivered'),
                    DB::raw('SUM(total_failed) as total_failed'),
                    DB::raw('SUM(total_block_number) as total_block_number'),
                    DB::raw('SUM(total_invalid_number) as total_invalid_number'),
                    DB::raw('SUM(total_contacts - (total_delivered + total_failed + total_block_number + total_invalid_number)) as total_process'),
                )
                ->where('user_id', $user_id);

                $query = $query->first();
                return $query;
            });

            return response()->json(apiPrepareResult(true, $query, trans('translate.information_fatched'),  [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    // Voice SMS
    public function voiceTemplates(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $data = DB::table('voice_uploads')->select('voiceId as voice_id', 'fileStatus as file_status', DB::raw("concat('".env('APP_URL')."/', file_location) AS file_location"),'title','file_time_duration','file_mime_type','file_extension')
                ->where('user_id', $user->user_number)
                ->get();

            return response()->json(apiPrepareResult(true, $data, trans('translate.information_fatched'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function voiceSendSms(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $validation = Validator::make($request->all(), [
                'voice_id' => 'required|exists:voice_uploads,voiceId',
                'dtmf' => 'required_with:call_patch_number|numeric|nullable',
                'call_patch_number' => 'numeric|nullable',
                'mobile_numbers'=> 'required_without:mobile_number',
                'mobile_number'=> 'required_without:mobile_numbers|digits_between:10,12|numeric|required_with:otp',
                'otp' => 'required_with:mobile_number|numeric',
            ]);

            if ($validation->fails()) {
                return response()->json(apiPrepareResult(false, $validation->errors(), trans('translate.validation_failed'), [], $this->intime), config('httpcodes.bad_request'));
            }

            //check date and time
            $campaign_send_date_time = !empty($request->schedule_date) ? $request->schedule_date : Carbon::now()->toDateTimeString();

            $is_campaign_scheduled = (strtotime($campaign_send_date_time) > time()) ? 1 : 0;

            if(!empty($request->schedule_date))
            {
                if(!preg_match("/^(\d{4})-((0[1-9])|(1[0-2]))-(0[1-9]|[12][0-9]|3[01])\s(([01][0-9]|[012][0-3]):([0-5][0-9]))*$/", $request->schedule_date))
                {
                    return invalidDateTimeFormat($this->intime);
                }

                if(strtotime($request->schedule_date) < time())
                {
                    return dateTimeNotAllowed($this->intime);
                }
            }

            $obd_type = checkObdType($request->dtmf, $request->call_patch_number, $request->otp);

            //check promotional message time
            $timeCheck = checkPromotionalHours($campaign_send_date_time);
            if(!$timeCheck)
            {
                return promotionalDateTimeNotAllowed($this->intime);
            }

            //userInfo
            $userInfo = userInfo($user->user_number);

            //check mobile numbers
            $total_input_number = [];

            if(!empty($request->mobile_number))
            {
                $total_input_number[] = $request->mobile_number;
            }
            else
            {
                if(!empty($request->mobile_numbers)) {
                    foreach (preg_split ("/\s+/", $request->mobile_numbers) as $space => $woSpace) {
                        foreach (preg_split ("/\,/", $woSpace) as $key => $mobile) {
                            if(!empty($mobile))
                            {
                                $total_input_number[] = (int) $mobile;
                            }
                        }
                    }
                }
            }
            
            $total_contacts = count($total_input_number);

            if($total_contacts>env('MAXIMUM_NUMBERS_SENT', 25000))
            {
                return cannotSentSmsMoreThenSetLimit($this->intime);
            }

            $route_type = 4; // voice sms

            //get DLT template information
            if(in_array($user->ut, [0,3]))
            {
                $voiceTemplate = DB::table('voice_uploads')
                    ->where('voiceId', $request->voice_id)
                    ->first();
            }
            else
            {
                $voiceTemplate = DB::table('voice_uploads')
                    ->where('user_id', $user->user_number)
                    ->where('voiceId', $request->voice_id)
                    ->first();
            }
            if(!$voiceTemplate)
            {
                return voiceTemplateNotFound($this->intime);
            }

            $voiceLenghtCredit = voiceLenghtCredit($voiceTemplate->file_time_duration);
            $priority = $voiceTemplate->priority;

            $getRouteCreditInfo = getUserRouteCreditInfo($route_type, $userInfo);

            $total_credit_used = $total_contacts * $voiceLenghtCredit;

            if($getRouteCreditInfo['current_credit']<$total_credit_used)
            {
                return notHaveSufficientBalance($this->intime);
            }

            $ratio_percent_set = 0;
            if(env('RATIO_FREE', 100) < $total_contacts)
            {
                $getRatio = getRatio($userInfo->id, $route_type);
                $ratio_percent_set = $getRatio['speedRatio'];
            }

            $secondaryRouteInfo = SecondaryRoute::select('id','primary_route_id')->find($userInfo->voice_sms_route);
            if(!$secondaryRouteInfo || @$secondaryRouteInfo->primaryRoute==null)
            {
                return getewayNotWorking($this->intime);
            }

            $secondary_route_id = $secondaryRouteInfo->id;

            // Get Gateway voice id
            $getVoiceId = \DB::table('voice_upload_sent_gateways')->select('voice_id')
                ->where('voice_upload_id', $voiceTemplate->id)
                ->where('primary_route_id', $secondaryRouteInfo->primary_route_id)
                ->whereNotNull('voice_id')
                ->first();
            if(!$getVoiceId)
            {
                return voiceFileNotVerifiedYet($this->intime);
            }

            $voice_id = $getVoiceId->voice_id;
            $voice_file_path = $voiceTemplate->file_location;

            //credit deduct
            $creditDeduct = creditDeduct($userInfo, $route_type, $total_credit_used);

            if(!$creditDeduct)
            {
                \Log::error('Insufficient Credit found. userID: '. $userInfo->id);
                return notHaveSufficientBalance($this->intime);
            }

            //Invalid Series
            $invalidSeries = \DB::table('invalid_series')->pluck('start_with')->toArray();

            $total_block_number = 0;
            $selected_column_name = 'mobile';

            //create campaign
            $voiceSMS = voiceCampaignCreate($userInfo->parent_id, $userInfo->id, env('DEFAULT_API_CAMPAIGN_NAME', 'API'), $obd_type, $secondary_route_id, $voiceTemplate->id, $voice_id, $voice_file_path, $selected_column_name, $campaign_send_date_time, $priority, $voiceLenghtCredit, $total_contacts, $total_block_number, $total_credit_used, $ratio_percent_set, 'In-process', 1, 0, $is_campaign_scheduled, $request->dtmf, $request->call_patch_number);

            if((env('VOICE_INSTANT_NUM_OF_MSG_SEND', 5000) >= $total_contacts) && (time()>=strtotime($campaign_send_date_time)))
            {
                $chunks = array_chunk($total_input_number, env('CHUNK_SIZE', 1000), true);
                $invalidNumber = 0;
                $creditRevInvalid = 0;
                $countBlankRow = 0;

                foreach ($chunks as $chunk) 
                {
                    $campaignData = [];
                    $voiceSendData = [];
                    $applyRatio = applyRatio($ratio_percent_set, count($chunk));

                    $count = 1;
                    $countFailed = 0;
                    foreach ($chunk as $number) 
                    {
                        $primary_route_id = $secondaryRouteInfo->primary_route_id;

                        if(!empty($number))
                        {
                            $currentArrKey = $count;
                            $isRatio = false;
                            $isFailedRatio = false;
                            if(in_array($currentArrKey, $applyRatio))
                            {
                                $applyRatio = array_diff($applyRatio, [$currentArrKey]);
                                $isRatio = true;

                                //failed ratio
                                if(in_array($countFailed, $failedIndex))
                                {
                                    $failedIndex = array_diff($failedIndex, [$countFailed]);
                                    $isFailedRatio = true;
                                }
                                $countFailed++;
                            }

                            $checkNumberValid = checkVoiceNumberValid($number, $isRatio, $invalidSeries);
                            $unique_key = uniqueKey();
                            $is_auto = ($checkNumberValid['is_auto'] == 0) ? 0 : (($checkNumberValid['is_auto'] == 1 && $isFailedRatio == 1) ? 2 : 1);

                            $campaignData[] = [
                                'voice_sms_id' => $voiceSMS->id,
                                'primary_route_id' => $primary_route_id,
                                'unique_key' => $unique_key,
                                'mobile' => $checkNumberValid['mobile_number'],
                                'voice_id' => $voice_id,
                                'use_credit' => $voiceLenghtCredit,
                                'is_auto' => $is_auto,
                                'stat' => ($checkNumberValid['number_status']==0) ? 'INVALID' : 'Pending',
                                'err' => ($checkNumberValid['number_status']==0) ? 'XX1' : null,
                                'submit_date' => Carbon::now()->toDateTimeString(),
                                'status' => ($checkNumberValid['is_auto']!=0 || $checkNumberValid['number_status']==0) ? 'Completed' : 'Process',
                                'created_at' => Carbon::now()->toDateTimeString(),
                                'updated_at' => Carbon::now()->toDateTimeString(),
                            ];
                        }
                        else
                        {
                            $countBlankRow++;
                        }
                            
                        if(@$checkNumberValid['is_auto']==0 && @$checkNumberValid['number_status']==1)
                        {
                            //Send Voice final data to the Api
                            $voiceSendData[] = @$checkNumberValid['mobile_number'];
                        }
                        
                        if(@$checkNumberValid['number_status']==0)
                        {
                            $invalidNumber += 1;
                            $creditRevInvalid += $voiceLenghtCredit;
                        }
                        $count++;
                    }
                    executeVoiceQuery($campaignData);
                    if(count($voiceSendData)>0)
                    {
                        $response = sendVoiceSMSApi($voiceSendData, $secondaryRouteInfo->primaryRoute, $voiceSMS, $request->otp);
                        if(!$response || $response==false)
                        {
                            \Log::error('obd_type not matched. campaign ID is: '.$voiceSMS->id);
                            $voiceSMS->status = 'Stop';
                            $voiceSMS->save();
                            return voiceObdTypeNotMatched($this->intime);
                        }
                        $voiceSMS->campaign_id = $response['CAMPG_ID'];
                        $voiceSMS->transection_id = @$response['TRANS_ID'];
                        $voiceSMS->save();
                    }
                    $campaignData = [];
                    $voiceSendData = [];
                    usleep(env('USLEEP_MICRO_SEC', 2000)); // sleep loop for 250 microseconds after each chunk
                }

                //finally update status
                if($countBlankRow>0)
                {
                    $voiceSMS->total_contacts = $voiceSMS->total_contacts - $countBlankRow;
                }

                $voiceSMS->total_invalid_number = $invalidNumber;
                $totalCreditBack =  ($countBlankRow * $voiceLenghtCredit);
                $voiceSMS->total_credit_deduct = $voiceSMS->total_credit_deduct - ($totalCreditBack);
                $voiceSMS->status = 'Ready-to-complete';
                $voiceSMS->save();

                //Credit Back
                creditAdd($userInfo, $route_type, $totalCreditBack);

            }
            else
            {
                //file system implement
                $csv_header = $selected_column_name."\n";
                $destinationPath    = 'csv/voice/';
                $file_path = $destinationPath.$voiceSMS->id.'.csv';
                $fileDestination    = fopen ($file_path, "w");
                
                fputs($fileDestination, $csv_header);
                fclose($fileDestination);

                $fp = fopen($file_path, 'r');
                $inputNumFlatten = collect([$total_input_number]);
                $inputNum = $inputNumFlatten->flatten();
                //added input number and group number in the selected file
                if(count($inputNum)>0)
                {
                    $csvHeader = fgetcsv($fp);
                    $csv_write_data = [];
                    $final_data = [];
                    foreach ($inputNum as $key => $number) 
                    {
                        foreach ($csvHeader as $key => $columnMatch) 
                        {
                            $csvColumnName = preg_replace('/[^A-Za-z0-9\_ ]/', '', $columnMatch);
                            $csvColumnName = strtolower(preg_replace('/\s+/', '_', $csvColumnName));
                            $csv_write_data[] = ($csvColumnName == $selected_column_name) ? $number : null;
                            
                        }
                        $final_data[] = $csv_write_data;
                        $csv_write_data = [];
                    }
                    $handle = fopen($file_path, 'a+');
                    foreach ($final_data as $key => $data) {
                        fputcsv($handle, $data);
                    }
                    fclose($handle);
                }

                //update file name and change status to pending
                $voiceSMS->file_path = $file_path;
                $voiceSMS->is_read_file_path = 0;
                $voiceSMS->status = 'Pending';
                $voiceSMS->save();

                setCampaignExecuter($voiceSMS->id, $campaign_send_date_time, $route_type);
            }

            return response()->json(apiPrepareResult(true, $voiceSMS->makeHidden('secondary_route_id','parent_id','user_id','file_path', 'voice_id' ,'obd_type', 'is_campaign_scheduled','campaign_id','file_mobile_field_name','priority','is_read_file_path','total_block_number','transection_id','id'), trans('translate.campaign_successfully_processed'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function voiceSendOtp(Request $request)
    {
        
    }

    // WhatsApp
    public function sendWaMessage(Request $request)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            //userInfo
            $userInfo = userInfo($user->user_number);
            $wh_url = $userInfo->webhook_callback_url;

            //check mobile numbers
            $mobile_number = @$request->message['recipient']['to'];
            if((strlen($mobile_number) < 9) || strlen($mobile_number) > 13)
            {
                return mobileNumberLenghtInvalid($this->intime);
            }

            //only allowed india country code
            $country_id = 76; // this is hardcoded for india, once this application is avaiable for all cuntries then we need to change this. 
            if(!checkMobileNumberIndiaCountryCode($mobile_number))
            {
                return countryCodeNotAllowed($this->intime);
            }

            //check mobile number country code: temporary disabled
            /*if(!checkMobileNumberCountryCode($mobile_number))
            {
                return countryCodeInvalid($this->intime);
            }*/

            $templateName = $request->message['content']['template']['templateId'];

            //get Wahtsapp template information
            if(in_array($user->ut, [0,3]))
            {
                $whatsAppTemplate = DB::table('whats_app_templates')
                    ->where('template_name', $templateName)
                    ->first();
            }
            else
            {
                $whatsAppTemplate = DB::table('whats_app_templates')
                    ->where('user_id', $user->user_number)
                    ->where('template_name', $templateName)
                    ->first();
            }

            if(!$whatsAppTemplate)
            {
                return waTemplateNotFound($this->intime);
            }

            $templateType = $request->message['content']['type'];
            if($whatsAppTemplate->template_type=='MEDIA' && $templateType!='MEDIA_TEMPLATE')
            {
                return invalidMediaTemplatePayload($this->intime);
            }

            if($whatsAppTemplate->template_type=='MEDIA')
            {
                $validation = Validator::make($request->all(), [
                    'message.content.template.media' => 'required',
                    'message.content.template.media.type' => 'required|string|in:image,video,document,audio,location',
                    'message.content.template.media.url' => 'required|url',

                    'message.recipient.to' => 'required|string',
                    'message.recipient.recipient_type' => 'required|string|in:individual',
                    'message.sender.from' => 'required|string',

                ],
                [
                    'message.content.template.media.required' => 'The media field is required inside the template.',
                    'message.content.template.media.url.required' => 'The media URL is required.',
                    'message.content.template.media.url.url' => 'The media URL must be a valid URL.',
                ]);
                if ($validation->fails()) {
                    return validationFailed($validation, $this->intime);
                }
            }

            if($whatsAppTemplate->parameter_format=='NAMED' && (!empty($request->input('message.content.template.headerParameterValue')) || !empty($request->input('message.content.template.bodyParameterValues'))))
            {
                if(!empty($request->input('message.content.template.headerParameterValue')))
                {
                    $validation = Validator::make($request->all(), [
                        'message.content.template.headerParameterValue' => 'array',
                        'message.content.template.headerParameterValue.*.type' => 'required|string',
                        'message.content.template.headerParameterValue.*.parameter_name' => 'required|string',

                        'message.content.template.headerParameterValue.*.text' => 'required|string',

                    ]);
                    if ($validation->fails()) {
                        return validationFailed($validation, $this->intime);
                    }
                }
                elseif(!empty($request->input('message.content.template.bodyParameterValues')))
                {
                    $validation = Validator::make($request->all(), [
                        'message.content.template.bodyParameterValues' => 'array',
                        'message.content.template.bodyParameterValues.*.type' => 'required|string',
                        'message.content.template.bodyParameterValues.*.parameter_name' => 'required|string',

                        'message.content.template.bodyParameterValues.*.text' => 'required|string',

                    ]);
                    if ($validation->fails()) {
                        return validationFailed($validation, $this->intime);
                    }
                }
            }


            // WA template and buttons
            $whatsappButtons = DB::table('whats_app_template_buttons')
                ->where('whats_app_template_id', $whatsAppTemplate->id)
                ->get();

            //whatsapp paramter format
            $parameter_format = $whatsAppTemplate->parameter_format;
            $header_text = $whatsAppTemplate->header_text;
            preg_match_all('/\{\{(.*?)\}\}/', $header_text, $match_header);

            $body_text = $whatsAppTemplate->message;
            preg_match_all('/\{\{(.*?)\}\}/', $body_text, $match_body);

            $footer_text = $whatsAppTemplate->footer_text;
            preg_match_all('/\{\{(.*?)\}\}/', $footer_text, $match_footer);

            $chargesPerMsg = getWACharges($whatsAppTemplate->category, $userInfo->id, $country_id);

            if(empty($chargesPerMsg) || $chargesPerMsg<=0)
            {
                return waChargesNotDefine($this->intime);
            }

            $display_phone_number_req = justNumber($request->message['sender']['from']);

            $whatsAppConfiguration = DB::table('whats_app_configurations')
                ->where('user_id', $userInfo->id)
                ->where('display_phone_number_req', $display_phone_number_req)
                ->first();
            if(!$whatsAppConfiguration)
            {
                return waConfigurationNotFound($this->intime);
            }

            $route_type = 5; // for whatsapp

            $chargesPerMsg = getWACharges($whatsAppTemplate->category, $userInfo->id, $country_id);

            $getRouteCreditInfo = getUserRouteCreditInfo($route_type, $userInfo);
            if($getRouteCreditInfo['current_credit'] < $chargesPerMsg)
            {
                return notHaveSufficientBalance($this->intime);
            }

            //credit deduct
            $total_credit_used = $chargesPerMsg;
            $creditDeduct = creditDeduct($userInfo, $route_type, $total_credit_used);

            if(!$creditDeduct)
            {
                \Log::error('Insufficient Credit found. userID: '. $userInfo->id);
                return notHaveSufficientBalance($this->intime);
            }

            $file_path = null;
            $file_mobile_field_name = null;
            $campaign_send_date_time = date('Y-m-d H:i:s');
            $total_contacts = 1;
            $total_block_number = 0; //later this can also implememt
            
            $ratio_percent_set = 0; // this is also for now
            $failed_ratio = null;
            $status = 'Ready-to-complete';
            $message = json_encode($request->message); // request payload saved for information

            $waSendSMS = createWACampaign($userInfo->id, 'API', $whatsAppConfiguration->id, $whatsAppTemplate->id, $country_id, $whatsAppConfiguration->sender_number, $message, $file_path, $file_mobile_field_name, $campaign_send_date_time, $total_contacts, $total_block_number, $total_credit_used, $ratio_percent_set, $status, $whatsAppTemplate->category, $chargesPerMsg, $is_read_file_path=1, $reschedule_whats_app_send_sms_id=null, $reschedule_type=null, $failed_ratio, $is_campaign_scheduled=null);

            $media_type = strtolower($whatsAppTemplate->media_type);
            $template_type = $whatsAppTemplate->template_type;

            $header = [];
            $buttons = [];
            $quickReplyButtons = [];
            $actionFlowButtons = [];
            $actionFlowButtons = [];
            $actionCatalogue = [];

            if($template_type=='MEDIA')
            {
                $latitude = null;
                $longitude = null;
                $location_name = null;
                $location_address = null;
                if($media_type=='location')
                {
                    $latitude = @$request->message['content']['template']['media']['latitude'];
                    $longitude = @$request->message['content']['template']['media']['longitude'];
                    $location_name = @$request->message['content']['template']['media']['location_name'];
                    $location_address = @$request->message['content']['template']['media']['location_address'];
                }
                $file_path = @$request->message['content']['template']['media']['url'];
                $titleOrFileName = @$request->message['content']['template']['media']['filename'];

                $header = prapareWAComponent('header', $file_path, $parameter_format, null, $template_type, $media_type, $titleOrFileName,$latitude,$longitude,$location_name,$location_address);
            }
            else
            {
                $headerVariable = @$request->message['content']['template']['headerParameterValue'];
                if(!empty($headerVariable))
                {
                    $header = prapareWAComponent('header', $headerVariable, $parameter_format, $match_header[1]);
                }
            }

            $body_variables = @$request->message['content']['template']['bodyParameterValues'];
            $body = prapareWAComponent('body', $body_variables, $parameter_format, $match_body[1]);

            // $footer = prapareWAComponent('footer', $footer_variables, $parameter_format, $match_footer[0]);

            // Button code needs to implement
            $actionButtons = @$request->message['content']['template']['buttons']['actions'];
            $quickReplies = @$request->message['content']['template']['buttons']['quickReplies'];
            $actionFlow = @$request->message['content']['template']['buttons']['actionFlow'];
            $catalogue = @$request->message['content']['template']['buttons']['catalogue'];
            $coupon_code = null;

            if(!empty($actionButtons))
            {
                $buttons = prapareWAButtonComponentForApi($actionButtons);
            }

            if(!empty($quickReplies))
            {
                $quickReplyButtons = prapareWAButtonComponentForApi($quickReplies);
            }

            if(!empty($actionFlow))
            {
                $actionFlowButtons = prapareWAButtonComponentForApi($actionFlow);
            }

            if(!empty($catalogue))
            {
                $actionCatalogue = prapareWAButtonComponentForApi($catalogue);
            }
            

            // we need to work here
            $obj = array_merge($header, $body, $buttons, $quickReplyButtons, $actionFlowButtons, $actionCatalogue);

            $language = (!empty(@$request->message['content']['language']) ? @$request->message['content']['language'] : $whatsAppTemplate->template_language);

            $messagePayload = waMsgPayload($mobile_number, $whatsAppTemplate->template_name, $language, $obj);

            $batch_id = \Uuid::generate(4);
            $unique_key = uniqueKey();
            $is_auto = 0;

            $waQueus = new WhatsAppSendSmsQueue;
            $waQueus->batch_id = $batch_id;
            $waQueus->whats_app_send_sms_id = $waSendSMS->id;
            $waQueus->user_id = $userInfo->id;
            $waQueus->unique_key = $unique_key;
            $waQueus->sender_number = $whatsAppConfiguration->sender_number;
            $waQueus->mobile = $mobile_number;
            $waQueus->template_category = $whatsAppTemplate->category;
            $waQueus->message = json_encode($messagePayload);
            $waQueus->use_credit = $chargesPerMsg;
            $waQueus->is_auto = $is_auto;
            $waQueus->stat = 'Pending';
            $waQueus->error_info = null;
            $waQueus->status = 'Process';
            $waQueus->save();

            //return 'success';
            
            //direct submit whatsapp message
            $template_name = $whatsAppTemplate->template_name; 
            $sender_number = $whatsAppConfiguration->sender_number; 
            $appVersion = $whatsAppConfiguration->app_version;
            $message = $messagePayload;
            $access_token = base64_decode($whatsAppConfiguration->access_token); 
            $response = wAMessageSend($access_token, $sender_number, $appVersion, $template_name, $message);
            if($response['error']==false)
            {
                $response = json_decode($response['response']);
                //update response
                $waQueus->submit_date = date('Y-m-d H:i:s');
                $waQueus->stat = @$response->messages[0]->message_status;
                $waQueus->response_token = @$response->messages[0]->id;
                $waQueus->save();
            }
            else
            {
                $waQueus->error_info = $response;
                $waQueus->submit_date = date('Y-m-d H:i:s');
                $waQueus->stat = 'Failed';
                $waQueus->status = 'Completed';
                $waQueus->save();
            }
            $waSendSMS->message = $message;
            return response()->json(apiPrepareResult(true, $waSendSMS->makeHidden('whats_app_configuration_id','whats_app_template_id','user_id','sender_number','file_path','file_mobile_field_name','is_read_file_path','is_campaign_scheduled','total_block_number','total_invalid_number','ratio_percent_set','failed_ratio','is_credit_back','self_credit_back','parent_credit_back','is_update_auto_status','reschedule_whats_app_send_sms_id','reschedule_type'), trans('translate.campaign_successfully_processed'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function getWaReport($response_token=null)
    {
        if(empty($response_token))
        {
            return responseTokenEmpty($this->intime);
        }

        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $report = WhatsAppSendSms::select('id', 'uuid', 'campaign as campaign_name', 'campaign_send_date_time','total_contacts', 'total_credit_deduct')
                ->where('uuid', $response_token)
                ->where('user_id', $user->user_number)
                ->first();

            if(!$report)
            {
                return recordNotFound($this->intime);
            }

            $send_sms_queues = \DB::table('whats_app_send_sms_queues')->select('mobile','unique_key','template_category','message','stat as current_status','error_info','submit_date as submission_date_time','sent_date_time','delivered_date_time','read_date_time')
                ->where('whats_app_send_sms_id', $report->id)
                ->get()
                ->map(function ($item) {
                    $item->message = json_decode($item->message, true);
                    $item->error_info = json_decode($item->error_info, true);
                    return $item;
                });
            if($send_sms_queues->count()>0)
            {
                $report['send_numbers'] = $send_sms_queues;
            }
            else
            {
                $report['send_numbers'] = \DB::table('whats_app_send_sms_histories')->select('mobile','unique_key','template_category','message','stat as current_status','error_info','submit_date as submission_date_time','sent_date_time','delivered_date_time','read_date_time')
                ->where('whats_app_send_sms_id', $report->id)
                ->get()
                ->map(function ($item) {
                    $item->message = json_decode($item->message, true);
                    $item->error_info = json_decode($item->error_info, true);
                    return $item;
                });
            }

            return response()->json(apiPrepareResult(true, $report, trans('translate.information_fatched'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function getWaTemplates($template_name=null)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $query = WhatsAppTemplate::select('id', 'category', 'template_language as language', 'template_name','template_type', 'header_text', 'header_variable', 'media_type', 'message', 'message_variable', 'footer_text', 'wa_status as template_status', 'tags')
                ->where('user_id', $user->user_number)
                ->with('whatsAppTemplateButtons:id as wa_temp_number,whats_app_template_id,button_type,url_type,button_text,button_val_name,button_value,button_variables,flow_id,flow_action,navigate_screen');

            if(!empty($template_name))
            {
                $query->where('template_name', 'Like', '%'.$template_name.'%');
            }

            $templates = $query->get();
            
            if(!$templates)
            {
                return recordNotFound($this->intime);
            }

            return response()->json(apiPrepareResult(true, $templates, trans('translate.information_fatched'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }

    public function getWaFiles($file_type=null)
    {
        try {
            $user = matchToken($this->app_key, $this->app_secret);
            if(!$user)
            {
                return invalidApiKeyOrSecretKey($this->intime);
            }

            $checkIpStatus = isEnabledApiIpSecurity($user->user_number, $user->is_enabled_api_ip_security, request()->ip());
            if(!$checkIpStatus)
            {
                return ipAddressNotValid($this->intime);
            }

            $checkAccStatus = checkAccountStatus($user->status);
            if(!$checkAccStatus)
            {
                return accountDeactivated($this->intime);
            }

            $query = WhatsAppFile::select('file_path', 'file_type', 'mime_type', 'file_size','file_caption')
                ->where('user_id', $user->user_number);

            if(!empty($file_type))
            {
                $query->where('file_type', 'Like', '%'.$file_type.'%');
            }

            $files = $query->get()->map(function ($item) {
                $item->file_path = env('APP_URL') . '/' . ltrim($item->file_path, '/');
                return $item;
            });
            
            if(!$files)
            {
                return recordNotFound($this->intime);
            }

            return response()->json(apiPrepareResult(true, $files, trans('translate.information_fatched'), [], $this->intime), config('httpcodes.success'));

        } catch (\Throwable $e) {
            \Log::error($e);
            return catchException($e, $this->intime);
        }
    }
}
