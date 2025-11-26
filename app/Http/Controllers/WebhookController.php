<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use DB;
use Mail;
use Excel;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\WhatsAppReplyThread;
use App\Models\WhatsAppChatBot;
use App\Models\WhatsAppChatBotSession;
use App\Models\WhatsAppConfiguration;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Cache;
use App\Helpers\ChatBotEngine;

class WebhookController extends Controller
{
    protected $intime;
    protected $api_key;
    protected $client_id;
    protected $client_secret;
    protected $url;

    public function __construct()
    {
        $this->intime = \Carbon\Carbon::now();
    }

    public function voiceWebhook(Request $request)
    {
        try {
            \Log::channel('webhook')->info($request->all());
            return response()->json(prepareResult(false, $request->all(), trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
        } catch (\Throwable $e) {
            \Log::channel('webhook')->error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function configureWaWebhook(Request $request)
    {
        try {
            $mode = $request->hub_mode;
            $challenge = $request->hub_challenge;
            $token = $request->hub_verify_token;
            echo $challenge;
            die;
            if($token==env('VERIFY_TOKEN', 'ashok64554'))
            {
                echo $challenge;
                //return response()->json(prepareResult(false, $request->all(), trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, ['Not connected'], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.bad_request'));
            
        } catch (\Throwable $e) {
            \Log::channel('webhook')->error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function responseWaWebhook(Request $request)
    {

        // if you set "Direct" then please comment kernal.php waupdate:dlr command otherwise uncomment for automation
        /*
            $schedule->command('waupdate:dlr')
                ->everyMinute()
                ->timezone(env('TIME_ZONE', 'Asia/Calcutta'));
        */
        \Log::channel('webhook')->info('All Responses from webhook');
        \Log::channel('webhook')->info(json_encode($request->all()));
        $default = 'Direct';
        try {

            if($default=='Direct')
            {
                // Start Direct Approach
                $json_decode = $request->all();
                $getStatus = $json_decode['entry'][0]['changes'][0]['value'];
                $getStatusField = $json_decode['entry'][0]['changes'][0]['field'];

                $wa_failed_responses = [];
                $wa_sent_responses = [];
                $wa_delivered_responses = [];
                $wa_read_responses = [];
                $wa_reply_threads = [];

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

                            WhatsAppSendSmsQueue::massUpdate(
                                values: $wa_failed_responses,
                                uniqueBy: 'response_token'
                            );

                            WhatsAppReplyThread::massUpdate(
                                values: $wa_failed_reply_responses,
                                uniqueBy: 'response_token'
                            );

                            break;
                        case 'sent':
                            \Log::info('sent data-whatsapp');
                            \Log::info($getFinalStatus);

                            $conversation_id = $getFinalStatus['conversation']['id'] ?? null;
                            $expiration_timestamp = $getFinalStatus['conversation']['expiration_timestamp'] ?? null;
                            $status = $getFinalStatus['status'] ?? null;
                            $timestamp = $getFinalStatus['timestamp'] ?? null;
                            $meta_billable = $getFinalStatus['pricing']['billable'] ?? null;
                            $meta_pricing_model = $getFinalStatus['pricing']['pricing_model'] ?? null;
                            $meta_billing_category = $getFinalStatus['pricing']['category'] ?? null;


                            $wa_sent_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'conversation_id' => $conversation_id,
                                'expiration_timestamp' => (!empty($expiration_timestamp)) ? date('Y-m-d H:i:s', date($expiration_timestamp)) : null,
                                'stat' => $status,
                                'status' => 'Completed',
                                'sent' => 1,
                                'sent_date_time' => (!empty($timestamp)) ? date('Y-m-d H:i:s', date($timestamp)) :  null,
                                'meta_billable' => $meta_billable,
                                'meta_pricing_model' => $meta_pricing_model,
                                'meta_billing_category' => $meta_billing_category,
                            ];
                            
                            WhatsAppSendSmsQueue::massUpdate(
                                values: $wa_sent_responses,
                                uniqueBy: 'response_token'
                            );

                            break;
                        case 'delivered':
                            $wa_delivered_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'stat' => $getFinalStatus['status'],
                                'delivered' => 1,
                                'delivered_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                            ];
                            
                            WhatsAppSendSmsQueue::massUpdate(
                                values: $wa_delivered_responses,
                                uniqueBy: 'response_token'
                            );

                            break;
                        case 'read':
                            $wa_read_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'stat' => $getFinalStatus['status'],
                                'read' => 1,
                                'read_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                            ];
                            
                            WhatsAppSendSmsQueue::massUpdate(
                                values: $wa_read_responses,
                                uniqueBy: 'response_token'
                            );

                            break;
                        case 'payment':
                            $wa_read_responses[] = [
                                'response_token' => $getFinalStatus['id'],
                                'stat' => $getFinalStatus['status'],
                                'read' => 1,
                                'read_date_time' => date('Y-m-d H:i:s', date($getFinalStatus['timestamp'])),
                            ];

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
                    /* BOT CODE */
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

                    executeWAReplyThreds($wa_reply_threads);
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
                }
                else
                {
                    \Log::info('whatsapp_response_from_webhook log else');
                    \Log::info('json_decode');
                    \Log::info(@$json_decode);
                    \Log::info('value');
                    \Log::info(@$value);
                    //$redis->lrem('whatsapp_key',1, $value);
                }
                // End Direct Approach
            }
            else
            {
                // Start Redis Approach
                $redis = Redis::connection();
                $redis->rpush('whatsapp_key', json_encode($request->all()));
                // End Redis Approach
            }
            
            return response()->json(prepareResult(false, $request->all(), trans('translate.created'), $this->intime), config('httpcodes.created'));
        } catch (\Predis\Connection\ConnectionException $e) {
            \Log::channel('webhook')->error('error connection redis');
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function smsWebhookResponse(Request $request)
    {
        \Log::channel('callbackwebhook')->info('Text SMS Response after trigger');
        \Log::channel('callbackwebhook')->info($request->all());

        return response()->json(prepareResult(false, true, 'data captured', $this->intime), config('httpcodes.success'));
    }

    public function configureWaPartnerWebhook(Request $request)
    {
        try {
            $mode = $request->hub_mode;
            $challenge = $request->hub_challenge;
            $token = $request->hub_verify_token;
            echo $challenge;
            die;
            if($token==env('VERIFY_TOKEN', 'ashok64554'))
            {
                echo $challenge;
                //return response()->json(prepareResult(false, $request->all(), trans('translate.fetched_records'), $this->intime), config('httpcodes.success'));
            }
            return response()->json(prepareResult(true, ['Not connected'], trans('translate.something_went_wrong'), $this->intime), config('httpcodes.bad_request'));
            
        } catch (\Throwable $e) {
            \Log::channel('webhook')->error($e);
            return response()->json(prepareResult(true, $e->getMessage(), trans('translate.something_went_wrong'), $this->intime), config('httpcodes.internal_server_error'));
        }
    }

    public function responseWaPartnerWebhook(Request $request)
    {
        \Log::channel('callbackwebhook')->info('WA Partner Response after trigger');
        \Log::channel('callbackwebhook')->info($request->all());

        return response()->json(prepareResult(false, true, 'data captured', $this->intime), config('httpcodes.success'));
    }

    /* Chatbot: Testing purpose */
    public function checkWaChatbot(Request $request)
    {
        $text = $request->message;
        $from = $request->customer_number;
        $display_phone_number = $request->display_phone_number;
        $getConfId = DB::table('whats_app_configurations')->select('id','sender_number','app_version','access_token')->where('display_phone_number', $display_phone_number)->first();
        if(!$getConfId)
        {
            return 'No configuration found';
        }
        $configId = $getConfId->id;
        $engine = new ChatbotEngine($getConfId);
        $response = $engine->handleIncomingMessage($from, $text, $configId);

        return response()->json(['status' => 'ok']);
    }

    /* Chatbot: webhook Testing purpose */
    public function testingWebhook(Request $request)
    {
        \Log::channel('whatsapp_bot')->info('Testing Webhook response');
        \Log::channel('whatsapp_bot')->info($request->all());
        $response = [
            'name' => 'Ashok BOT',
            'booking_info' => [
                'status'=> 'Booked',
                'date' => '2025-10-12',
                'ticket_no' => 'ABC123'
            ],
            'product_info' => [
                'name'=> 'Apple',
                'price' => '100.00'
            ],
            'lead_status' => [
                'status' => 'active'
            ],
            'support' => [
                'agent' => 'Ashok Sahu',
                'call' => '919713753131',
                'address' => 'Bhopal, India'
            ]
        ];
        return $response;

        //return response()->json(prepareResult(false, true, $response, $this->intime), config('httpcodes.success'));
    }
}
