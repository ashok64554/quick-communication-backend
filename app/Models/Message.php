<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'sender_id',
        'receiver_id',
        'message',
        'file_path',
        'file_type',
        'read_at',
    ];

    public function sender()
    {
        return $this->hasOne(User::class,'id','sender_id')->withoutGlobalScope('parent_id');
    
    }
    public function receiver()
    {
        return $this->hasOne(User::class,'id','receiver_id')->withoutGlobalScope('parent_id');
    }
}
