<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [ 
        'name',
        'iso',
        'iso3',
        'currency',
        'currency_code',
        'currency_symbol',
        'phonecode',
        'min',
        'max',
    ];
}
