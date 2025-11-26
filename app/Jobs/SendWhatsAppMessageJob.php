<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $access_token;
    protected $sender_number;
    protected $appVersion;
    protected $templateName;
    protected $payload;
    protected $submitMsg;

    public function __construct($access_token, $sender_number, $appVersion, $templateName, $payload, $submitMsg) 
    {
        $this->access_token =  $access_token;
        $this->sender_number =  $sender_number;
        $this->appVersion =  $appVersion;
        $this->templateName =  $templateName;
        $this->payload =  $payload;
        $this->submitMsg =  $submitMsg;
    }

    public function handle(): void
    {
        
        $access_token = $this->access_token;
        $sender_number = $this->sender_number;
        $appVersion = $this->appVersion;
        $templateName = $this->templateName;
        $payload = $this->payload;
        $submitMsg = $this->submitMsg;
        $url = "https://graph.facebook.com/$appVersion/$sender_number/messages";

        $response = Http::withHeaders([
            'Authorization' =>  'Bearer ' . $access_token,
            'Content-Type' => 'application/json' 
        ])
        ->post($url, $payload)

        if (!$response->successful()) {
            \Log::channel('whatsapp')->error("WhatsApp message failed for {$this->phone}", $response->json());
        }
    }
}
