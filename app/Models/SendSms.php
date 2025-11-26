<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CurrentParent;
use App\Models\User;
use App\Models\SecondaryRoute;
use App\Models\DltTemplate;
use App\Models\Country;
use App\Models\SendSmsQueue;
use App\Models\CampaignExecuter;
use App\Models\SendSmsHistory;
use App\Models\DltTemplateGroup;

class SendSms extends Model
{
    use HasFactory, CurrentParent;

    protected $fillable = [
        'uuid',
        'parent_id',
        'user_id',
        'campaign',
        'secondary_route_id',
        'dlt_template_id',
        'dlt_template_group_id',
        'sender_id',
        'route_type',
        'country_id',
        'sms_type',
        'message',
        'message_type',
        'is_flash',
        'file_path',
        'file_mobile_field_name',
        'is_read_file_path',
        'campaign_send_date_time',
        'is_campaign_scheduled',
        'priority',
        'message_count',
        'message_credit_size',
        'total_contacts',
        'total_block_number',
        'total_invalid_number',
        'total_credit_deduct',
        'ratio_percent_set',
        'failed_ratio',
        'total_delivered',
        'total_failed',
        'is_credit_back',
        'self_credit_back',
        'parent_credit_back',
        'credit_back_date',
        'is_update_auto_status',
        'status',
        'reschedule_send_sms_id',
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

    public function parent()
    {
        return $this->belongsTo(User::class,'parent_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function secondaryRoute()
    {
        return $this->belongsTo(SecondaryRoute::class, 'secondary_route_id', 'id');
    }

    public function dltTemplate()
    {
        return $this->belongsTo(DltTemplate::class, 'dlt_template_id', 'id');
    }

    public function dltTemplateGroup()
    {
        return $this->belongsTo(DltTemplateGroup::class, 'dlt_template_group_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function campaignExecuter()
    {
        return $this->hasOne(CampaignExecuter::class,'send_sms_id', 'id');
    }

    public function sendSmsQueues()
    {
        return $this->hasMany(SendSmsQueue::class,'send_sms_id', 'id');
    }

    public function sendSmsHistories()
    {
        return $this->hasMany(SendSmsHistory::class,'send_sms_id', 'id');
    }

    public function repushSendSmsQueues()
    {
        return $this->hasMany(SendSmsQueue::class,'send_sms_id', 'reschedule_send_sms_id');
    }

    public function repushSendSmsHistories()
    {
        return $this->hasMany(SendSmsHistory::class,'send_sms_id', 'reschedule_send_sms_id');
    }
}
