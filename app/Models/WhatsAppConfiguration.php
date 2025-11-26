<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppQrCode;

class WhatsAppConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_phone_number',
        'display_phone_number_req',
        'name',
        'sender_number',
        'business_account_id',
        'waba_id',
        'app_id',
        'app_version',
        'access_token',
        'verified_name',
        'code_verification_status',
        'quality_rating',
        'current_limit',
        'platform_type',
        'last_quality_checked',
        'current_limit',

        'is_cart_enabled',
        'is_catalog_visible',
        'wa_commerce_setting_id',

        'business_category',
        'wa_business_page',
        'messsage_limit',
        'wa_status',
        'privacy_read_receipt',
        'privacy_deregister_mobile',
        'enable_auto_response',
        'auto_response_message',

        'calling_setting'
    ];

    protected $hidden = [
        'access_token',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function whatsAppTemplates()
    {
        return $this->hasMany(WhatsAppTemplate::class, 'whats_app_configuration_id', 'id');
    }

    public function whatsAppQrCodes()
    {
        return $this->hasMany(WhatsAppQrCode::class, 'whats_app_configuration_id', 'id');
    }
}
