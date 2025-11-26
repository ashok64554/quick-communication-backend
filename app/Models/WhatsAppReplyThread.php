<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\WhatsAppSendSmsHistory;
use App\Models\User;
use Iksaku\Laravel\MassUpdate\MassUpdatable;

class WhatsAppReplyThread extends Model
{
    use HasFactory, MassUpdatable;

    protected $fillable = [
        'queue_history_unique_key',
        'whats_app_send_sms_id',
        'user_id',
        'profile_name',
        'phone_number_id',
        'display_phone_number',
        'user_mobile',
        'message_type',
        'message',
        'json_message',
        'media_id',
        'mime_type',
        'media_url',
        'context_ref_wa_id',
        'error_info',
        'received_date',
        'response_token',
        'use_credit',
        'is_vendor_reply',
    ];

    public function WhatsAppSendSms()
    {
        return $this->belongsTo(WhatsAppSendSms::class, 'whats_app_send_sms_id', 'id');
    }

    public function WhatsAppSendSmsQueue()
    {
        return $this->belongsTo(WhatsAppSendSmsQueue::class, 'queue_history_unique_key', 'unique_key');
    }

    public function WhatsAppSendSmsHistory()
    {
        return $this->belongsTo(WhatsAppSendSmsHistory::class, 'queue_history_unique_key', 'unique_key');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
