<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppTemplateButton;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'whats_app_configuration_id',
        'wa_template_id',
        'parameter_format',
        'category',
        'sub_category',
        'marketing_type',
        'template_language',
        'template_name',
        'template_type',
        'template_text',
        'header_variable',
        'media_type',
        'header_handle',
        'message',
        'message_variable',
        'footer_text',
        'button_action',
        'status',
        'wa_status',
        'tags',
        'json_response',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function whatsAppConfiguration()
    {
        return $this->belongsTo(WhatsAppConfiguration::class, 'whats_app_configuration_id', 'id');
    }

    public function whatsAppTemplateButtons()
    {
        return $this->hasMany(WhatsAppTemplateButton::class, 'whats_app_template_id', 'id');
    }
}
