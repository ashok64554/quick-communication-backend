<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppRateCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_name',
        'currency',
        'marketing_charge',
        'utility_charge',
        'authentication_charge',
        'authentication_international_charge',
        'service_charge',
    ];
}
