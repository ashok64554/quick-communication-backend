<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CurrentParent;
use App\Models\User;
use App\Models\ManageSenderId;
use App\Models\DltTemplateGroup;

class DltTemplate extends Model
{
    use HasFactory, CurrentParent;

    protected $fillable = [
        'parent_id',
        'user_id',
        'manage_sender_id',
        'dlt_template_group_id',
        'template_name',
        'dlt_template_id',
        'entity_id',
        'sender_id',
        'header_id',
        'is_unicode',
        'dlt_message',
        'priority',
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

    public function manageSenderId()
    {
        return $this->belongsTo(ManageSenderId::class, 'manage_sender_id', 'id');
    }

    public function dltTemplateGroup()
    {
        return $this->belongsTo(DltTemplateGroup::class, 'dlt_template_group_id', 'id');
    }
}
