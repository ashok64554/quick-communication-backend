<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class SpeedRatio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trans_text_sms',
        'promo_text_sms',
        'two_way_sms',
        'voice_sms',
        'whatsapp_sms',
        'trans_text_f_sms',
        'promo_text_f_sms',
        'two_way_f_sms',
        'voice_f_sms',
        'whatsapp_f_sms',
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }
}
