<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CurrentParent;
use App\Models\User;
use App\Models\ContactGroup;

class ContactNumber extends Model
{
    use HasFactory, CurrentParent;

    protected $fillable = [
        'parent_id',
        'user_id',
        'contact_group_id',
        'number',
    ];

    protected $casts = [
        'number' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class,'parent_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function contactGroup()
    {
        return $this->belongsTo(ContactGroup::class, 'contact_group_id', 'id');
    }
}
