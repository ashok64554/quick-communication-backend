<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CurrentParent;
use App\Models\SecondaryRoute;
use App\Models\Country;
use App\Models\VoiceSmsQueue;
use App\Models\VoiceSmsHistory;
use App\Models\VoiceUpload;

class VoiceSms extends Model
{
    use HasFactory, CurrentParent;

    protected $fillable = [
        'uuid',
        'parent_id',
        'user_id',
        'campaign_id',
        'transection_id',
        'campaign',
        'obd_type',
        'dtmf',
        'call_patch_number',
        'secondary_route_id',
        'voice_upload_id',
        'voice_id',
        'voice_file_path',
        'country_id',
        'file_path',
        'file_mobile_field_name',
        'is_read_file_path',
        'campaign_send_date_time',
        'is_campaign_scheduled',
        'priority',
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
        'status'
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

    public function voiceUpload()
    {
        return $this->belongsTo(VoiceUpload::class,'voice_upload_id', 'id');
    }

    public function voiceSmsQueues()
    {
        return $this->hasMany(VoiceSmsQueue::class,'voice_sms_id', 'id');
    }

    public function voiceSmsHistories()
    {
        return $this->hasMany(VoiceSmsHistory::class,'voice_sms_id', 'id');
    }
}
