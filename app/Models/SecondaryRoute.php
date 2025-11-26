<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\PrimaryRoute;

class SecondaryRoute extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'primary_route_id',
        'created_by',
        'sec_route_name',
        'status',
    ];

    protected $dates = ['deleted_at'];

    public function createdBy()
    {
        return $this->belongsTo(User::class,'created_by', 'id');
    }

    public function primaryRoute()
    {
        return $this->belongsTo(PrimaryRoute::class,'primary_route_id', 'id');
    }
}
