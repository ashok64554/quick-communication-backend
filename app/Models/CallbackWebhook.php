<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallbackWebhook extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'message_type', //1:text, 2:voice, 3:whatsapp,
        'webhook_url',
        'response',
    ];
}
