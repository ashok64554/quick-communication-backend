<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DlrGenerate extends Model
{
    use HasFactory;

    // We are not using this right now, we are directly created dlr
    protected $fillable = [ 
        'msg_id',
        'final_date_time',
    ];

    public $timestamps = false;
}
