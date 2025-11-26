<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SendSms;
use App\Models\PrimaryRoute;
use App\Models\DlrcodeVender;

class SendSmsHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'send_sms_id',
        'primary_route_id',
        'unique_key',
        'mobile',
        'message',
        'use_credit',
        'is_auto',
        'stat',
        'err',
        'status',
        'submit_date',
        'done_date',
        'response_token',
        'sub',
        'dlvrd',
    ];

    public function sendSms()
    {
        return $this->belongsTo(SendSms::class,'send_sms_id', 'id');
    }

    public function primaryRoute()
    {
        return $this->belongsTo(PrimaryRoute::class,'primary_route_id', 'id');
    }

    public function dlrInfo()
    {
        return $this->belongsTo(DlrcodeVender::class, 'err', 'dlr_code')->withoutGlobalScope('parent_id');
    }
}
