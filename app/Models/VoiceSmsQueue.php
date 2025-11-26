<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\VoiceSms;
use App\Models\PrimaryRoute;

class VoiceSmsQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'voice_sms_id',
        'primary_route_id',
        'unique_key',
        'mobile',
        'voice_id',
        'use_credit',
        'is_auto',
        'stat',
        'err',
        'submit_date',
        'done_date',
        'status',
        'response_token',
        'cli',
        'flag',
        'start_time',
        'end_time',
        'duration',
        'dtmf'
    ];

    public function voiceSms()
    {
        return $this->belongsTo(VoiceSms::class,'voice_sms_id', 'id');
    }

    public function primaryRoute()
    {
        return $this->belongsTo(PrimaryRoute::class,'primary_route_id', 'id');
    }
}
