<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Support\Help\HelpContentLoader;
use Illuminate\Database\Seeder;

class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = HelpContentLoader::articles();
        $slugs = collect($articles)->pluck('slug')->all();

        foreach ($articles as $article) {
            HelpArticle::updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'category' => $article['category'],
                    'title' => $article['title'],
                    'summary' => $article['summary'],
                    'audience' => $article['audience'],
                    'body' => $article['body'],
                    'sort_order' => $article['sort_order'],
                    'is_published' => $article['is_published'],
                ]
            );
        }

        // Remove legacy/orphan articles (old inline seeder, admin category, etc.)
        HelpArticle::query()
            ->where(function ($query) use ($slugs) {
                $query->whereNotIn('slug', $slugs)
                    ->orWhere('audience', 'admin')
                    ->orWhere('category', 'Admin');
            })
            ->delete();
    }
}
