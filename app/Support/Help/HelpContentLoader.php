<?php

namespace App\Support\Help;

class HelpContentLoader
{
    /**
     * @return list<array{category: string, slug: string, title: string, summary: string, audience: string, body: string, sort_order: int, is_published: bool}>
     */
    public static function articles(): array
    {
        $articles = [];
        $base = resource_path('content/help');

        if (! is_dir($base)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            /** @var array<string, mixed> $article */
            $article = require $file->getPathname();

            if (! isset($article['slug'], $article['title'], $article['body'])) {
                continue;
            }

            $articles[] = [
                'category' => (string) ($article['category'] ?? 'General'),
                'slug' => (string) $article['slug'],
                'title' => (string) $article['title'],
                'summary' => (string) ($article['summary'] ?? ''),
                'audience' => (string) ($article['audience'] ?? 'tenant'),
                'body' => (string) $article['body'],
                'sort_order' => (int) ($article['sort_order'] ?? 0),
                'is_published' => (bool) ($article['is_published'] ?? true),
            ];
        }

        usort($articles, fn ($a, $b) => [$a['category'], $a['sort_order'], $a['title']] <=> [$b['category'], $b['sort_order'], $b['title']]);

        return $articles;
    }
}
