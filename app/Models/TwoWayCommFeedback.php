<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TwoWayComm;
use App\Models\ShortLink;

class TwoWayCommFeedback extends Model
{
    use HasFactory;

    protected $connection = 'mysql_twoway';
    protected $table = 'two_way_comm_feedbacks';
    
    protected $fillable = [
        'two_way_comm_id',
        'short_link_id',
        'send_sms_id',
        'name',
        'mobile',
        'email',
        'subject',
        'comment',
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
