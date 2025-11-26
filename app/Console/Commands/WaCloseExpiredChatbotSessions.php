<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WhatsAppChatBotSession;

class WaCloseExpiredChatbotSessions extends Command
{
    protected $signature = 'expired_chatbot_session:removed';

    protected $description = 'Command description';

    public function handle()
    {
        $expiredSessions = WhatsAppChatBotSession::where('ended', false)
        ->where('last_activity_at', '<', now()->subMinutes(30))
        ->update(['ended' => true]);

        return Command::SUCCESS;
    }
}
