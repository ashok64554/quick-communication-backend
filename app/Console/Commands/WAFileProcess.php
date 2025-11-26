<?php

namespace App\Console\Commands;

ini_set('memory_limit', '-1');

use Illuminate\Console\Command;
use App\Models\WhatsAppBatch;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateButton;
use App\Jobs\SendWhatsAppMessageJob;

class WAFileProcess extends Command
{
    protected $signature = 'wafile:process';

    protected $description = 'Command description';

    public function handle()
    {
        set_time_limit(0);

        $actDir = 'public/';
        $currentTime = date("Y-m-d H:i:s");
        $countBlankRow = 0;

        $getBatches = WhatsAppBatch::where('execute_time', '<=', $currentTime)
            ->orderBy('priority', 'DESC')
            ->get();

        foreach ($getBatches as $key => $getBatche) 
        {
            if($getBatche->current_status=='2')
            {
                continue;
            }
            $getBatche->current_status = '2';
            $getBatche->save();

            $updateCampaignStatus = \DB::table('whats_app_send_sms')
                ->where('id', $getBatche->whats_app_send_sms_id)
                ->update([
                    'status' => 'Ready-to-complete'
                ]);
            
            $submitMsgs = WhatsAppSendSmsQueue::select('whats_app_send_sms_queues.id','whats_app_send_sms_queues.unique_key','whats_app_send_sms_queues.mobile','whats_app_send_sms_queues.submit_date','whats_app_send_sms_queues.whats_app_send_sms_id','whats_app_send_sms_queues.message','whats_app_send_sms.whats_app_configuration_id','whats_app_send_sms.whats_app_template_id','whats_app_send_sms.sender_number','whats_app_configurations.access_token','whats_app_configurations.app_version','whats_app_templates.template_language','whats_app_templates.template_name')
                ->join('whats_app_send_sms', 'whats_app_send_sms_queues.whats_app_send_sms_id', 'whats_app_send_sms.id')
                ->join('whats_app_configurations', 'whats_app_send_sms.whats_app_configuration_id', 'whats_app_configurations.id')
                ->join('whats_app_templates', 'whats_app_send_sms.whats_app_template_id', 'whats_app_templates.id')
                ->where('batch_id', $getBatche->batch)
                ->where('is_auto', 0)
                ->where('stat', '!=', 'INVALID')
                ->where('stat', 'Pending')
                ->get();
                foreach ($submitMsgs as $key => $submitMsg) 
                {
                    $template_name = $submitMsg->template_name; 
                    $sender_number = $submitMsg->sender_number; 
                    $appVersion = $submitMsg->app_version;
                    $message = json_decode($submitMsg->message);
                    $access_token = base64_decode($submitMsg->access_token); 
                    $response = wAMessageSend($access_token, $sender_number, $appVersion, $template_name, $message);
                    \Log::channel('whatsapp')->info($response);
                    
                    if($response['error']==false)
                    {
                        $response = json_decode($response['response']);
                        //update response
                        $submitMsg->submit_date = date('Y-m-d H:i:s');
                        $submitMsg->stat = @$response->messages[0]->message_status;
                        $submitMsg->response_token = @$response->messages[0]->id;
                        $submitMsg->save();
                    }
                    else
                    {
                        $submitMsg->error_info = $response;
                        $submitMsg->submit_date = date('Y-m-d H:i:s');
                        $submitMsg->stat = 'Failed';
                        $submitMsg->status = 'Completed';
                        $submitMsg->save();
                    }
                    
                }

                // detele batch id after executing all numbers
                $getBatche->delete();
            }

        return Command::SUCCESS;
    }
}
