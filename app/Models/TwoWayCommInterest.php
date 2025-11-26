<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TwoWayComm;
use App\Models\ShortLink;

class TwoWayCommInterest extends Model
{
    use HasFactory;

    protected $connection = 'mysql_twoway';
    protected $table = 'two_way_comm_interests';
    
    protected $fillable = [
        'two_way_comm_id',
        'short_link_id',
        'send_sms_id',
        'mobile',
        'isInterest',
        'ip'
    ]; 

    public function TwoWayComm()
    {
        return $this->belongsTo(TwoWayComm::class, 'two_way_comm_id', 'id');
    }

    public function ShortLink()
    {
        return $this->belongsTo(ShortLink::class, 'two_way_comm_id', 'id');
    }
}
