<?php

namespace Tests\Unit;

use App\Support\Help\HelpContentLoader;
use Tests\TestCase;

class HelpMarkdownTest extends TestCase
{
    public function test_all_articles_have_substantial_body_content(): void
    {
        foreach (HelpContentLoader::articles() as $article) {
            $this->assertGreaterThan(
                400,
                strlen($article['body']),
                "Article \"{$article['slug']}\" body is too short - expand documentation."
            );
            $this->assertStringContainsString('##', $article['body'], "Article {$article['slug']} should use markdown headings.");
        }
    }
}
