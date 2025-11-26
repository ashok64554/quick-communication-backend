<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CurrentParent;
use App\Models\TwoWayComm;
use App\Models\SendSms;
use App\Models\LinkClickLog;
use App\Models\TwoWayCommFeedback;
use App\Models\TwoWayCommInterest;
use App\Models\TwoWayCommRating;

class ShortLink extends Model
{
    use HasFactory, CurrentParent;

    protected $connection = 'mysql_twoway';
    protected $table = 'short_links';
    
    protected $fillable = [
        'parent_id',
        'two_way_comm_id',
        'send_sms_id',
        'code',
        'sub_part',
        'token',
        'mobile_num',
        'link',
        'total_click',
        'link_expired'
    ]; 

    public function TwoWayComm()
    {
        return $this->belongsTo(TwoWayComm::class, 'two_way_comm_id', 'id');
    }

    public function sendSms()
    {
        return $this->belongsTo(SendSms::class, 'send_sms_id', 'id');
    }

    public function linkClickLogs()
    {
        return $this->hasMany(LinkClickLog::class, 'short_link_id', 'id');
    }

    public function twoWayCommFeedbacks()
    {
        return $this->hasMany(TwoWayCommFeedback::class, 'short_link_id', 'id');
    }

    public function twoWayCommInterests()
    {
        return $this->hasMany(TwoWayCommInterest::class, 'short_link_id', 'id');
    }

    public function twoWayCommRatings()
    {
        return $this->hasMany(TwoWayCommRating::class, 'short_link_id', 'id');
    }
}
