<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PrimaryRoute;

class DailySubmissionLog extends Model
{
    use HasFactory;

    protected $fillable = [ 
        'submission_date',
        'sms_gateway',
        'submission',
        'submission_credit_used',
        'auto_submission',
        'auto_submission_credit',
        'overall_delivered',
        'overall_delivered_credit',
        'actual_delivered',
        'actual_delivered_credit',
        'other_than_delivered',
        'other_than_delivered_credit'
    ];

    public function primaryRoute()
    {
        return $this->belongsTo(PrimaryRoute::class,'sms_gateway', 'id');
    }
}
