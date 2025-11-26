<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\VoiceUploadSentGateway;

class VoiceUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'voiceId',
        'fileStatus',
        'title',
        'file_location',
        'file_time_duration', // 30, 60, 90 or 120 seconds
        'exact_file_duration',
        'file_mime_type',
        'file_extension',
        'priority'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function voiceUploadSentGateways()
    {
        return $this->hasMany(VoiceUploadSentGateway::class, 'voice_upload_id', 'id');
    }
}
