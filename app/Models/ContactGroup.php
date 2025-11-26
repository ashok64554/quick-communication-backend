<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CurrentParent;
use App\Models\User;
use App\Models\ContactNumber;

class ContactGroup extends Model
{
    use HasFactory, CurrentParent;

    protected $fillable = [
        'parent_id',
        'user_id',
        'group_name',
        'description',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class,'parent_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function contactNumbers()
    {
        return $this->hasMany(ContactNumber::class, 'contact_group_id', 'id');
    }
}
