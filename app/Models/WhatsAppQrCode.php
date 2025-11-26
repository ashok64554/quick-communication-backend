<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\WhatsAppConfiguration;

class WhatsAppQrCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'whats_app_configuration_id',
        'qr_image_format',
        'prefilled_message',
        'code',
        'deep_link_url',
        'qr_image_url'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function whatsAppConfiguration()
    {
        return $this->belongsTo(WhatsAppConfiguration::class, 'whats_app_configuration_id', 'id');
    }
}
