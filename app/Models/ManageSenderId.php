<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CurrentParent;
use App\Models\User;
use App\Models\DltTemplate;

class ManageSenderId extends Model
{
    use HasFactory, CurrentParent;

    protected $fillable = [
        'parent_id',
        'user_id',
        'company_name',
        'entity_id',
        'header_id',
        'sender_id',
        'sender_id_type',
        'status',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class,'parent_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function dltTemplates()
    {
        return $this->belongsTo(DltTemplate::class, 'manage_sender_id', 'id');
    }
}
