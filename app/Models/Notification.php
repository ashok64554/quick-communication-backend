<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status_code',
        'title',
        'message',
        'data_id',
        'extra_info',
        'read_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }
}
