<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Country;

class WhatsAppCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'country_id',
        'wa_marketing_charge',
        'wa_utility_charge',
        'wa_service_charge',
        'wa_authentication_charge'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function Country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }
}
