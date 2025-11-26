<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\WhatsAppChatBotSession;

class WhatsAppChatBot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'whats_app_configuration_id',
        'whats_app_template_id',
        'display_phone_number_req',
        'chatbot_name',
        'matching_criteria',
        'start_with',
        'request_payload',
        'automation_flow',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'automation_flow' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    protected function requestPayload(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => !empty($value) ? json_decode($value, true) : null,
        );
    }

    protected function automationFlow(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => !empty($value) ? json_decode($value, true) : null,
        );
    }

    public function sessions() 
    {
        return $this->hasMany(WhatsAppChatBotSession::class, 'wa_chat_bot_id', 'id');
    }
}
