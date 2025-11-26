<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TwoWayComm;
use App\Models\ShortLink;

class LinkClickLog extends Model
{
    use HasFactory;

    protected $connection = 'mysql_twoway';
    
    protected $fillable = [
        'two_way_comm_id',
        'short_link_id',
        'mobile',
        'ip',
        'browserName',
        'browserFamily',
        'browserVersion',
        'browserEngine',
        'platformName',
        'deviceFamily',
        'deviceModel'
    ];

    public function TwoWayComm()
    {
        return $this->belongsTo(TwoWayComm::class, 'two_way_comm_id', 'id');
    }
    
    public function ShortLinks()
    {
        return $this->belongsTo(ShortLink::class, 'short_link_id', 'id');
    }
}
