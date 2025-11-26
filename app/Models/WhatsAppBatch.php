<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\WhatsAppSendSmsQueue;

class WhatsAppBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'whats_app_send_sms_id',
        'batch',
        'current_status',
        'priority',
        'execute_time',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function whatsAppSendSmsQueues()
    {
        return $this->belongsTo(WhatsAppSendSmsQueue::class, 'batch_id', 'batch');
    }
}
