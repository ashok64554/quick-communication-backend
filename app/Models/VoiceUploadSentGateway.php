<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PrimaryRoute;
use App\Models\VoiceUpload;

class VoiceUploadSentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'voice_upload_id',
        'primary_route_id',
        'file_send_to_smsc_id',
        'voice_id',
        'file_status',
    ];

    public function voiceUpload()
    {
        return $this->belongsTo(VoiceUpload::class, 'voice_upload_id', 'id');
    }

    public function primaryRoute()
    {
        return $this->belongsTo(PrimaryRoute::class, 'primary_route_id', 'id');
    }
}
