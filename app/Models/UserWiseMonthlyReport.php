<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class UserWiseMonthlyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'group_name',
        'month',
        'year',
        'total_submission',
        'total_credit_deduct',
        'total_delivered',
        'total_failed',
        'total_rejected',
        'total_invalid'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withTrashed();
    }
}
