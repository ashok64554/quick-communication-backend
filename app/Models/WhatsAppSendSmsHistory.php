<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\WhatsAppSendSms;
use App\Models\WhatsAppReplyThread;
use Iksaku\Laravel\MassUpdate\MassUpdatable;

class WhatsAppSendSmsHistory extends Model
{
    use HasFactory, MassUpdatable;

    protected $fillable = [
        'whats_app_send_sms_id',
        'user_id',
        'batch_id',
        'unique_key',
        'sender_number',
        'mobile',
        'template_category',
        'message',
        'use_credit',
        'is_auto',
        'stat',
        'status',
        'error_info',
        'submit_date',
        'response_token',
        'conversation_id',
        'expiration_timestamp',
        'sent',
        'sent_date_time',
        'delivered',
        'delivered_date_time',
        'read',
        'read_date_time',
        'meta_billable',
        'meta_pricing_model',
        'meta_billing_category',
    ];

    public function whatsAppSendSms()
    {
        return $this->belongsTo(WhatsAppSendSms::class, 'whats_app_send_sms_id', 'id');
    }

    public function WhatsAppReplyThreads()
    {
        return $this->hasMany(WhatsAppReplyThread::class, 'queue_history_unique_key', 'unique_key');
    }

    protected function message(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => (!empty($value) ? json_decode($value, true) : null),
        );
    }
}
