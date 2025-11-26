<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\WhatsAppSendSmsHistory;
use Log;

class WACreditBack extends Command
{
    protected $signature = 'wacredit:back';

    protected $description = 'Command description';

    public function handle()
    {
        set_time_limit(0);

        checkWaApiCampaignComplete();

        $today  = date('Y-m-d');
        $yesterday = date("Y-m-d", strtotime("-1 days", time()));
        $route_type = 5;
        
        //total api credit used yesterday (only READ & DELIVERED)
        $getAllUsers = WhatsAppSendSms::select('id','user_id','message_category')
            ->where('whats_app_send_sms.is_credit_back', 0)
            ->where('whats_app_send_sms.campaign', env('DEFAULT_API_CAMPAIGN_NAME', 'API'))
            ->whereDate('whats_app_send_sms.campaign_send_date_time', $yesterday)
            ->groupBy('user_id')
            ->groupBy('message_category')
            ->chunkById(env('CHUNK_SIZE', 1000), function($chunks) use ($route_type, $yesterday) 
            {
                foreach($chunks as $user)
                {
                    $totalApiCreditUsed = WhatsAppSendSmsQueue::join('whats_app_send_sms', 'whats_app_send_sms_queues.whats_app_send_sms_id', 'whats_app_send_sms.id')
                        ->where('whats_app_send_sms.message_category', $user->message_category)
                        ->where('whats_app_send_sms.user_id', $user->user_id)
                        ->where('whats_app_send_sms.campaign', env('DEFAULT_API_CAMPAIGN_NAME', 'API'))
                        ->whereDate('whats_app_send_sms.campaign_send_date_time', $yesterday)
                        ->whereIn('whats_app_send_sms_queues.stat', ['read', 'delivered'])
                        ->sum('whats_app_send_sms_queues.use_credit');
                    if($totalApiCreditUsed>0)
                    {
                        $userInfo = User::select('id','uuid','name','email','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit','whatsapp_credit','authority_type','current_parent_id')
                            ->find($user->user_id);
                        creditApiLog($user->user_id, 1, $route_type, 2, $totalApiCreditUsed, null, null, 'API Credit Used: '.$yesterday);
                        /////notification and mail//////
                        $variable_data = [
                            '{{date}}' => $yesterday,
                            '{{no_of_credit}}' => $totalApiCreditUsed.'/-',
                        ];
                        notification('user-api-credit-used', $userInfo, $variable_data);
                    }
                }
            });


        //Campaign & API Credit Back
        $moveIds = [];
        $getAllUsers = WhatsAppSendSms::select('id','user_id')
            ->where('is_credit_back', 0)
            ->whereDate('campaign_send_date_time', $yesterday)
            ->groupBy('user_id')
            ->chunkById(env('CHUNK_SIZE', 1000), function($chunks) use ($route_type, $yesterday)  
            {
                foreach($chunks as $user)
                {
                    $userId = $user->user_id;
                    $totalCreditBack = 0;
                    $moveIds[] = $user->user_id;
                    $totalCreditBack = \DB::table('whats_app_send_sms_queues')
                        ->join('whats_app_send_sms', 'whats_app_send_sms_queues.whats_app_send_sms_id', 'whats_app_send_sms.id')
                        ->where('whats_app_send_sms.user_id', $user->user_id)
                        ->where('whats_app_send_sms_queues.stat', 'failed')
                        ->whereDate('whats_app_send_sms.campaign_send_date_time', $yesterday)
                        ->sum('whats_app_send_sms_queues.use_credit');

                    if($totalCreditBack>0)
                    {
                        $user = User::select('id','uuid','name','email','promotional_credit','transaction_credit','two_waysms_credit','voice_sms_credit','whatsapp_credit','authority_type','current_parent_id')
                            ->find($userId);

                        creditLog($userId, 1, $route_type, 1, $totalCreditBack, null, null, 'Whatsapp Credit Reversed: '.$yesterday, 0);
                        creditAdd($user, $route_type, $totalCreditBack);

                        /////notification and mail//////
                        $variable_data = [
                            '{{date}}' => $yesterday,
                            '{{user_id}}' => $userId,
                            '{{name}}' => $user->name,
                            '{{amount_refund}}' => $totalCreditBack,
                        ];
                        \Log::info($variable_data);
                        notification('wa-user-credit-reverse', $user, $variable_data);

                        Log::channel('creditback')->error('user-credit-reversed:self, user_id:'.$user->id.' / total_credit_back:'.$totalCreditBack);
                    }
                }

                //update refund status
                $imploded = implode(',', $moveIds);
                $moveIds = [];
                \DB::statement("UPDATE `whats_app_send_sms` SET `is_credit_back`= '1', 
                `credit_back_date`= now()  
                WHERE DATE(`campaign_send_date_time`)= '".$yesterday."' AND user_id in ($imploded);");

                //copy and delete records
                \DB::statement("INSERT whats_app_send_sms_histories
                    SELECT null, `whats_app_send_sms_id`, `user_id`, `batch_id`, `unique_key`, `sender_number`, `mobile`, `template_category`, `message`, `use_credit`, `is_auto`, `stat`, `status`, `error_info`, `submit_date`, `response_token`, `conversation_id`, `expiration_timestamp`, `sent`, `sent_date_time`, `delivered`, `delivered_date_time`, `read`, `read_date_time`, `meta_billable`, `meta_pricing_model`, `meta_billing_category`, `created_at`,`updated_at` FROM whats_app_send_sms_queues WHERE DATE(`created_at`) = '".$yesterday."';");

                \DB::statement("DELETE FROM `whats_app_send_sms_queues` WHERE DATE(`created_at`) = '".$yesterday."';");
            });
        return;

    }
}
