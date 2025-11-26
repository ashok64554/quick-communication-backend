<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CurrentParent;
use App\Models\User;

class CreditLog extends Model
{
    use HasFactory, CurrentParent;

    protected $fillable = [
        'parent_id',
        'user_id',
        'created_by',
        'log_type',
        'action_for',
        'credit_type',
        'old_balance',
        'balance_difference',
        'current_balance',
        'rate',
        'comment',
        'scurrbing_sms_adjustment',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class,'parent_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
