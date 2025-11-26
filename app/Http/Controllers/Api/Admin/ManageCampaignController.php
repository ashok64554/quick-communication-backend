<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\SendSms;
use App\Models\SendSmsQueue;
use App\Models\SendSmsHistory;
use App\Models\VoiceSms;
use App\Models\VoiceSmsQueue;
use App\Models\VoiceSmsHistory;
use App\Models\NotificationTemplate;
use App\Models\DltTemplate;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Auth;
use DB;
use Exception;
use Excel;
use Mail;
use App\Mail\CommonMail;

class ManageCampaignController extends Controller
{
    protected $intime;
    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
        $this->middleware('permission:admin-manage-campaign-status');
    }

    public function manageCampaign(Request $request)
    {
        set_time_limit(0);
        $intime = Carbon::now()->toDateTimeString();
        /*
            both_update_date_time,

            queue_accept_to_deliverd,
            history_accept_to_deliverd,
            queue_pending_to_deliverd,
            history_pending_to_deliverd,

            queue_accept_to_failed,
            history_accept_to_failed,
            queue_pending_to_failed,
            history_pending_to_failed,

            resend_all_campaign_auto_deliver,
            resend_campaign_not_yet_deliver_number,
            update_auto_status,
            queue_update_accepted_to_actual_status_final_ack,
            history_update_accepted_to_actual_status_final_ack,

            apply_ratio,
            resubmit_pending_sms


        */
        $validation = \Validator::make($request->all(),[ 
            'action_type'     => 'required|in:both_update_date_time,queue_accept_to_deliverd,history_accept_to_deliverd,queue_pending_to_deliverd,history_pending_to_deliverd,queue_accept_to_failed,history_accept_to_failed,queue_pending_to_failed,history_pending_to_failed,resend_all_campaign_auto_deliver,resend_campaign_not_yet_deliver_number,update_auto_status,queue_update_accepted_to_actual_status_final_ack,history_update_accepted_to_actual_status_final_ack,apply_ratio,resubmit_pending_sms',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if($request->action_type=='apply_ratio')
        {
            $validation = \Validator::make($request->all(),[
                'ratio_percent_to_delivered'     => 'required|numeric|min:1|max:100',
                'send_sms_id'     => 'required|exists:send_sms,id',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        if($request->action_type=='resubmit_pending_sms')
        {
            $validation = \Validator::make($request->all(),[
                'sms_queue_id'     => 'required|numeric|exists:send_sms_queues,id',
                'no_of_submit_sms'     => 'required|numeric',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        try {
            $hcount = 0;
            if($request->action_type == 'both_update_date_time') 
            {
                if(!empty($request->send_sms_id))
                {
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second))  
                    WHERE `stat`='Pending' AND `send_sms_id` = '".$request->send_sms_id."';");

                    DB::statement("UPDATE `send_sms_histories` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second))  
                    WHERE `stat`='Pending' AND `send_sms_id` = '".$request->send_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second))  
                    WHERE `stat`='Pending'");

                    DB::statement("UPDATE `send_sms_histories` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second))  
                    WHERE `stat`='Pending'");
                }
                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'queue_accept_to_deliverd') 
            {
                if(!empty($request->send_sms_id))
                {
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
                    `stat` = 'DELIVRD',
                    `err` = '000',
                    `status` = 'Completed'
                    WHERE `stat`='Accepted' AND `send_sms_id` = '".$request->send_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
                    `stat` = 'DELIVRD',
                    `err` = '000',
                    `status` = 'Completed'  
                    WHERE `stat`='Accepted'");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'history_accept_to_deliverd') 
            {
                if(!empty($request->send_sms_id))
                {
                    DB::statement("UPDATE `send_sms_histories` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
                    `stat` = 'DELIVRD',
                    `err` = '000',
                    `status` = 'Completed' 
                    WHERE `stat`='Accepted' AND `send_sms_id` = '".$request->send_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `send_sms_histories` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
                    `stat` = 'DELIVRD',
                    `err` = '000',
                    `status` = 'Completed'  
                    WHERE `stat`='Accepted'");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'queue_pending_to_deliverd') 
            {
                if(!empty($request->send_sms_id))
                {
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
                    `stat` = 'DELIVRD',
                    `err` = '000',
                    `status` = 'Completed' 
                    WHERE `stat`='Pending' AND `send_sms_id` = '".$request->send_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
                    `stat` = 'DELIVRD',
                    `err` = '000',
                    `status` = 'Completed'  
                    WHERE `stat`='Pending'");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'history_pending_to_deliverd') 
            {
                if(!empty($request->send_sms_id))
                {
                    DB::statement("UPDATE `send_sms_histories` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
                    `stat` = 'DELIVRD',
                    `err` = '000',
                    `status` = 'Completed' 
                    WHERE `stat`='Pending' AND `send_sms_id` = '".$request->send_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `send_sms_histories` SET `response_token`= COALESCE(response_token, UCASE(LEFT(MD5(mobile), 32))), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
                    `stat` = 'DELIVRD',
                    `err` = '000',
                    `status` = 'Completed'  
                    WHERE `stat`='Pending'");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'queue_accept_to_failed') 
            {
                if(!empty($request->send_sms_id))
                {
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
                    `stat`='FAILED', 
                    `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
                    `status` = 'Completed' 
                    WHERE `stat`='Accepted' AND `send_sms_id` = '".$request->send_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
                    `stat`='FAILED', 
                    `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
                    `status` = 'Completed' 
                    WHERE `stat`='Accepted';");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'history_accept_to_failed') 
            {
                if(!empty($request->send_sms_id))
                {
                    DB::statement("UPDATE `send_sms_histories` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
                    `stat`='FAILED', 
                    `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
                    `status` = 'Completed' 
                    WHERE `stat`='Accepted' AND `send_sms_id` = '".$request->send_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `send_sms_histories` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
                    `stat`='FAILED', 
                    `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
                    `status` = 'Completed' 
                    WHERE `stat`='Accepted';");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'queue_pending_to_failed') 
            {
                if(!empty($request->send_sms_id))
                {
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
                    `stat`='FAILED', 
                    `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
                    `status` = 'Completed' 
                    WHERE `stat`='Pending' AND `send_sms_id` = '".$request->send_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
                    `stat`='FAILED', 
                    `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
                    `status` = 'Completed' 
                    WHERE `stat`='Pending';");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'history_pending_to_failed') 
            {
                if(!empty($request->send_sms_id))
                {
                    DB::statement("UPDATE `send_sms_histories` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
                    `stat`='FAILED', 
                    `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
                    `status` = 'Completed' 
                    WHERE `stat`='Pending' AND `send_sms_id` = '".$request->send_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `send_sms_histories` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
                    `stat`='FAILED', 
                    `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
                    `status` = 'Completed' 
                    WHERE `stat`='Pending';");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'resend_all_campaign_auto_deliver') 
            {
                $count = 0;
                if(!empty($request->send_sms_id))
                {
                    $sendSMS = SendSms::find($request->send_sms_id);
                    if(!$sendSMS)
                    {
                        return response()->json(prepareResult(true, trans('translate.no_records_found'), trans('translate.no_records_found'), $this->intime), config('httpcodes.internal_server_error'));
                    }

                    // webhook only for api request now so we comment this line and pass null value.
                    //userInfo
                    // $userInfo = userInfo($sendSMS->user_id);
                    // $wh_url = $userInfo->webhook_callback_url;
                    $wh_url = null;

                    $smsc_id = $sendSMS->secondaryRoute->primaryRoute->smsc_id;
                    $priority = $sendSMS->priority;
                    $sender_id = $sendSMS->sender_id;
                    $dltTemplate = \DB::table('dlt_templates')->find($sendSMS->dlt_template_id);

                    //get associated routes (gateway)
                    $associated_routes = \DB::table('primary_route_associateds')
                        ->join('primary_routes', 'primary_routes.id', '=', 'primary_route_associateds.associted_primary_route')
                        ->where('primary_route_id', $sendSMS->secondaryRoute->primary_route_id)
                        ->pluck('smsc_id', 'id')
                        ->toArray();
                    if(sizeof($associated_routes)<1)
                    {
                        $associated_routes = [$sendSMS->secondaryRoute->primary_route_id => $sendSMS->secondaryRoute->primaryRoute->smsc_id];
                    }

                    $kannelPara = kannelParameter($sendSMS->is_flash, $dltTemplate->is_unicode);

                    //Our SQL Box Code
                    $kannel_domain = env('KANNEL_DOMAIN');
                    $kannel_ip = env('KANNEL_IP');
                    $kannel_admin_user = env('KANNEL_ADMIN_USER', 'tester');
                    $kannel_sendsms_pass = env('KANNEL_SENDSMS_PASS','bar');
                    $kannel_sendsms_port = env('KANNEL_SENDSMS_PORT', 13013);
                    $node_port = env('NODE_PORT', 8009);
                    $telemarketer_id = env('TELEMARKETER_ID', '1702157571346669272');
                    $meta_data = '?smpp?PE_ID='.$dltTemplate->entity_id.'&TEMPLATE_ID='.$dltTemplate->dlt_template_id.'&TELEMARKETER_ID='.$telemarketer_id;

                    $queue = SendSmsQueue::where('is_auto', '!=', 0)
                    ->where('send_sms_id', $request->send_sms_id)
                    ->chunkById(env('CHUNK_SIZE', 1000), function ($records) use ($kannelPara, $meta_data, $kannel_ip, $node_port, $smsc_id, $priority, $sender_id,&$count, $associated_routes,$wh_url)
                    {
                        
                        foreach ($records as $rec) 
                        {
                            $getPRInfo = getRandomSingleArray($associated_routes);
                            $primary_route_id = $getPRInfo['key'];
                            $smsc_id = $getPRInfo['value'];
                            
                            $unique_key = $rec->unique_key;
                            $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?msgid='.$unique_key.'&d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&wh_url='.$wh_url.'';
                            $kannelData[] = [
                                'momt' => 'MT',
                                'sender' => $sender_id,
                                'receiver' => $rec->mobile,
                                'msgdata' => urlencode($rec->message),
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
                            $count++;
                        }
                        executeKannelQuery($kannelData);
                        $kannelData = [];
                    }); 

                    // update ratio
                    $sendSMS->ratio_percent_set = 0;
                    $sendSMS->failed_ratio = 0;
                    $sendSMS->save();

                    DB::statement("UPDATE `send_sms_queues` SET `is_auto`= '0' WHERE `is_auto` != '0' AND `send_sms_id` = '".$request->send_sms_id."';");
                }

                return response()->json(prepareResult(false, [], trans('translate.number_of_record_updated').' :'. $count, $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'resend_campaign_not_yet_deliver_number') 
            {
                $count = 0;
                if(!empty($request->send_sms_id))
                {
                    $sendSMS = SendSms::find($request->send_sms_id);
                    if(!$sendSMS)
                    {
                        return response()->json(prepareResult(true, trans('translate.no_records_found'), trans('translate.no_records_found'), $this->intime), config('httpcodes.internal_server_error'));
                    }

                    // webhook only for api request now so we comment this line and pass null value.
                    //userInfo
                    // $userInfo = userInfo($sendSMS->user_id);
                    // $wh_url = $userInfo->webhook_callback_url;
                    $wh_url = null;

                    $smsc_id = $sendSMS->secondaryRoute->primaryRoute->smsc_id;

                    //get associated routes (gateway)
                    $associated_routes = \DB::table('primary_route_associateds')
                        ->join('primary_routes', 'primary_routes.id', '=', 'primary_route_associateds.associted_primary_route')
                        ->where('primary_route_id', $sendSMS->secondaryRoute->primary_route_id)
                        ->pluck('smsc_id', 'id')
                        ->toArray();
                    if(sizeof($associated_routes)<1)
                    {
                        $associated_routes = [$sendSMS->secondaryRoute->primary_route_id => $sendSMS->secondaryRoute->primaryRoute->smsc_id];
                    }

                    $priority = $sendSMS->priority;
                    $sender_id = $sendSMS->sender_id;
                    $dltTemplate = \DB::table('dlt_templates')->find($sendSMS->dlt_template_id);

                    $kannelPara = kannelParameter($sendSMS->is_flash, $dltTemplate->is_unicode);

                    //Our SQL Box Code
                    $kannel_domain = env('KANNEL_DOMAIN');
                    $kannel_ip = env('KANNEL_IP');
                    $kannel_admin_user = env('KANNEL_ADMIN_USER', 'tester');
                    $kannel_sendsms_pass = env('KANNEL_SENDSMS_PASS','bar');
                    $kannel_sendsms_port = env('KANNEL_SENDSMS_PORT', 13013);
                    $node_port = env('NODE_PORT', 8009);
                    $telemarketer_id = env('TELEMARKETER_ID', '1702157571346669272');
                    $meta_data = '?smpp?PE_ID='.$dltTemplate->entity_id.'&TEMPLATE_ID='.$dltTemplate->dlt_template_id.'&TELEMARKETER_ID='.$telemarketer_id;
                    
                    $queue = SendSmsQueue::where('is_auto', 0)
                    ->where('send_sms_id', $request->send_sms_id)
                    ->where('stat', 'Pending')
                    ->chunkById(env('CHUNK_SIZE', 1000), function ($records) use ($kannelPara, $meta_data, $kannel_ip, $node_port, $smsc_id, $priority, $sender_id,&$count, $associated_routes,$wh_url)
                    {
                        
                        foreach ($records as $rec) 
                        {
                            $getPRInfo = getRandomSingleArray($associated_routes);
                            $primary_route_id = $getPRInfo['key'];
                            $smsc_id = $getPRInfo['value'];

                            $unique_key = $rec->unique_key;
                            $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?msgid='.$unique_key.'&d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&wh_url='.$wh_url.'';
                            $kannelData[] = [
                                'momt' => 'MT',
                                'sender' => $sender_id,
                                'receiver' => $rec->mobile,
                                'msgdata' => urlencode($rec->message),
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
                            $count++;
                        }
                        executeKannelQuery($kannelData);
                        $kannelData = [];
                    });
                }

                return response()->json(prepareResult(false, [], trans('translate.number_of_record_submitted').' :'. $count, $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'update_auto_status')
            {
                reUpdatePending($request->send_sms_id);
                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'queue_update_accepted_to_actual_status_final_ack' || $request->action_type == 'history_update_accepted_to_actual_status_final_ack')
            {
                $operation = ($request->action_type == 'history_update_accepted_to_actual_status_final_ack') ? 'history' : 'queue';

                updateAcceptedToStatusWise($request->send_sms_id, $operation);
                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'apply_ratio') 
            {
                $totalDelivered = 0;
                $result = \DB::select('CALL getDeliveredCount(?)', [$request->send_sms_id]);
                foreach ($result as $key => $value) {
                    $totalDelivered = $value->total_delivered;
                }

                $totalSubmits = SendSms::select('total_contacts')->find($request->send_sms_id)->total_contacts;
                $remaining =  $totalSubmits - $totalDelivered;
                $calPercentage = (int) floor((($totalSubmits * $request->ratio_percent_to_delivered)/100));
                $updateRemaining =  $calPercentage - $totalDelivered;
                //\Log::info($totalSubmits.'-'.$totalDelivered.'-'.$remaining.'-'.$calPercentage.'-'.$updateRemaining);
                if($updateRemaining > 0)
                {  
                    DB::statement("UPDATE `send_sms_queues` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                    `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                    `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second), 
                    `stat`='DELIVRD', 
                    `err` = '000',
                    `status` = 'Completed' 
                    WHERE `send_sms_id` = '".$request->send_sms_id."' AND `stat` NOT IN ('DELIVRD','INVALID','BLOCK') ORDER BY RAND() LIMIT ".$updateRemaining.";");

                    $result = \DB::select('CALL getDeliveredCount(?)', [$request->send_sms_id]);
                    foreach ($result as $key => $value) {
                        $totalDelivered = $value->total_delivered;
                    }
                    $stillRemains = $calPercentage - ($totalDelivered);
                    if($stillRemains > 0)
                    {
                        DB::statement("UPDATE `send_sms_histories` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                        `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                        `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second), 
                        `stat`='DELIVRD', 
                        `err` = '000',
                    `status` = 'Completed' 
                        WHERE `send_sms_id` = '".$request->send_sms_id."' AND `stat` NOT IN ('DELIVRD','INVALID','BLOCK') ORDER BY RAND() LIMIT ".$stillRemains.";");
                    }
                }
                else
                {
                    //some entries need to set failed
                    $updateRemaining = abs($updateRemaining);
                    $this->recursiveUpdateRatio($request->send_sms_id, $updateRemaining, $calPercentage);
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }
            elseif($request->action_type == 'resubmit_pending_sms') 
            {
                $records = SendSmsQueue::where('stat', 'Pending')
                    ->where('is_auto', 0)
                    ->where('id', '<=', $request->sms_queue_id)
                    ->take($request->no_of_submit_sms)
                    ->orderBy('id', 'DESC')
                    ->get();
                $nrecords = $records->chunk(env('CHUNK_SIZE', 1000));
                foreach ($nrecords as $key => $queueSMSs) 
                {
                    foreach ($queueSMSs as $nkey => $findSMS) 
                    {
                        $smsInfo = $findSMS->sendSms;

                        // webhook only for api request now so we comment this line and pass null value.
                        //userInfo
                        // $userInfo = userInfo($smsInfo->user_id);
                        // $wh_url = $userInfo->webhook_callback_url;
                        $wh_url = null;
                        
                        $dltTemplate = $findSMS->sendSms->dltTemplate;
                        $is_unicode = ($smsInfo->message_type==1) ? 0 : 1;

                        //kannel paramter
                        $kannelPara = kannelParameter($smsInfo->is_flash, $is_unicode);
                        //Our SQL Box Code
                        $kannel_domain = env('KANNEL_DOMAIN');
                        $kannel_ip = env('KANNEL_IP');
                        $kannel_admin_user = env('KANNEL_ADMIN_USER', 'tester');
                        $kannel_sendsms_pass = env('KANNEL_SENDSMS_PASS','bar');
                        $kannel_sendsms_port = env('KANNEL_SENDSMS_PORT', 13013);
                        $node_port = env('NODE_PORT', 8009);
                        $telemarketer_id = env('TELEMARKETER_ID', '1702157571346669272');
                        $meta_data = '?smpp?PE_ID='.$dltTemplate->entity_id.'&TEMPLATE_ID='.$dltTemplate->dlt_template_id.'&TELEMARKETER_ID='.$telemarketer_id;
                        $smsc_id = $findSMS->primaryRoute->smsc_id;
                        $priority = $smsInfo->priority;

                        $dlr_url = 'http://'.$kannel_ip.':'.$node_port.'/DLR?msgid='.$findSMS->unique_key.'&d=%d&oo=%O&ff=%F&s=%s&ss=%S&aa=%A&wh_url='.$wh_url.'';
                        $kannelData[] = [
                            'momt' => 'MT',
                            'sender' => $smsInfo->sender_id,
                            'receiver' => $findSMS->mobile,
                            'msgdata' => urlencode($findSMS->message),
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
                            'binfo' => $findSMS->unique_key,
                        ];
                    }
                    executeKannelQuery($kannelData);
                    $kannelData = [];
                }
                
                return response()->json(prepareResult(false, [], trans('translate.number_of_record_submitted').' :'. ($records->count()), $this->intime), config('httpcodes.success'));
            }
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    private function recursiveUpdateRatio($send_sms_id, $updateRemaining, $calPercentage)
    {
        if($updateRemaining>0)
        {
            DB::statement("UPDATE `send_sms_queues` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
            `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
            `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
            `stat`='FAILED', 
            `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
            `status` = 'Completed' 
            WHERE `send_sms_id` = '".$send_sms_id."' AND `stat` NOT IN ('INVALID','BLOCK') ORDER BY RAND() LIMIT ".$updateRemaining.";");

            $totalDelivered = 0;
            $result = \DB::select('CALL getDeliveredCount(?)', [$send_sms_id]);
            foreach ($result as $key => $value) {
                $totalDelivered = $value->total_delivered;
            }

            $stillRemains =  $calPercentage - ($totalDelivered);
            if($stillRemains < 0)
            {
                $stillRemains = abs($stillRemains);
                DB::statement("UPDATE `send_sms_histories` SET `response_token`= UCASE(LEFT(MD5(mobile), 32)), 
                `submit_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second), 
                `done_date`= DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 30) second), 
                `stat`='FAILED', 
                `err` = ELT(0.5 + RAND() * 8, '001','160','172','173','012','036','040','060'),
                `status` = 'Completed'
                WHERE `send_sms_id` = '".$send_sms_id."' AND `stat` NOT IN ('INVALID','BLOCK') ORDER BY RAND() LIMIT ".$stillRemains.";");
            }
        }

        $totalDelivered = 0;
        $result = \DB::select('CALL getDeliveredCount(?)', [$send_sms_id]);
        foreach ($result as $key => $value) {
            $totalDelivered = $value->total_delivered;
        }

        $stillRemains =  $calPercentage - ($totalDelivered);
        if($stillRemains < 0)
        {
            $updateRemaining = abs($stillRemains);
            $this->recursiveUpdateRatio($send_sms_id, $updateRemaining, $calPercentage);
        }

        return true;
    }

    public function changeCampaignStatusToComplete($id)
    {
        try {
            $campaign = SendSms::find($id);
            if($campaign)
            {
                $campaign->status = 'Completed';
                $campaign->Save();

                return response()->json(prepareResult(false, $campaign, trans('translate.updated'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, [], trans('translate.record_not_found'), $this->intime), config('httpcodes.not_found'));
                
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function getUserRouteInfo(Request $request)
    {
        try {
            $query = User::select('id','name','promotional_route', 'promotional_credit', 'transaction_route', 'transaction_credit', 'two_waysms_route', 'two_waysms_credit', 'voice_sms_route', 'voice_sms_credit')
            ->with('promotionalRouteInfo:id,sec_route_name,primary_route_id','transactionRouteInfo:id,sec_route_name,primary_route_id','twoWaysmsRouteInfo:id,sec_route_name,primary_route_id','voiceSmsRouteInfo:id,sec_route_name,primary_route_id','promotionalRouteInfo.primaryRoute:id,route_name','transactionRouteInfo.primaryRoute:id,route_name','twoWaysmsRouteInfo.primaryRoute:id,route_name','voiceSmsRouteInfo.primaryRoute:id,route_name');

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

    public function informingUserAboutServer(Request $request)
    {
        $validation = \Validator::make($request->all(),[ 
            'notification_template_id'=> 'required_without:dlt_template_id',
            'dlt_template_id'=> 'required_without:notification_template_id',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        try {
            $error = null;
            if(is_array($request->user_id) && sizeof($request->user_id)>0)
            {
                $users = User::select('id', 'uuid', 'name', 'email', 'mobile')->whereIn('id', $request->user_id);
            }
            elseif($request->for_user==1)
            {
                $users = User::select('id', 'uuid', 'name', 'email', 'mobile');
            }
            else
            {
                return response()->json(prepareResult(true, trans('translate.select_user_before_sending_notification'), trans('translate.select_user_before_sending_notification'), $this->intime), config('httpcodes.internal_server_error'));
            }
            
            if(!empty($request->notification_template_id))
            {
                $notification = NotificationTemplate::find($request->notification_template_id);
                if($notification)
                {
                    if(!empty($notification->mail_subject))
                    {
                        $setupBcc = config('defaultMailBcc');
                        $emails = $users->pluck('email');
                        $chunks = $emails->chunk(env('CHUNK_SIZE', 1000));
                        foreach ($chunks as $sendTos) 
                        {
                            foreach($sendTos as $sendTo)
                            {
                                //send email
                                if(env('IS_MAIL_SEND_ENABLE', false))
                                {
                                    //send mail
                                    $mailObj = [
                                        'template_name' => $notification->notification_for,
                                        'mail_subject'  => $request->mail_subject,
                                        'mail_body'     => $request->mail_body,
                                        'other_info'    => null,
                                    ];
                                    Mail::to($sendTo)
                                    ->bcc($setupBcc)
                                    ->send(new CommonMail($mailObj));
                                }
                            }
                        } 
                    }                   

                    if(!empty($notification->notification_subject))
                    {
                        ////////notification//////////
                        $userNotification = $users->get();
                        foreach ($userNotification as $key => $user) 
                        {
                            $variable_data = [];

                            notification($notification->notification_for, $user, $variable_data, null, null, null, false, $request->notification_subject, $request->notification_body);
                        }
                        /////////////////////////////////////
                    } 
                }
                else
                {
                    $error .= "Notification template not found.";
                }
            }

            elseif(!empty($request->dlt_template_id))
            {
                $validation = \Validator::make($request->all(),[ 
                    'dlt_message'=> 'required',
                ]);

                if ($validation->fails()) {
                    return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
                }

                $dlt_template = DltTemplate::select('dlt_template_id')->find($request->dlt_template_id);
                if($dlt_template)
                {
                    $credencials = User::select('app_key', 'app_secret')->first();
                    $mobiles = $users->pluck('mobile')->unique()->toArray();
                    $mobile_numbers = implode(',', $mobiles);
                    $campaign = sendSmsThroughApi($credencials->app_key, $credencials->app_secret, $dlt_template->dlt_template_id, $request->dlt_message, $mobile_numbers);
                }
                else
                {
                    $error .= "\nDLT template not found.";
                }
            }

            return response()->json(prepareResult(false, $error, trans('translate.success'), $this->intime), config('httpcodes.success'));
                
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function reupdatePendingStatus($send_sms_id)
    {
        try {
            reUpdatePending($send_sms_id);
            return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function manageVoiceCampaign(Request $request)
    {
        set_time_limit(0);
        $intime = Carbon::now()->toDateTimeString();
        /*
            queue_process_to_answered,
            history_process_to_answered,

            queue_process_to_failed,
            history_process_to_failed,

            resend_all_campaign_auto_deliver,
            update_auto_status,

            apply_ratio,

        */
        $validation = \Validator::make($request->all(),[ 
            'action_type'     => 'required|in:queue_process_to_answered,history_process_to_answered,queue_process_to_failed,history_process_to_failed,resend_all_campaign_auto_deliver,update_auto_status,apply_ratio',
        ]);

        if ($validation->fails()) {
            return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
        }

        if($request->action_type=='apply_ratio')
        {
            $validation = \Validator::make($request->all(),[
                'ratio_percent_to_delivered'     => 'required|numeric|min:1|max:100',
                'voice_sms_id'     => 'required|exists:voice_sms,id',
            ]);

            if ($validation->fails()) {
                return response()->json(prepareResult(true, $validation->messages(), trans('translate.validation_failed'), $this->intime), config('httpcodes.bad_request'));
            }
        }

        try {
            $hcount = 0;
            $time = time();
            if($request->action_type == 'queue_process_to_answered') 
            {
                if(!empty($request->voice_sms_id))
                {
                    DB::statement("UPDATE `voice_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second)),
                    `stat` = 'Answered',
                    `cli` = ".$time.",
                    `flag` = null,
                    `start_time` = COALESCE(start_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 4) second)),
                    `end_time` = COALESCE(end_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 20) second)),
                    `duration` = TIMESTAMPDIFF(SECOND, start_time, end_time),
                    `dtmf` = null,
                    `status` = 'Completed'
                    WHERE `stat`='Process' AND `voice_sms_id` = '".$request->voice_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `voice_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second)),
                    `stat` = 'Answered',
                    `cli` = ".$time.",
                    `flag` = null,
                    `start_time` = COALESCE(start_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 4) second)),
                    `end_time` = COALESCE(end_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 20) second)),
                    `duration` = TIMESTAMPDIFF(SECOND, start_time, end_time),
                    `dtmf` = null,
                    `status` = 'Completed'  
                    WHERE `stat`='Process'");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'history_process_to_answered') 
            {
                if(!empty($request->voice_sms_id))
                {
                    DB::statement("UPDATE `voice_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second)),
                    `stat` = 'Answered',
                    `cli` = ".$time.",
                    `flag` = null,
                    `start_time` = COALESCE(start_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 4) second)),
                    `end_time` = COALESCE(end_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 20) second)),
                    `duration` = TIMESTAMPDIFF(SECOND, start_time, end_time),
                    `dtmf` = null,
                    `status` = 'Completed'
                    WHERE `stat`='Accepted' AND `voice_sms_id` = '".$request->voice_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `voice_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second)),
                    `stat` = 'Answered',
                    `cli` = ".$time.",
                    `flag` = null,
                    `start_time` = COALESCE(start_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 4) second)),
                    `end_time` = COALESCE(end_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 20) second)),
                    `duration` = TIMESTAMPDIFF(SECOND, start_time, end_time),
                    `dtmf` = null,
                    `status` = 'Completed' 
                    WHERE `stat`='Process'");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'queue_process_to_failed') 
            {
                if(!empty($request->voice_sms_id))
                {
                    DB::statement("UPDATE `voice_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, TIMESTAMP(DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second))),
                    `stat` = 'Failed',
                    `cli` = ".$time.",
                    `flag` = null,
                    `start_time` = null,
                    `end_time` = null,
                    `duration` = null,
                    `dtmf` = null,
                    `status` = 'Completed'
                    WHERE `stat`='Process' AND `voice_sms_id` = '".$request->voice_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `voice_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, TIMESTAMP(DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second))),
                    `stat` = 'Failed',
                    `cli` = ".$time.",
                    `flag` = null,
                    `start_time` = null,
                    `end_time` = null,
                    `duration` = null,
                    `dtmf` = null,
                    `status` = 'Completed' 
                    WHERE `stat`='Process';");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'history_process_to_failed') 
            {
                if(!empty($request->voice_sms_id))
                {
                    DB::statement("UPDATE `voice_sms_histories` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, TIMESTAMP(DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second))),
                    `stat` = 'Failed',
                    `cli` = ".$time.",
                    `flag` = null,
                    `start_time` = null,
                    `end_time` = null,
                    `duration` = null,
                    `dtmf` = null,
                    `status` = 'Completed'
                    WHERE `stat`='Process' AND `voice_sms_id` = '".$request->voice_sms_id."';");
                }
                else
                {
                    DB::statement("UPDATE `voice_sms_histories` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, TIMESTAMP(DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second))),
                    `stat` = 'Failed',
                    `cli` = ".$time.",
                    `flag` = null,
                    `start_time` = null,
                    `end_time` = null,
                    `duration` = null,
                    `dtmf` = null,
                    `status` = 'Completed' 
                    WHERE `stat`='Process';");
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'resend_all_campaign_auto_deliver') 
            {
                $count = 0;
                if(!empty($request->voice_sms_id))
                {
                    $voiceSms = VoiceSms::find($request->voice_sms_id);
                    if(!$voiceSms)
                    {
                        return response()->json(prepareResult(true, trans('translate.no_records_found'), trans('translate.no_records_found'), $this->intime), config('httpcodes.internal_server_error'));
                    }
                    $smsc_id = $voiceSms->secondaryRoute->primaryRoute->smsc_id;
                    $priority = $voiceSms->priority;
                    $secondaryRouteInfo = $voiceSms->secondaryRoute;

                    //get associated routes (gateway)
                    $associated_routes = \DB::table('primary_route_associateds')
                        ->join('primary_routes', 'primary_routes.id', '=', 'primary_route_associateds.associted_primary_route')
                        ->where('primary_route_id', $voiceSms->secondaryRoute->primary_route_id)
                        ->pluck('smsc_id', 'id')
                        ->toArray();
                    if(sizeof($associated_routes)<1)
                    {
                        $associated_routes = [$voiceSms->secondaryRoute->primary_route_id => $voiceSms->secondaryRoute->primaryRoute->smsc_id];
                    }

                    $queue = VoiceSmsQueue::where('is_auto', '!=', 0)
                    ->where('voice_sms_id', $request->voice_sms_id)
                    ->chunkById(env('VOICE_CHUNK_SIZE', 25000), function ($records) use ($priority,&$count, $associated_routes)
                    {
                        
                        $voiceSendData = [];
                        foreach ($records as $rec) 
                        {
                            $getPRInfo = getRandomSingleArray($associated_routes);
                            $primary_route_id = $getPRInfo['key'];
                            $smsc_id = $getPRInfo['value'];

                            if(!empty($rec->mobile))
                            {
                                $voiceSendData[] = checkVoiceNumberValid($rec->mobile, false);
                            }

                            $count++;
                        }

                        if(count($voiceSendData)>0)
                        {
                            $response = sendVoiceSMSApi($voiceSendData, $secondaryRouteInfo->primaryRoute, $voiceSms);
                            $voiceSms->campaign_id = $response['CAMPG_ID'];
                            $voiceSms->transection_id = @$response['TRANS_ID'];
                            $voiceSms->save();
                        }
                        $voiceSendData = [];
                    }); 

                    // update ratio
                    $voiceSms->ratio_percent_set = 0;
                    $voiceSms->failed_ratio = 0;
                    $voiceSms->save();

                    DB::statement("UPDATE `voice_sms_queues` SET `is_auto`= '0' WHERE `is_auto` != '0' AND `voice_sms_id` = '".$request->voice_sms_id."';");
                }

                return response()->json(prepareResult(false, [], trans('translate.number_of_record_updated').' :'. $count, $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'update_auto_status')
            {
                voiceReUpdatePending($request->voice_sms_id);
                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }

            elseif($request->action_type == 'apply_ratio') 
            {
                $total_answered = 0;
                $result = \DB::select('CALL getVoiceAnsweredCount(?)', [$request->voice_sms_id]);
                foreach ($result as $key => $value) {
                    $total_answered = $value->total_answered;
                }

                $totalSubmits = VoiceSms::select('total_contacts')->find($request->voice_sms_id)->total_contacts;
                $remaining =  $totalSubmits - $total_answered;
                $calPercentage = (int) floor((($totalSubmits * $request->ratio_percent_to_delivered)/100));
                $updateRemaining =  $calPercentage - $total_answered;
                //\Log::info($totalSubmits.'-'.$total_answered.'-'.$remaining.'-'.$calPercentage.'-'.$updateRemaining);
                if($updateRemaining > 0)
                {  
                    DB::statement("UPDATE `voice_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, TIMESTAMP(DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second))),
                    `stat` = 'Answered',
                    `cli` = ".$time.",
                    `flag` = null,
                    `start_time` = COALESCE(start_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 4) second)),
                    `end_time` = COALESCE(end_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 20) second)),
                    `duration` = TIMESTAMPDIFF(SECOND, start_time, end_time),
                    `dtmf` = null,
                    `status` = 'Completed'
                    WHERE `voice_sms_id` = '".$request->voice_sms_id."' AND `stat` NOT IN ('Answered','INVALID','BLOCK') ORDER BY RAND() LIMIT ".$updateRemaining.";");

                    $result = \DB::select('CALL getVoiceAnsweredCount(?)', [$request->voice_sms_id]);
                    foreach ($result as $key => $value) {
                        $total_answered = $value->total_answered;
                    }
                    $stillRemains = $calPercentage - ($total_answered);
                    if($stillRemains > 0)
                    {
                        DB::statement("UPDATE `voice_sms_histories` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                        `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                        `done_date`= COALESCE(done_date, TIMESTAMP(DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second))),
                        `stat` = 'Answered',
                        `cli` = ".$time.",
                        `flag` = null,
                        `start_time` = COALESCE(start_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 4) second)),
                        `end_time` = COALESCE(end_time, DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 20) second)),
                        `duration` = TIMESTAMPDIFF(SECOND, start_time, end_time),
                        `dtmf` = null,
                        `status` = 'Completed'
                        WHERE `voice_sms_id` = '".$request->voice_sms_id."' AND `stat` NOT IN ('Answered','INVALID','BLOCK') ORDER BY RAND() LIMIT ".$stillRemains.";");
                    }
                }
                else
                {
                    //some entries need to set failed
                    $updateRemaining = abs($updateRemaining);
                    $this->recursiveVoiceUpdateRatio($request->voice_sms_id, $updateRemaining, $calPercentage);
                }

                return response()->json(prepareResult(false, [], trans('translate.action_is_successfully_done'), $this->intime), config('httpcodes.success'));
            }
        } catch (\Throwable $e) {
            \Log::error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    private function recursiveVoiceUpdateRatio($voice_sms_id, $updateRemaining, $calPercentage)
    {
        $time = time();
        if($updateRemaining>0)
        {
            DB::statement("UPDATE `voice_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                `done_date`= COALESCE(done_date, TIMESTAMP(DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second))),
                `stat` = 'Failed',
                `cli` = ".$time.",
                `flag` = null,
                `start_time` = null,
                `end_time` = null,
                `duration` = null,
                `dtmf` = null,
                `status` = 'Completed'
            WHERE `voice_sms_id` = '".$voice_sms_id."' AND `stat` NOT IN ('INVALID','BLOCK') ORDER BY RAND() LIMIT ".$updateRemaining.";");

            $total_answered = 0;
            $result = \DB::select('CALL getVoiceAnsweredCount(?)', [$voice_sms_id]);
            foreach ($result as $key => $value) {
                $total_answered = $value->total_answered;
            }

            $stillRemains =  $calPercentage - ($total_answered);
            if($stillRemains < 0)
            {
                $stillRemains = abs($stillRemains);
                DB::statement("UPDATE `voice_sms_histories` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
                    `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
                    `done_date`= COALESCE(done_date, TIMESTAMP(DATE_ADD(submit_date, INTERVAL FLOOR((RAND() * 5) + 22) second))),
                    `stat` = 'Failed',
                    `cli` = ".$time.",
                    `flag` = null,
                    `start_time` = null,
                    `end_time` = null,
                    `duration` = null,
                    `dtmf` = null,
                    `status` = 'Completed'
                WHERE `voice_sms_id` = '".$voice_sms_id."' AND `stat` NOT IN ('INVALID','BLOCK') ORDER BY RAND() LIMIT ".$stillRemains.";");
            }
        }

        $total_answered = 0;
        $result = \DB::select('CALL getVoiceAnsweredCount(?)', [$voice_sms_id]);
        foreach ($result as $key => $value) {
            $total_answered = $value->total_answered;
        }

        $stillRemains =  $calPercentage - ($total_answered);
        if($stillRemains < 0)
        {
            $updateRemaining = abs($stillRemains);
            $this->recursiveUpdateRatio($voice_sms_id, $updateRemaining, $calPercentage);
        }

        return true;
    }
}
