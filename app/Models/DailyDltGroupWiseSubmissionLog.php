<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PrimaryRoute;

class DailyDltGroupWiseSubmissionLog extends Model
{
    /**********************************************
     * 
     * RIGHT NOW WE ARE NOT USING THIS, IF WE HAVE ANY PROBLEM DURING THE REPORT THEN WE'LL CREATE AUTOMATION FOR THIS
     * 
     * */

    use HasFactory;

    protected $fillable = [ 
        'user_id',
        'submission_date',
        'dlt_template_group_id',
        'dlt_template_group_name',
        'total_submission',
        'submission_credit_used',
        'total_delivered',
        'total_failed'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
