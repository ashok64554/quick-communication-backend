<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\SecondaryRoute;
use App\Models\DlrcodeVender;
use App\Models\PrimaryRouteAssociated;

class PrimaryRoute extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by',
        'gateway_type',
        'route_name',
        'smpp_credit',
        'coverage',
        'smsc_id',
        'api_url_for_voice',
        'ip_address',
        'port',
        'receiver_port',
        'smsc_username',
        'smsc_password',
        'system_type',
        'throughput',
        'reconnect_delay',
        'enquire_link_interval',
        'max_pending_submits',
        'transceiver_mode',
        'source_addr_ton',
        'source_addr_npi',
        'dest_addr_ton',
        'dest_addr_npi',
        'log_file',
        'log_level',
        'instances',
        'online_from',
        'status',
        'voice',
    ];

    protected $dates = ['deleted_at'];

    public function createdBy()
    {
        return $this->belongsTo(User::class,'created_by', 'id');
    }

    public function secondaryRoute()
    {
        return $this->hasMany(SecondaryRoute::class,'primary_route_id', 'id');
    }

    public function associtedRoutes()
    {
        return $this->hasMany(PrimaryRouteAssociated::class,'primary_route_id', 'id');
    }

    public function dlrcodeVenders()
    {
        return $this->hasMany(DlrcodeVender::class,'primary_route_id', 'id');
    }
}
