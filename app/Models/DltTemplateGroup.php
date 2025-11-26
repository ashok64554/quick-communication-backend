<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\DltTemplate;

class DltTemplateGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'group_name'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function dltTemplates()
    {
        return $this->hasMany(DltTemplate::class, 'dlt_template_group_id', 'id');
    }
}
