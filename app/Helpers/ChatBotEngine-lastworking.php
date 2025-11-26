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

        // ⚡ Step 2.5: Check if user is answering a pending switch
        $pending = Cache::get("switch_pending:{$from}");
        if ($pending) {
            return $this->handleSwitchResponse($from, $message);
        }

        // Step 3: Session exists + user tries to start a new flow
        if ($session && $initiationBot) {
            $text = "You already have an active session. Do you want to cancel it and start '{$initiationBot->chatbot_name}'?";

            Cache::put("switch_pending:{$from}", [
                'old_session_id' => $session->id,
                'new_bot_id' => $initiationBot->id,
            ], now()->addMinutes(5));

            $this->sendReply($from, $text, 'buttons', [
                ['id' => 'yes', 'title' => 'Yes'],
                ['id' => 'no', 'title' => 'No']
            ]);

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

    protected function handleSwitchResponse(string $from, string $message)
    {
        $pending = Cache::get("switch_pending:{$from}");
        if (!$pending) {
            return null;
        }

        if (strtolower($message) === 'yes') {
            WhatsAppChatBotSession::where('id', $pending['old_session_id'])->delete();
            $newBot = WhatsAppChatBot::find($pending['new_bot_id']);
            Cache::forget("switch_pending:{$from}");
            return $this->startSession($from, $newBot);
        }

        if (strtolower($message) === 'no') {
            Cache::forget("switch_pending:{$from}");
            $this->sendReply($from, "Okay, continuing your current session.", 'message');
            return ['type' => 'message', 'text' => "Okay, continuing your current session."];
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

    // processStep(), goToStep(), replaceVars(), sendReply(), callExternalApi() remain the same from your last version
    /**
     * Process step execution
     */
    protected function processStep($session, $message, $step, $flow)
    {
        $meta = is_string($session->meta) ? json_decode($session->meta, true) : ($session->meta ?? []);

        switch ($step['type']) {
            case 'message':
                $text = $this->replaceVars($step['text'] ?? '', $meta);
                $this->sendReply($session->customer_number, $text, 'text');

                if (!empty($step['next_step'])) {
                    return $this->goToStep($session, $flow, $step['next_step']);
                }
                $session->delete();
                return null;

            case 'buttons':
                $this->sendReply($session->customer_number, $step['text'], 'buttons', $step['options'] ?? []);
                // WAIT for user response
                return null;

            case 'list':
                $this->sendReply($session->customer_number, $step['text'], 'list', $step['items'] ?? []);
                // WAIT for user response
                return null;

            case 'input':
                $prompt = $this->replaceVars($step['text'], $meta);
                $this->sendReply($session->customer_number, $prompt, 'input');
                // WAIT for user response
                return null;

            case 'api_call':
                $response = $this->callExternalApi($step, $meta);

                if (isset($step['save_response_as'])) {
                    $meta[$step['save_response_as']] = $response;
                    $session->meta = json_encode($meta);
                    $session->save();
                }

                if (!empty($step['next_step'])) {
                    return $this->goToStep($session, $flow, $step['next_step']);
                }
                $session->delete();
                return null;

            case 'media':
                $url     = $this->replaceVars($step['url'], $meta);
                $caption = $this->replaceVars($step['caption'] ?? '', $meta);
                $mediaType = $step['media_type'] ?? 'image';

                $this->sendReply($session->customer_number, $caption, 'media', [
                    'media_type' => $mediaType,
                    'url'        => $url,
                    'caption'    => $caption
                ]);

                if (!empty($step['next_step'])) {
                    return $this->goToStep($session, $flow, $step['next_step']);
                }
                $session->delete();
                return null;

            case 'end':
                $this->sendReply($session->customer_number, $step['text'] ?? "Session ended.", 'end');
                $session->delete();
                return null;
        }
    }

    /**
     * Go to next step in flow
     */
    protected function goToStep($session, $flow, $stepId)
    {
        $nextStep = collect($flow['steps'])->firstWhere('id', $stepId);

        if ($nextStep) {
            $session->current_step = $nextStep['id'];
            $session->save();
            return $this->processStep($session, '', $nextStep, $flow);
        }

        $session->delete();
        $this->sendReply($session->customer_number, "Session ended.", 'end');
        return null;
    }

    /**
     * Replace variables in text with meta values
     */
    protected function replaceVars($text, $vars)
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($vars) {
            $key = $matches[1];
            $default = null;

            if (strpos($key, '|') !== false) {
                [$key, $default] = explode('|', $key, 2);
            }

            $value = $this->getNestedValue($vars, $key);
            return $value ?? $default ?? '';
        }, $text);
    }

    protected function getNestedValue($array, $key)
    {
        $keys = explode('.', $key);
        foreach ($keys as $k) {
            if (!is_array($array) || !array_key_exists($k, $array)) {
                return null;
            }
            $array = $array[$k];
        }
        return $array;
    }

    /**
     * Send WhatsApp reply
     */
    protected function sendReply($to, $text, $step_type, $options = null)
    {
        $conf = $this->configuration;
        $sender_number = $conf->sender_number;
        $appVersion    = $conf->app_version;
        $access_token  = base64_decode($conf->access_token);

        if ($step_type === 'media' && is_array($options)) {
            $mediaType = $options['media_type'] ?? 'image';
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'   => $to,
                'type' => $mediaType,
                $mediaType => [
                    'link'    => $options['url'],
                    'caption' => $options['caption'] ?? ''
                ]
            ];
        } else {
            $payload = createWhatsappPayloadBot($to, $text, $step_type, $options);
        }

        $url = "https://graph.facebook.com/$appVersion/$sender_number/messages";
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json'
        ])->post($url, $payload);

        Log::channel('whatsapp_bot')->info("Sent to {$to}", $payload);
        Log::channel('whatsapp_bot')->info($response->body());

        return $text;
    }

    /**
     * Call external API
     */
    protected function callExternalApi($step, $meta)
    {
        $method  = strtoupper($step['method'] ?? 'GET');
        $url     = $this->replaceVars($step['url'], $meta);
        $headers = $step['headers'] ?? [];
        $payload = [];

        if (!empty($step['payload'])) {
            foreach ($step['payload'] as $k => $v) {
                $payload[$k] = $this->replaceVars($v, $meta);
            }
        }

        try {
            $response = Http::withHeaders($headers)->$method($url, $payload);
            return $response->json();
        } catch (\Exception $e) {
            Log::error("API call failed: " . $e->getMessage());
            return null;
        }
    }
}
