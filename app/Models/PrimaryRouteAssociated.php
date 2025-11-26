<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PrimaryRoute;

class PrimaryRouteAssociated extends Model
{
    use HasFactory;

    protected $fillable = [
        'primary_route_id',
        'associted_primary_route',
    ];

    public $timestamps = false;

    public function primaryRoute()
    {
        return $this->belongsTo(PrimaryRoute::class,'primary_route_id', 'id');
    }

    public function PrimaryRouteAssociate()
    {
        return $this->belongsTo(PrimaryRoute::class,'associted_primary_route', 'id');
    }
}
