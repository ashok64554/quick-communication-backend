<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DynamicContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamic_content_id', 
        'slug', 
        'title', 
        'subtitle', 
        'description', 
        'image', 
        'order_num'
    ];

    public function getChildContent()
    {
        return $this->hasMany(self::class, 'dynamic_content_id', 'id')->orderBy('order_num', 'ASC');
    }
}
