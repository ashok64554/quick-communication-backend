<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CurrentParent;
use App\Models\User;

class CreditRequest extends Model
{
    use HasFactory, CurrentParent;

    protected $fillable = [
        'parent_id',
        'user_id',
        'created_by',
        'credit_request',
        'route_type',
        'comment',
        'request_reply',
        'reply_date',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class,'parent_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withoutGlobalScope('parent_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id')->withoutGlobalScope('parent_id');
    }
}
