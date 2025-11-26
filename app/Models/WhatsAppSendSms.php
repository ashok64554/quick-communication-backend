<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppSendSmsQueue;
use App\Models\WhatsAppSendSmsHistory;
use App\Models\WhatsAppReplyThread;
use App\Models\Country;

class WhatsAppSendSms extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'whats_app_configuration_id',
        'whats_app_template_id',
        'country_id',
        'campaign',
        'sender_number',
        'message',
        'file_path',
        'file_mobile_field_name',
        'is_read_file_path',
        'campaign_send_date_time',
        'is_campaign_scheduled',
        'message_category',
        'charges_per_msg',
        'total_contacts',
        'total_block_number',
        'total_invalid_number',
        'total_credit_deduct',
        'ratio_percent_set',
        'failed_ratio',
        'total_sent',
        'total_delivered',
        'total_read',
        'total_failed',
        'total_other',
        'is_credit_back',
        'self_credit_back',
        'parent_credit_back',
        'credit_back_date',
        'is_update_auto_status',
        'status',
        'reschedule_whats_app_send_sms_id',
        'reschedule_type'
    ];

    protected $hidden = [
        'is_update_auto_status',
        'ratio_percent_set',
        'failed_ratio'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function whatsAppConfiguration()
    {
        return $this->belongsTo(WhatsAppConfiguration::class, 'whats_app_configuration_id', 'id');
    }

    public function whatsAppTemplate()
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whats_app_template_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function whatsAppSendSmsQueues()
    {
        return $this->hasMany(WhatsAppSendSmsQueue::class, 'whats_app_send_sms_id', 'id');
    }

    public function whatsAppSendSmsHistories()
    {
        return $this->hasMany(WhatsAppSendSmsHistory::class, 'whats_app_send_sms_id', 'id');
    }

    public function WhatsAppReplyThreads()
    {
        return $this->hasMany(WhatsAppReplyThread::class, 'whats_app_send_sms_id', 'id');
    }
}
