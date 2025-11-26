<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_for',
        'mail_subject',
        'mail_body',
        'notification_subject',
        'notification_body',
        'custom_attributes',
        'save_to_database',
        'status_code',
        'route_path',
        'is_deletable',
    ];
}
