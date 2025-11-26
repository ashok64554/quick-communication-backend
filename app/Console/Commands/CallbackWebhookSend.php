<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Log;
use DB;
use Illuminate\Support\Facades\Http;
use App\Models\CallbackWebhook;

class CallbackWebhookSend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'callback:webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /*
            {
                "campaign_token": "64faa610-abc7-11ee-a02b-75ac21154640",
                "mobile_number": 919713753131,
                "used_credit": 1,
                "submit_date": "2024-01-10 18:54:26",
                "done_date": "2024-01-10 18:54:27",
                "status": "DELIVRD",
                "status_code": "000"
            }
        */
        $getData = DB::table('callback_webhooks')->take(200)->get();
        foreach($getData as $data)
        {
            if(!empty($data->webhook_url))
            {
                $response = Http::timeout(5)
                    ->retry(2, 100)
                    ->withBody($data->response, 'application/json')
                    ->post($data->webhook_url);
            }
            DB::table('callback_webhooks')->where('id', $data->id)->delete();
        }
    }
}
