<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CurrentParent;
use App\Models\User;

class TwoWayComm extends Model
{
    use HasFactory, CurrentParent;

    protected $connection = 'mysql_twoway';
    protected $table = 'two_way_comms';
    
    protected $fillable = [
        'parent_id',
        'created_by',
        'is_web_temp',
        'redirect_url',
        'title',
        'content',
        'bg_color',
        'content_expired',
        'take_response',
        'response_mob_num'
    ];

    public function LinkClickLogs()
    {
        return $this->hasMany(LinkClickLog::class, 'two_way_comm_id', 'id');
    }
    
    public function ShortLinks()
    {
        return $this->hasMany(ShortLink::class, 'two_way_comm_id', 'id');
    }

    public function TwoWayCommFeedbacks()
    {
        return $this->hasMany(TwoWayCommFeedback::class, 'two_way_comm_id', 'id');
    }

    public function TwoWayCommInterests()
    {
        return $this->hasMany(TwoWayCommInterest::class, 'two_way_comm_id', 'id');
    }

    public function TwoWayCommRatings()
    {
        return $this->hasMany(TwoWayCommRating::class, 'two_way_comm_id', 'id');
    }
}
