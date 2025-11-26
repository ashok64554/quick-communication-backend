<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PrimaryRoute;

class DlrcodeVender extends Model
{
    use HasFactory;

    protected $fillable = [ 
        'primary_route_id',
        'dlr_code',
        'description',
        'is_refund_applicable',
        'is_retry_applicable',
    ];

    public function primaryRoute()
    {
        return $this->belongsTo(PrimaryRoute::class, 'primary_route_id', 'id');
    }
}
