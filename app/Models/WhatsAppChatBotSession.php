<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\WhatsAppChatBot;

class WhatsAppChatBotSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'wa_chat_bot_id',
        'whats_app_configuration_id',
        'flow_type',
        'customer_number',
        'current_step',
        'loop_count',
        'meta',
        'status',
        'context_vars',
        'last_message',
        'ended',
        'last_activity_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'context_vars' => 'array',
    ];

    public function bot() 
    {
        return $this->belongsTo(WhatsAppChatBot::class, 'wa_chat_bot_id', 'id');
    }
}
