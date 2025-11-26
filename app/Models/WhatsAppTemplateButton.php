<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\WhatsAppConfiguration;

class WhatsAppTemplateButton extends Model
{
    use HasFactory;

    protected $fillable = [
        'whats_app_template_id',
        'button_type',
        'url_type',
        'button_text',
        'button_val_name',
        'button_value',
        'button_variables',        
        'flow_id',        
        'flow_action',        
        'navigate_screen',        
    ];

    public function whatsAppTemplate()
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whats_app_template_id', 'id');
    }
}
