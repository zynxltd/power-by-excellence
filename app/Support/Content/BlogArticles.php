<?php

namespace App\Support\Content;

use Illuminate\Support\Facades\File;

class BlogArticles
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $articles = [];
        $path = resource_path('content/blog');

        if (! is_dir($path)) {
            return config('blog.articles', []);
        }

        foreach (File::glob($path.'/*.php') as $file) {
            $slug = basename($file, '.php');
            $articles[$slug] = require $file;
        }

        return $articles ?: config('blog.articles', []);
    }

    public static function find(string $slug): ?array
    {
        $file = resource_path("content/blog/{$slug}.php");

        if (is_file($file)) {
            return require $file;
        }

        return config("blog.articles.{$slug}");
    }
}
