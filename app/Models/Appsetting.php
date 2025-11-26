<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use mervick\aesEverywhere\AES256;

class Appsetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'app_logo',
        'contact_email',
        'contact_address',
        'contact_number',
        'tax_percentage',
        'file_gen_if_exceed',
        'order_no_start',
        'g_key',
        'g_secret',
        'privacy_policy',
        'terms_and_conditions',
        'cookies_protection',
        'cookies_disclaimer',
    ];

    public function getGKeyAttribute($value)
    {
        return (!empty($value)) ? AES256::encrypt($value, 'NewRiseInc') : NULL;
    }

    public function getGSecretAttribute($value)
    {
        return (!empty($value)) ? AES256::encrypt($value, 'NewRiseInc') : NULL;
    }
}
