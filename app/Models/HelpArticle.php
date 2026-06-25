<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    protected $fillable = [
        'category', 'slug', 'title', 'summary', 'body', 'sort_order', 'is_published', 'audience',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
