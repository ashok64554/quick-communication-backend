<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvalidSeries extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_with',
    ];
}
