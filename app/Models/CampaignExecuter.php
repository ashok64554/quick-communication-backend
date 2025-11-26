<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SendSms;

class CampaignExecuter extends Model
{
    use HasFactory;

    protected $fillable = [
        'send_sms_id',
        'campaign_send_date_time',
        'campaign_type', // 1:Transactional, 2:Promotional, 3:TwoWay, 4:Voice
    ];

    public function sendSms()
    {
        return $this->belongsTo(SendSms::class,'send_sms_id', 'id');
    }
}
