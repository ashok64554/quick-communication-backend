<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WebhookEvent;

class WebhookEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $events = [
            [
                'event_name' => 'message.send',
                'event_description' => null,
                'event_response' => null
            ],
            [
                'event_name' => 'message.failed',
                'event_description' => null,
                'event_response' => null
            ],
            [
                'event_name' => 'message.delivered',
                'event_description' => null,
                'event_response' => null
            ],
        ];

        WebhookEvent::truncate();

        WebhookEvent::insert($events);
    }
}
