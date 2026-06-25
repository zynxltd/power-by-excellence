<?php

namespace App\Http\Controllers;

use App\Support\Content\BlogArticles;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Marketing/Blog/Index', [
            'articles' => collect(BlogArticles::all())->map(fn ($a, $slug) => [
                'slug' => $slug,
                'title' => $a['title'],
                'excerpt' => $a['excerpt'],
                'published_at' => $a['published_at'],
                'category' => $a['category'] ?? 'Lead Distribution',
                'reading_time' => $a['reading_time'] ?? self::readingTime($a['body'] ?? ''),
                'word_count' => str_word_count($a['body'] ?? ''),
            ])->values(),
            'seo' => [
                'title' => 'Lead Distribution Blog — PowerByExcellence',
                'description' => 'Expert articles on ping-tree routing, real-time bidding, buyer management, and lead generation best practices.',
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $article = BlogArticles::find($slug);
        abort_unless($article, 404);

        return Inertia::render('Marketing/Blog/Show', [
            'article' => array_merge($article, [
                'slug' => $slug,
                'reading_time' => $article['reading_time'] ?? self::readingTime($article['body'] ?? ''),
                'word_count' => str_word_count($article['body'] ?? ''),
            ]),
            'seo' => [
                'title' => ($article['title'] ?? 'Article').' — PowerByExcellence Blog',
                'description' => $article['excerpt'] ?? '',
            ],
        ]);
    }

    protected static function readingTime(string $body): int
    {
        return max(1, (int) ceil(str_word_count($body) / 200));
    }
}
