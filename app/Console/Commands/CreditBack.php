<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\SendSms;
use App\Models\DlrcodeVender;
use App\Models\SendSmsQueue;
use App\Models\SendSmsHistory;
use App\Models\VoiceSms;
use App\Models\VoiceSmsQueue;
use App\Models\VoiceSmsHistory;
use Log;

class CreditBack extends Command
{
    protected $signature = 'credit:back';

    protected $description = 'Command description';

    public function handle()
    {
        set_time_limit(0);

        //\Artisan::call('optimize:clear');

        checkApiCampaignComplete();

        $today  = date('Y-m-d');
        $yesterday = date("Y-m-d", strtotime("-1 days", time()));
        $moveIds = [];
        $campaigns = SendSms::select('send_sms.id','send_sms.user_id','send_sms.secondary_route_id','send_sms.dlt_template_id','send_sms.sender_id','send_sms.route_type','send_sms.sms_type','send_sms.message_count','send_sms.message_credit_size','send_sms.is_credit_back','send_sms.self_credit_back','send_sms.parent_credit_back','send_sms.credit_back_date','users.current_parent_id', 'users.authority_type')
            ->whereIn('send_sms.status', ['Ready-to-complete','Completed','Rejected','Stop'])
            ->where('send_sms.is_credit_back', 0)
            ->where('send_sms.campaign', '!=', env('DEFAULT_API_CAMPAIGN_NAME', 'API'))
            ->whereDate('send_sms.campaign_send_date_time', $yesterday)
            ->join('users', 'send_sms.user_id', 'users.id')
            ->with('secondaryRoute:id,primary_route_id')
            ->chunkById(env('CHUNK_SIZE', 1000), function($chunks) 
            {
                foreach($chunks as $sendsms) 
                {
                    // Update accepted records whos get final acknowledgement before submission acknowledgement according to stat code.
                    updateAcceptedToStatusWise($sendsms->id);

                    // Pending to deliver if not yet updated
                    reUpdatePending($sendsms->id, 10000);

                    // update delivery failed count report
                    updateAllTypeStatusReport($sendsms->id);

                    $refundDlrs = DlrcodeVender::where('primary_route_id', $sendsms->secondaryRoute->primary_route_id)
                        ->where('is_refund_applicable', 1)
                        ->pluck('dlr_code')
                        ->toArray();

                    $queue = $sendsms->sendSmsQueues()
                        ->whereIn('err', $refundDlrs)
                        ->sum('use_credit');

                    $history = $sendsms->sendSmsHistories()
                        ->whereIn('err', $refundDlrs)
                        ->sum('use_credit');
                    $totalCreditBack = $queue + $history;
                    
                    // Scurrbing SMS and credit adjustment
                    $getSMSRate = 0;
                    if($totalCreditBack > 0)
                    {
                        //authority_type => 1:onDelivered, 2:onSubmission
                        if($sendsms->authority_type==1)
                        {
                            $user = User::select('id','uuid','name','email','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit')
                                ->find($sendsms->user_id);

                            $getSMSRateFromCredit = \DB::table('credit_logs')
                                ->select('rate')
                                ->where('user_id', $sendsms->user_id)
                                ->where('action_for', $sendsms->route_type)
                                ->whereNotNull('rate')
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($getSMSRateFromCredit)
                            {
                                $getSMSRate = $getSMSRateFromCredit->rate;
                            }
                            $scurrbing_sms_adjustment = smsCreditReverse($getSMSRate, $totalCreditBack);

                            $totalCreditBack = round(($totalCreditBack - $scurrbing_sms_adjustment), 0);

                            creditLog($sendsms->user_id, 1, $sendsms->route_type, 1, $totalCreditBack, null, null, 'Credit Reversed: '.$yesterday, $scurrbing_sms_adjustment);
                            creditAdd($user, $sendsms->route_type, $totalCreditBack);
                            $sendsms->self_credit_back = $totalCreditBack;
                            /////notification and mail//////
                            $variable_data = [
                                '{{name}}' => $user->name,
                                '{{no_of_credit}}' => $totalCreditBack,
                            ];
                            notification('user-credit-reverse', $user, $variable_data);

                            Log::channel('creditback')->error('user-credit-reversed:self, user_id:'.$user->id.' / send_sms_id:'.$sendsms->id);
                        }
                        else
                        {
                            $user = User::select('id','uuid','name','email','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit')
                                ->find($sendsms->current_parent_id);

                            $getSMSRateFromCredit = \DB::table('credit_logs')
                                ->select('rate')
                                ->where('user_id', $sendsms->current_parent_id)
                                ->where('action_for', $sendsms->route_type)
                                ->whereNotNull('rate')
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($getSMSRateFromCredit)
                            {
                                $getSMSRate = $getSMSRateFromCredit->rate;
                            }
                            $scurrbing_sms_adjustment = smsCreditReverse($getSMSRate, $totalCreditBack);

                            $totalCreditBack = round(($totalCreditBack - $scurrbing_sms_adjustment), 0);
                            creditLog($sendsms->current_parent_id, 1, $sendsms->route_type, 1, $totalCreditBack, null, null, 'Credit Reversed: '.$yesterday, $scurrbing_sms_adjustment);
                            creditAdd($user, $sendsms->route_type, $totalCreditBack);
                            $sendsms->parent_credit_back = $totalCreditBack;
                            /////notification and mail//////
                            $variable_data = [
                                '{{name}}' => $user->name,
                                '{{no_of_credit}}' => $totalCreditBack,
                            ];
                            notification('user-credit-reverse', $user, $variable_data);
                            Log::channel('creditback')->error('user-credit-reversed:current_parent, user_id:'.$user->id.' / send_sms_id:'.$sendsms->id);
                        }
                    }

                    //change status
                    $sendsms->is_credit_back = 1;
                    $sendsms->credit_back_date = date('Y-m-d H:i:s');
                    $sendsms->status = 'Completed';
                    $sendsms->save();

                    // now data moved from queue to history table
                    \DB::statement("INSERT send_sms_histories
                    SELECT null, `send_sms_id`, `primary_route_id`, `unique_key`, `mobile`, `message`, `use_credit`, `is_auto`, `stat`, `err`, `status`, `submit_date`, `done_date`, `response_token`, `sub`, `dlvrd`, `created_at`,`updated_at` FROM send_sms_queues WHERE send_sms_id = $sendsms->id;");
                    \DB::statement("DELETE FROM `send_sms_queues` WHERE send_sms_id = $sendsms->id;");
                }                
            });
        
        //total api credit used yesterday
        $getAllUsers = SendSms::select('id','user_id','route_type')
            ->where('send_sms.is_credit_back', 0)
            ->where('send_sms.campaign', env('DEFAULT_API_CAMPAIGN_NAME', 'API'))
            ->whereDate('send_sms.campaign_send_date_time', $yesterday)
            ->groupBy('user_id')
            ->groupBy('route_type')
            ->chunkById(env('CHUNK_SIZE', 1000), function($chunks) use ($yesterday) 
            {
                foreach($chunks as $user)
                {
                    $totalApiCreditUsed = SendSmsQueue::join('send_sms', 'send_sms_queues.send_sms_id', 'send_sms.id')
                        ->where('send_sms.route_type', $user->route_type)
                        ->where('send_sms.user_id', $user->user_id)
                        ->where('send_sms.campaign', env('DEFAULT_API_CAMPAIGN_NAME', 'API'))
                        ->whereDate('send_sms.campaign_send_date_time', $yesterday)
                        ->whereNot('send_sms_queues.err', '<=>', 'XX1')
                        ->sum('send_sms_queues.use_credit');
                    if($totalApiCreditUsed>0)
                    {
                        $userInfo = User::select('id','uuid','name','email','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit','authority_type','current_parent_id')
                            ->find($user->user_id);
                        creditApiLog($user->user_id, 1, $user->route_type, 2, $totalApiCreditUsed, null, null, 'API Credit Used: '.$yesterday);
                        /////notification and mail//////
                        $variable_data = [
                            '{{date}}' => $yesterday,
                            '{{no_of_credit}}' => $totalApiCreditUsed,
                        ];
                        notification('user-api-credit-used', $userInfo, $variable_data);
                    }
                }
            });

        //API Campaign Credit Back
        $moveIds = [];
        $getAllUsers = SendSms::select('id','user_id')
            ->where('send_sms.is_credit_back', 0)
            ->where('send_sms.campaign', env('DEFAULT_API_CAMPAIGN_NAME', 'API'))
            ->whereDate('send_sms.campaign_send_date_time', $yesterday)
            ->groupBy('user_id')
            ->chunkById(env('CHUNK_SIZE', 1000), function($chunks) use ($yesterday) 
            {
                foreach($chunks as $user)
                {
                    $totalCreditBack = 0;
                    $moveIds[] = $user->user_id;
                    $sentMsgRoutes = SendSmsQueue::select('send_sms_queues.primary_route_id', 'send_sms.route_type')
                        ->whereDate('send_sms.campaign_send_date_time', $yesterday)
                        ->join('send_sms', 'send_sms_queues.send_sms_id', 'send_sms.id')
                        ->groupBy('send_sms.route_type')
                        ->groupBy('send_sms_queues.primary_route_id')
                        ->get();
                    foreach($sentMsgRoutes as $route) 
                    {
                        $refundDlrs = DlrcodeVender::where('primary_route_id', $route->primary_route_id)
                            ->where('is_refund_applicable', 1)
                            ->pluck('dlr_code')
                            ->toArray();

                        $queue = SendSmsQueue::join('send_sms', 'send_sms_queues.send_sms_id', 'send_sms.id')
                            ->where('send_sms.user_id', $user->user_id)
                            ->whereDate('send_sms.campaign_send_date_time', $yesterday)
                            ->where('send_sms.route_type', $route->route_type)
                            ->whereIn('send_sms_queues.err', $refundDlrs)
                            ->where('send_sms_queues.primary_route_id', $route->primary_route_id)
                            ->sum('send_sms_queues.use_credit');
                        $totalCreditBack += $queue;
                    }

                    // Scurrbing SMS and credit adjustment
                    $getSMSRate = 0;

                    if($totalCreditBack>0)
                    {
                        //authority_type => 1:onDelivered, 2:onSubmission
                        $userInfo = User::select('id','uuid','name','email','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit','authority_type','current_parent_id')
                            ->find($user->user_id);
                        if($userInfo->authority_type==1)
                        {
                            $getSMSRateFromCredit = \DB::table('credit_logs')
                                ->select('rate')
                                ->where('user_id', $user->user_id)
                                ->where('action_for', $route->route_type)
                                ->whereNotNull('rate')
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($getSMSRateFromCredit)
                            {
                                $getSMSRate = $getSMSRateFromCredit->rate;
                            }
                            $scurrbing_sms_adjustment = smsCreditReverse($getSMSRate, $totalCreditBack);

                            $totalCreditBack = round(($totalCreditBack - $scurrbing_sms_adjustment), 0);

                            creditLog($user->user_id, 1, $route->route_type, 1, $totalCreditBack, null, null, 'Credit Reversed: '.$yesterday, $scurrbing_sms_adjustment);
                            creditAdd($userInfo, $route->route_type, $totalCreditBack);
                            /////notification and mail//////
                            $variable_data = [
                                '{{name}}' => $userInfo->name,
                                '{{no_of_credit}}' => $totalCreditBack,
                            ];
                            notification('user-credit-reverse', $userInfo, $variable_data);

                            Log::channel('creditback')->error('API:user-credit-reversed:self, user_id:'.$user->id);
                        }
                        else
                        {
                            $user = User::select('id','uuid','name','email','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit')
                                ->find($userInfo->current_parent_id);

                            $getSMSRateFromCredit = \DB::table('credit_logs')
                                ->select('rate')
                                ->where('user_id', $userInfo->current_parent_id)
                                ->where('action_for', $route->route_type)
                                ->whereNotNull('rate')
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($getSMSRateFromCredit)
                            {
                                $getSMSRate = $getSMSRateFromCredit->rate;
                            }
                            $scurrbing_sms_adjustment = smsCreditReverse($getSMSRate, $totalCreditBack);

                            $totalCreditBack = round(($totalCreditBack - $scurrbing_sms_adjustment), 0);

                            creditLog($userInfo->current_parent_id, 1, $route->route_type, 1, $totalCreditBack, null, null, 'Credit Reversed: '.$yesterday, $scurrbing_sms_adjustment);
                            creditAdd($userInfo, $route->route_type, $totalCreditBack);
                            /////notification and mail//////
                            $variable_data = [
                                '{{name}}' => $user->name,
                                '{{no_of_credit}}' => $totalCreditBack,
                            ];
                            notification('user-credit-reverse', $user, $variable_data);
                            Log::channel('creditback')->error('API:user-credit-reversed:current_parent, user_id:'.$user->id);
                        }
                    }
                }

                //update refund status
                $imploded = implode(',', $moveIds);
                $moveIds = [];
                \DB::statement("UPDATE `send_sms` SET `is_credit_back`= '1', 
                `credit_back_date`= now()  
                WHERE DATE(`campaign_send_date_time`)= '".$yesterday."' AND user_id in ($imploded);");

                //copy and delete records
                \DB::statement("INSERT send_sms_histories
                    SELECT null, `send_sms_id`, `primary_route_id`, `unique_key`, `mobile`, `message`, `use_credit`, `is_auto`, `stat`, `err`, `status`, `submit_date`, `done_date`, `response_token`, `sub`, `dlvrd`, `created_at`,`updated_at` FROM send_sms_queues WHERE DATE(`created_at`) = '".$yesterday."';");
                \DB::statement("DELETE FROM `send_sms_queues` WHERE DATE(`created_at`) = '".$yesterday."';");
            });

        /*-------------------------------------------------*/
        // Voice SMS

        $moveIds = [];
        $campaigns = VoiceSms::select('voice_sms.id','voice_sms.user_id','voice_sms.secondary_route_id','voice_sms.campaign_id','voice_sms.transection_id','voice_sms.campaign','voice_sms.obd_type','voice_sms.dtmf','voice_sms.call_patch_number','voice_sms.voice_upload_id','voice_sms.voice_id','voice_sms.voice_file_path','voice_sms.message_credit_size','voice_sms.is_credit_back','voice_sms.self_credit_back','voice_sms.parent_credit_back','voice_sms.credit_back_date','users.current_parent_id', 'users.authority_type')
            ->whereIn('voice_sms.status', ['Ready-to-complete','Completed','Rejected','Stop'])
            ->where('voice_sms.is_credit_back', 0)
            ->where('voice_sms.campaign', '!=', env('DEFAULT_API_CAMPAIGN_NAME', 'API'))
            ->whereDate('voice_sms.campaign_send_date_time', $yesterday)
            ->join('users', 'voice_sms.user_id', 'users.id')
            ->with('secondaryRoute:id,primary_route_id')
            ->chunkById(env('CHUNK_SIZE', 1000), function($chunks) 
            {
                foreach($chunks as $voicesms) 
                {
                    // Pending to deliver if not yet updated
                    voiceReUpdatePending($voicesms->id, 10000);

                    // update delivery failed count report
                    updateAllTypeVoiceStatusReport($voicesms->id);

                    $refundDlrs = DlrcodeVender::where('primary_route_id', $voicesms->secondaryRoute->primary_route_id)
                        ->where('is_refund_applicable', 1)
                        ->pluck('dlr_code')
                        ->toArray();

                    $queue = $voicesms->voiceSmsQueues()
                        ->whereIn('err', $refundDlrs)
                        ->sum('use_credit');

                    $history = $voicesms->voiceSmsHistories()
                        ->whereIn('err', $refundDlrs)
                        ->sum('use_credit');
                    $totalCreditBack = $queue + $history;
                    
                    // Scurrbing SMS and credit adjustment
                    $getSMSRate = 0;
                    if($totalCreditBack > 0)
                    {
                        //authority_type => 1:onDelivered, 2:onSubmission
                        if($voicesms->authority_type==1)
                        {
                            $user = User::select('id','uuid','name','email','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit')
                                ->find($voicesms->user_id);

                            $getSMSRateFromCredit = \DB::table('credit_logs')
                                ->select('rate')
                                ->where('user_id', $voicesms->user_id)
                                ->where('action_for', $voicesms->route_type)
                                ->whereNotNull('rate')
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($getSMSRateFromCredit)
                            {
                                $getSMSRate = $getSMSRateFromCredit->rate;
                            }
                            $scurrbing_sms_adjustment = smsCreditReverse($getSMSRate, $totalCreditBack);

                            $totalCreditBack = round(($totalCreditBack - $scurrbing_sms_adjustment), 0);

                            creditLog($voicesms->user_id, 1, $voicesms->route_type, 1, $totalCreditBack, null, null, 'Credit Reversed: '.$yesterday, $scurrbing_sms_adjustment);
                            creditAdd($user, $voicesms->route_type, $totalCreditBack);
                            $voicesms->self_credit_back = $totalCreditBack;
                            /////notification and mail//////
                            $variable_data = [
                                '{{name}}' => $user->name,
                                '{{no_of_credit}}' => $totalCreditBack,
                            ];
                            notification('user-credit-reverse', $user, $variable_data);

                            Log::channel('creditback')->error('user-credit-reversed:self, user_id:'.$user->id.' / send_sms_id:'.$voicesms->id);
                        }
                        else
                        {
                            $user = User::select('id','uuid','name','email','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit')
                                ->find($voicesms->current_parent_id);

                            $getSMSRateFromCredit = \DB::table('credit_logs')
                                ->select('rate')
                                ->where('user_id', $voicesms->current_parent_id)
                                ->where('action_for', $voicesms->route_type)
                                ->whereNotNull('rate')
                                ->orderBy('id', 'DESC')
                                ->first();
                            if($getSMSRateFromCredit)
                            {
                                $getSMSRate = $getSMSRateFromCredit->rate;
                            }
                            $scurrbing_sms_adjustment = smsCreditReverse($getSMSRate, $totalCreditBack);

                            $totalCreditBack = round(($totalCreditBack - $scurrbing_sms_adjustment), 0);
                            creditLog($voicesms->current_parent_id, 1, $voicesms->route_type, 1, $totalCreditBack, null, null, 'Credit Reversed: '.$yesterday, $scurrbing_sms_adjustment);
                            creditAdd($user, $voicesms->route_type, $totalCreditBack);
                            $voicesms->parent_credit_back = $totalCreditBack;
                            /////notification and mail//////
                            $variable_data = [
                                '{{name}}' => $user->name,
                                '{{no_of_credit}}' => $totalCreditBack,
                            ];
                            notification('user-credit-reverse', $user, $variable_data);
                            Log::channel('creditback')->error('user-credit-reversed:current_parent, user_id:'.$user->id.' / send_sms_id:'.$voicesms->id);
                        }
                    }

                    //change status
                    $voicesms->is_credit_back = 1;
                    $voicesms->credit_back_date = date('Y-m-d H:i:s');
                    $voicesms->status = 'Completed';
                    $voicesms->save();

                    // now data moved from queue to history table
                    \DB::statement("INSERT voice_sms_histories
                    SELECT null, `voice_sms_id`, `primary_route_id`, `unique_key`, `mobile`, `message`, `use_credit`, `is_auto`, `stat`, `err`, `status`, `submit_date`, `done_date`, `response_token`, `sub`, `dlvrd`, `created_at`,`updated_at` FROM voice_sms_queues WHERE voice_sms_id = $voicesms->id;");
                    \DB::statement("DELETE FROM `voice_sms_queues` WHERE voice_sms_id = $voicesms->id;");
                }                
            });
       
        return;

    }
}
