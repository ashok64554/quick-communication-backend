<?php

namespace App\Helpers;

use App\Models\WhatsAppChatBot;
use App\Models\WhatsAppChatBotSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotEngine
{
    protected int $defaultTimeoutMinutes = 30;
    protected $configuration;

    public function __construct($configuration)
    {
        $this->configuration = $configuration;
    }

    public function handleIncomingMessage(string $from, string $message, int $configurationId, array $payload = [])
    {
        // Check for existing active session
        $session = WhatsAppChatBotSession::where('customer_number', $from)->first();

        // Get all bot flows
        $bots = WhatsAppChatBot::where('whats_app_configuration_id', $configurationId)->get();

        // Step 1: If session exists and expired -> remove
        if ($session && $this->isSessionExpired($session)) {
            $session->delete();
            $session = null;
        }

        // Step 2: Detect initiation keyword
        $initiationBot = $bots->first(function ($bot) use ($message) {
            $payload = $bot->request_payload;
            return isset($payload['initiation']) && strtolower($message) === strtolower($payload['initiation']);
        });

        // Step 3: Session exists + user tries to start a new flow
        // If user tries to start a new flow but session exists
        if ($session && $initiationBot) {

            // Prepare message
            $text = "You already have an active session. Do you want to cancel it and start '{$initiationBot->chatbot_name}'?";

            // Cache pending switch for 5 minutes
            Cache::put("switch_pending:{$from}", [
                'old_session_id' => $session->id,
                'new_bot_id' => $initiationBot->id,
            ], now()->addMinutes(5));

            // Send WhatsApp message with buttons
            $this->sendReply($from, $text, 'buttons', [
                ['id' => 'yes', 'title' => 'Yes'],
                ['id' => 'no', 'title' => 'No']
            ]);

            // Return payload for logging or further handling
            return [
                'type' => 'message',
                'text' => $text,
                'options' => ['Yes', 'No']
            ];
        }

        // Step 4: No session + user starts new flow
        if (!$session && $initiationBot) {
            return $this->startSession($from, $initiationBot);
        }

        // Step 5: Continue existing session
        if ($session) {
            return $this->continueSession($session, $message);
        }

        // Step 6: No match
        return [
            'type' => 'message',
            'text' => "Sorry, I didn’t understand that."
        ];
    }


    protected function askSwitchConfirmation($session, $newBot, $from)
    {
        // Cache pending switch for 5 minutes
        Cache::put("switch_pending:{$from}", [
            'old_session_id' => $session->id,
            'new_bot_id' => $newBot->id,
        ], now()->addMinutes(5));

        return [
            'type' => 'buttons',
            'text' => "You are already in another flow. Do you want to cancel the current flow and start '{$newBot->chatbot_name}'?",
            'options' => [
                ['id' => 'yes', 'title' => 'Yes'],
                ['id' => 'no', 'title' => 'No']
            ]
        ];
    }


    public function handleSwitchResponse(string $from, string $message)
    {
        $pending = Cache::get("switch_pending:{$from}");
        if (!$pending) return null;

        $messageNormalized = strtolower(trim($message));

        if ($messageNormalized === 'yes') {
            // Delete old session
            WhatsAppChatBotSession::where('id', $pending['old_session_id'])->delete();

            // Start new session
            $newBot = WhatsAppChatBot::find($pending['new_bot_id']);
            Cache::forget("switch_pending:{$from}");

            // Return the new session messages immediately
            return $this->startSession($from, $newBot);
        }

        if ($messageNormalized === 'no') {
            Cache::forget("switch_pending:{$from}");
            return $this->sendReply(
                $from,
                "Okay, continuing your current session.",
                'text'
            );
        }

        return null;
    }


    protected function isSessionExpired($session): bool
    {
        return $session->updated_at->addMinutes($this->defaultTimeoutMinutes)->isPast();
    }

    protected function startSession(string $from, $bot)
    {
        $flow = $bot->request_payload;

        $session = WhatsAppChatBotSession::create([
            'wa_chat_bot_id' => $bot->id,
            'whats_app_configuration_id' => $this->configuration->id,
            'customer_number' => $from,
            'current_step' => $flow['steps'][0]['id'] ?? null,
            'meta' => json_encode([]),
        ]);

        return $this->goToStep($session, $flow, $session->current_step);
    }

    protected function continueSession($session, $message)
    {
        $flow = $session->bot->request_payload;
        $currentStep = $this->findStepById($flow['steps'], $session->current_step);

        if (!$currentStep) {
            $session->delete();
            return ['type' => 'message', 'text' => 'Session ended.'];
        }

        $messageNormalized = strtolower(trim($message));

        switch ($currentStep['type']) {
            case 'buttons':
                $selected = collect($currentStep['options'] ?? [])->first(function ($opt) use ($messageNormalized) {
                    return strtolower($opt['id']) === $messageNormalized || strtolower($opt['title']) === $messageNormalized;
                });
                if ($selected) {
                    if (!isset($selected['next_step']) || !$selected['next_step']) {
                        $session->delete();
                        return ['type' => 'message', 'text' => 'Session ended.'];
                    }
                    return $this->goToStep($session, $flow, $selected['next_step']);
                }
                return $this->processStep($session, '', $currentStep, $flow);

            case 'list':
                $selected = collect($currentStep['items'] ?? [])->first(function ($opt) use ($messageNormalized) {
                    return (isset($opt['id']) && strtolower($opt['id']) === $messageNormalized) || strtolower($opt['title']) === $messageNormalized;
                });
                if ($selected) {
                    if (!isset($selected['next_step']) || !$selected['next_step']) {
                        $session->delete();
                        return ['type' => 'message', 'text' => 'Session ended.'];
                    }
                    return $this->goToStep($session, $flow, $selected['next_step']);
                }
                return $this->processStep($session, '', $currentStep, $flow);

            case 'input':
                $meta = is_string($session->meta) ? json_decode($session->meta, true) : ($session->meta ?? []);
                $fieldKey = $currentStep['field'] ?? ($currentStep['save_as'] ?? 'input');
                $meta[$fieldKey] = $message;
                $session->meta = json_encode($meta);
                $session->save();

                if (!isset($currentStep['next_step']) || !$currentStep['next_step']) {
                    $session->delete();
                    return ['type' => 'message', 'text' => 'Session ended.'];
                }
                return $this->goToStep($session, $flow, $currentStep['next_step']);

            case 'condition':
                $case = collect($currentStep['cases'] ?? [])->first(function ($c) use ($messageNormalized) {
                    return strtolower($c['when']) === $messageNormalized;
                });
                if ($case) {
                    if (!isset($case['next_step']) || !$case['next_step']) {
                        $session->delete();
                        return ['type' => 'message', 'text' => 'Session ended.'];
                    }
                    return $this->goToStep($session, $flow, $case['next_step']);
                }
                return $this->processStep($session, '', $currentStep, $flow);

            default:
                return $this->processStep($session, '', $currentStep, $flow);
        }
    }

    protected function findStepById(array $steps, $id)
    {
        foreach ($steps as $s) {
            if ($s['id'] == $id) return $s;
        }
        return null;
    }

    protected function processStep($session, $message, $step, $flow)
    {
        $meta = is_string($session->meta) ? json_decode($session->meta, true) : ($session->meta ?? []);

        switch ($step['type']) {
            case 'message':
                $text = $this->replaceVars($step['text'] ?? '', $meta);
                $this->sendReply($session->customer_number, $text, 'text');
                if (isset($step['next_step']) && $step['next_step']) {
                    return $this->goToStep($session, $flow, $step['next_step']);
                }
                $session->delete();
                return ['type' => 'message', 'text' => 'Session ended.'];

            case 'buttons':
                $this->sendReply($session->customer_number, $step['text'] ?? '', 'buttons', $step['options'] ?? null);
                return null;

            case 'list':
                $this->sendReply($session->customer_number, $step['text'] ?? '', 'list', $step['items'] ?? null);
                return null;

            case 'input':
                $prompt = $this->replaceVars($step['text'] ?? '', $meta);
                $this->sendReply($session->customer_number, $prompt, 'input');
                return null;

            case 'condition':
                if (!empty($step['text'])) {
                    $this->sendReply($session->customer_number, $this->replaceVars($step['text'], $meta), 'condition');
                }
                return null;

            case 'api_call':
                try {
                    $response = $this->callExternalApi($step, $meta);

                    // If API failed or returned empty
                    if (!$response || !is_array($response)) {
                        return [
                            'type' => 'message',
                            'text' => 'Sorry, something went wrong while fetching your details. Please try again later.'
                        ];
                    }

                    // Handle save_response_as
                    if (isset($step['save_response_as'])) {
                        if ($step['save_response_as'] === '*' || $step['save_response_as'] === '') {
                            // Merge everything at root
                            $meta = array_merge($meta, $response);
                        } elseif (isset($response[$step['save_response_as']])) {
                            // Save only the matching part
                            $meta[$step['save_response_as']] = $response[$step['save_response_as']];
                        } else {
                            // Save full response under the given alias
                            $meta[$step['save_response_as']] = $response;
                        }
                    } else {
                        // Default: merge full response into root
                        $meta = array_merge($meta, $response);
                    }

                    // Save session meta safely
                    $session->meta = json_encode($meta);
                    $session->save();

                    // Go to next step if defined
                    if (!empty($step['next_step'])) {
                        return $this->goToStep($session, $flow, $step['next_step']);
                    }

                    // End session safely
                    $session->delete();
                    return ['type' => 'message', 'text' => 'Session ended.'];

                } catch (\Exception $e) {
                    // Log error for debugging
                    \Log::error('API Call Error: ' . $e->getMessage());

                    return [
                        'type' => 'message',
                        'text' => 'We are facing technical issues connecting to the service. Please try again later.'
                    ];
                }

            case 'media':
                $url = $this->replaceVars($step['url'] ?? '', $meta);
                $caption = $this->replaceVars($step['caption'] ?? '', $meta);
                $mediaType = $step['media_type'] ?? 'image'; // image, video, audio, document

                $this->sendReply($session->customer_number, $caption, 'media', [
                    'media_type' => $mediaType,
                    'url' => $url,
                    'caption' => $caption
                ]);

                if (isset($step['next_step']) && $step['next_step']) {
                    return $this->goToStep($session, $flow, $step['next_step']);
                }
                $session->delete();
                return ['type' => 'message', 'text' => 'Session ended.'];

            case 'end':
                $this->sendReply($session->customer_number, $step['text'] ?? "Session ended.", 'end');
                $session->delete();
                return null;
        }
    }


    protected function goToStep($session, $flow, $stepId)
    {
        if (!isset($flow['steps']) || !is_array($flow['steps'])) {
            $session->delete();
            $this->sendReply($session->customer_number, "Session ended (invalid flow).", 'end');
            return;
        }

        $nextStep = collect($flow['steps'])->firstWhere('id', $stepId);

        if ($nextStep) {
            $session->current_step = $nextStep['id'];
            $session->save();
            $this->processStep($session, '', $nextStep, $flow);
        } else {
            $session->delete();
            $this->sendReply($session->customer_number, "Session ended.", 'end');
        }
    }

    protected function replaceVars($text, $vars)
    {
        if (is_string($vars)) {
            $vars = json_decode($vars, true);
        }
        if (!is_array($vars)) return $text;

        // Flatten nested arrays with dot notation
        $flatten = $this->flattenArray($vars);

        // Match all placeholders like {{key}} or {{key|default}}
        preg_match_all('/{{\s*([^}]+)\s*}}/', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $placeholder = $match[0]; // e.g., {{support.call|N/A}}
            $keyDefault = $match[1];  // e.g., support.call|N/A

            // Split key and default
            $parts = explode('|', $keyDefault, 2);
            $key = trim($parts[0]);
            $default = $parts[1] ?? '';

            // Replace with value or default
            $value = $flatten[$key] ?? $default;
            $text = str_replace($placeholder, $value, $text);
        }

        return $text;
    }

    /**
     * Flatten nested arrays using dot notation
     */
    protected function flattenArray(array $array, string $prefix = '')
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    protected function sendReply($to, $text, $step_type, $options = null)
    {
        \Log::info($options);
        $loadConf = $this->configuration;
        $sender_number = $loadConf->sender_number;
        $appVersion = $loadConf->app_version;
        $access_token = base64_decode($loadConf->access_token);

        $payload = null;

        if ($step_type === 'media' && is_array($options)) 
        {
            $mediaType = $options['media_type'] ?? 'image';
            $url = $options['url'] ?? '';
            $caption = $options['caption'] ?? '';

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => $mediaType,
                $mediaType => [
                    'link' => $url,
                    'caption' => $caption
                ]
            ];
        } 
        else 
        {
            // otherthan media type message
            $payload = createWhatsappPayloadBot($to, $text, $step_type, $options);
        }
        \Log::channel('whatsapp_bot')->info($payload);    

        \Log::channel('whatsapp_bot')->info("Sending to {$to}: {$text}", ['options' => $options]);

        $url = "https://graph.facebook.com/$appVersion/$sender_number/messages";
        $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ])->post($url, $payload);
        usleep(env('USLEEP_MICRO_SEC', 2000));
        \Log::channel('whatsapp_bot')->info($response);

        return $text;
    }

    protected function callExternalApi($step, $meta)
    {
        $method = strtoupper($step['method'] ?? 'GET');
        $url = $this->replaceVars($step['url'], $meta);
        $headers = $step['headers'] ?? [];
        $payload = [];

        \Log::channel('whatsapp_bot')->info('Api Request call bot');
        \Log::channel('whatsapp_bot')->info($step);
        \Log::channel('whatsapp_bot')->info($meta);

        if (!empty($step['payload'])) {
            foreach ($step['payload'] as $k => $v) {
                $payload[$k] = $this->replaceVars($v, $meta);
            }
        }

        try {
            $response = Http::withHeaders($headers)->{$method}($url, $payload);
            \Log::channel('whatsapp_bot')->info('Api response call bot');
            \Log::channel('whatsapp_bot')->info($response);
            return $response->json();
        } catch (\Exception $e) {
            \Log::channel('whatsapp_bot')->error("API call failed: " . $e->getMessage());
            return null;
        }
    }
}
