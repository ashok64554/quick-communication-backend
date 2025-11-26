<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\WhatsAppReplyThread;
use App\Helpers\ChatBotEngine;
use Cache;

class WAUpdateDlr extends Command
{
    protected $signature = 'waupdate:dlr';

    protected $description = 'Command description';

    public function handle()
    {
        // Check Redis connection
        try {
            $redis = Redis::connection();
        } catch(\Predis\Connection\ConnectionException $e){
            \Log::error('error connection redis');
            die;
        }

        $this->updateWADlr($redis);
    }

    public function updateWADlr($redis)
    {
        $redisConn = $redis;
        $arList = $redis->lrange("whatsapp_key", 0 ,1000);
        if(sizeof($arList)>0)
        {
            $wa_failed_responses = [];
            $wa_failed_reply_responses = [];
            $wa_sent_responses = [];
            $wa_delivered_responses = [];
            $wa_read_responses = [];
            $wa_reply_threads = [];
            foreach ($arList as $key => $value) 
            {
                $json_decode = json_decode($value, true);
                $getStatus = $json_decode['entry'][0]['changes'][0]['value'];
                $getStatusField = $json_decode['entry'][0]['changes'][0]['field'];

                if(array_key_exists('statuses', $getStatus))
                {
                    $getFinalStatus = $getStatus['statuses'][0];
                    switch (strtolower($getFinalStatus['status']))
                    {
                        case 'failed':
                            $wa_failed_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'stat' => $getFinalStatus['status'],
                                'status' => 'Completed',
                                'sent' => 1,
                                'sent_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                                'error_info' => json_encode($getFinalStatus['errors'])
                            ];

                            $wa_failed_reply_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'error_info' => json_encode($getFinalStatus['errors'])
                            ];

                            $redis->lrem('whatsapp_key',1, $value);
                            break;
                        case 'sent':
                            $wa_sent_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'conversation_id' => $getFinalStatus['conversation']['id'],
                                'expiration_timestamp' => date('Y-m-d H:i:s', date($getFinalStatus['conversation']['expiration_timestamp'])),
                                'stat' => $getFinalStatus['status'],
                                'status' => 'Completed',
                                'sent' => 1,
                                'sent_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                                'meta_billable' => $getFinalStatus['pricing']['billable'],
                                'meta_pricing_model' => $getFinalStatus['pricing']['pricing_model'],
                                'meta_billing_category' => $getFinalStatus['pricing']['category'],
                            ];
                            $redis->lrem('whatsapp_key',1, $value);
                            break;
                        case 'delivered':
                            $wa_delivered_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'stat' => $getFinalStatus['status'],
                                'delivered' => 1,
                                'delivered_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                            ];
                            $redis->lrem('whatsapp_key',1, $value);
                            break;
                        case 'read':
                            $wa_read_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'stat' => $getFinalStatus['status'],
                                'read' => 1,
                                'read_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                            ];
                            $redis->lrem('whatsapp_key',1, $value);
                            break;
                        case 'payment':
                            $wa_read_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'stat' => $getFinalStatus['status'],
                                'read' => 1,
                                'read_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                            ];
                            $redis->lrem('whatsapp_key',1, $value);
                            break;
                        default:
                            // code...
                            break;
                    }
                }
                elseif(array_key_exists('messages', $getStatus))
                {
                    $user_mobile = $getStatus['metadata']['phone_number_id'];
                    $phone_number_id = $getStatus['messages'][0]['from'];

                    if(array_key_exists('context', $getStatus['messages'][0]))
                    {
                        $getMessageInfo = \DB::table('whats_app_send_sms_queues')
                            ->select('id', 'unique_key', 'user_id', 'whats_app_send_sms_id')
                            ->where('response_token', @$getStatus['messages'][0]['context']['id'])
                            ->first();
                    }
                    else
                    {
                        $getMessageInfo = \DB::table('whats_app_send_sms_queues')
                            ->select('id', 'unique_key', 'user_id', 'whats_app_send_sms_id')
                            ->where('sender_number', $user_mobile)
                            ->where('mobile', $phone_number_id)
                            ->orderBy('id', 'DESC')
                            ->first();
                    }

                    $message_type = $getStatus['messages'][0]['type'];
                    $media_id = null;
                    $mime_type = null;

                    if($message_type == 'text')
                    {
                        $message = @$getStatus['messages'][0]['text']['body'];
                    }

                    elseif($message_type == 'reaction')
                    {
                        $message = @$getStatus['messages'][0]['reaction']['emoji'];
                    }
                    elseif($message_type == 'unknown')
                    {
                        $message = 'Unsupported message type Received.';
                    }
                    elseif($message_type == 'contacts' || $message_type == 'location' || $message_type == 'button' || $message_type == 'order' || $message_type == 'interactive')
                    {
                        $message = json_encode(@$getStatus['messages'][0][$message_type]);
                    }
                    else
                    {
                        $media_id = @$getStatus['messages'][0][$message_type]['id'];
                        $mime_type = @$getStatus['messages'][0][$message_type]['mime_type'];
                        $message = @$getStatus['messages'][0][$message_type]['caption'];
                    }

                    $wa_user_id = null;
                    if(!$getMessageInfo)
                    {
                        $getConfUserId = \DB::table('whats_app_configurations')
                        ->select('user_id')
                        ->where('display_phone_number_req', $phone_number_id)
                        ->first();
                        $wa_user_id = @$getConfUserId->user_id;
                    }
                    /*
                        - contacts - json response save in message column
                        - location - json response save in message column
                        - button - json response save in message column
                        - order - json response save in message column
                        - interactive - json response save in message column (like flow reply)
                    */

                    $wa_reply_threads[] = [
                        'queue_history_unique_key' => ($getMessageInfo) ? $getMessageInfo->unique_key : null,
                        'whats_app_send_sms_id' => ($getMessageInfo) ? $getMessageInfo->whats_app_send_sms_id : null,
                        'user_id' => ($getMessageInfo) ? $getMessageInfo->user_id : $wa_user_id,
                        'profile_name' => @$getStatus['contacts'][0]['profile']['name'],
                        'phone_number_id' => $phone_number_id,
                        'display_phone_number' => $getStatus['metadata']['display_phone_number'],
                        'user_mobile' => $user_mobile,
                        'message_type' => $message_type,
                        'message' => $message,
                        'json_message' => json_encode($getStatus['messages'][0]),
                        'media_id' => $media_id,
                        'mime_type' => $mime_type,
                        'context_ref_wa_id' => array_key_exists('context', $getStatus['messages'][0]) ? @$getStatus['messages'][0]['context']['id'] : null,
                        'error_info' => null,
                        'received_date' => date('Y-m-d H:i:s', date($getStatus['messages'][0]['timestamp'])),
                        'response_token' => $getStatus['messages'][0]['id'],
                        'use_credit' => null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];

                    /****************************************/
                    /* BOT CODE ADDED FOR TESTING*/
                    $getConfId = DB::table('whats_app_configurations')->select('id','sender_number','app_version','access_token')->where('display_phone_number', @$getStatus['metadata']['display_phone_number'])->first();
                    if($getConfId)
                    {
                        $from = $phone_number_id;
                        $text = $message;
                        $configId = $getConfId->id;
                        if($message_type == 'interactive')
                        {
                            $text = @$getStatus['messages'][0]['interactive']['button_reply']['title'];
                            //\Log::info($text);
                        }

                        if(!empty($text))
                        {
                            $engine = new ChatbotEngine($getConfId);
                            $response = $engine->handleIncomingMessage($from, $text, $configId);
                        }
                    }
                    
                    /****************************************/

                    $redis->lrem('whatsapp_key',1, $value);
                }
                elseif(array_key_exists('event', $getStatus))
                {
                    if($getStatusField=='message_template_status_update')
                    {
                        $message_template_id = @$getStatus['message_template_id'];
                        $status = $getStatus['event'];
                        \DB::table('whats_app_templates')
                        ->where('wa_template_id', $message_template_id)
                        ->update([
                            'wa_status' => $status,
                            'status' => (($status=='APPROVED') ? '1' : '3')
                        ]);
                    }
                    elseif($getStatusField=='phone_number_quality_update')
                    {
                        $display_phone_number = @$getStatus['display_phone_number'];
                        $current_limit = $getStatus['current_limit'];
                        \DB::table('whats_app_configurations')
                        ->where('display_phone_number_req', $display_phone_number)
                        ->update([
                            'current_limit' => $current_limit,
                        ]);
                    }
                    else
                    {
                        \Log::info('whatsapp_response_from_webhook log else');
                        \Log::info('json_decode');
                        \Log::info(@$json_decode);
                        \Log::info('value');
                        \Log::info(@$value);
                    }
                    $redis->lrem('whatsapp_key',1, $value);
                }
                elseif(array_key_exists('display_phone_number', $getStatus))
                {
                    if($getStatusField=='phone_number_quality_update')
                    {
                        $display_phone_number = @$getStatus['display_phone_number'];
                        $current_limit = $getStatus['current_limit'];
                        \DB::table('whats_app_configurations')
                        ->where('display_phone_number_req', $display_phone_number)
                        ->update([
                            'current_limit' => $current_limit,
                        ]);
                    }
                    else
                    {
                        $display_phone_number = @$getStatus['display_phone_number'];
                        $decision = $getStatus['decision'];
                        \DB::table('whats_app_configurations')
                        ->where('display_phone_number_req', $display_phone_number)
                        ->update([
                            'platform_type' => $decision,
                        ]);
                    }

                    \Log::info('display_phone_number log all');
                    \Log::info('json_decode');
                    \Log::info(@$json_decode);
                    \Log::info('value');
                    \Log::info(@$value);

                    $redis->lrem('whatsapp_key',1, $value);
                }
                else
                {
                    \Log::info('whatsapp_response_from_webhook log else');
                    \Log::info('json_decode');
                    \Log::info(@$json_decode);
                    \Log::info('value');
                    \Log::info(@$value);
                    $redis->lrem('whatsapp_key',1, $value);
                }
            }

            if(sizeof($wa_failed_responses)>0)
            {
                WhatsAppSendSmsQueue::massUpdate(
                    values: $wa_failed_responses,
                    uniqueBy: 'response_token'
                );

                WhatsAppReplyThread::massUpdate(
                    values: $wa_failed_reply_responses,
                    uniqueBy: 'response_token'
                );
            }

            if(sizeof($wa_sent_responses)>0)
            {
                WhatsAppSendSmsQueue::massUpdate(
                    values: $wa_sent_responses,
                    uniqueBy: 'response_token'
                );
            }

            if(sizeof($wa_delivered_responses)>0)
            {
                WhatsAppSendSmsQueue::massUpdate(
                    values: $wa_delivered_responses,
                    uniqueBy: 'response_token'
                );
            }

            if(sizeof($wa_read_responses)>0)
            {
                WhatsAppSendSmsQueue::massUpdate(
                    values: $wa_read_responses,
                    uniqueBy: 'response_token'
                );
            }

            if(sizeof($wa_reply_threads)>0)
            {
                executeWAReplyThreds($wa_reply_threads);
            }
            
            sleep(2);
            $arList = $redis->lrange("whatsapp_key", 0, 1);
            if(sizeof($arList)>0)
            {
                $this->updateWADlr($redisConn);
            }
        }
        return true;
    }
}
