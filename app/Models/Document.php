<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
      'code_lang','title','slug','api_information','api_code','response_description','api_response','video_link','image'
    ];

    public function getLangWise()
    {
        return $this->hasMany(Self::class, 'code_lang', 'code_lang');
    }
}
