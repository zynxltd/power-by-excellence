<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HelpController extends Controller
{
    public function index(Request $request): Response
    {
        $audiences = $this->allowedAudiences($request->user());
        $defaultAudience = $this->defaultAudience($request->user());

        $articles = HelpArticle::where('is_published', true)
            ->whereIn('audience', $audiences)
            ->where('category', '!=', 'Admin')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $grouped = $articles->groupBy('category');

        return Inertia::render('Help/Index', [
            'categories' => $grouped->map(fn ($items, $category) => [
                'name' => $category,
                'audience' => $items->first()->audience,
                'articles' => $items->map(fn ($a) => $a->only(['slug', 'title', 'summary', 'audience'])),
            ])->values(),
            'audienceFilters' => $this->audienceFilters($audiences, $articles),
            'defaultAudience' => $defaultAudience,
            'learningPaths' => $this->learningPaths($audiences),
            'featured' => $this->featuredArticles($articles, $audiences),
            'search' => $request->input('q'),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $article = HelpArticle::where('slug', $slug)->where('is_published', true)->first();

        if (! $article || ! in_array($article->audience, $this->allowedAudiences($request->user()), true)) {
            throw new NotFoundHttpException;
        }

        $siblings = HelpArticle::where('category', $article->category)
            ->where('is_published', true)
            ->whereIn('audience', $this->allowedAudiences($request->user()))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'slug', 'title']);

        $index = $siblings->search(fn ($a) => $a->slug === $article->slug);

        return Inertia::render('Help/Show', [
            'article' => array_merge($article->only(['slug', 'title', 'summary', 'category', 'body', 'audience']), [
                'tableOfContents' => $this->tableOfContents($article->body),
            ]),
            'related' => HelpArticle::where('category', $article->category)
                ->where('id', '!=', $article->id)
                ->where('is_published', true)
                ->whereIn('audience', $this->allowedAudiences($request->user()))
                ->orderBy('sort_order')
                ->limit(5)
                ->get(['slug', 'title']),
            'navigation' => [
                'prev' => $index > 0 ? $siblings[$index - 1]->only(['slug', 'title']) : null,
                'next' => $index !== false && $index < $siblings->count() - 1 ? $siblings[$index + 1]->only(['slug', 'title']) : null,
            ],
            'categoryArticles' => $siblings->map(fn ($a) => [
                'slug' => $a->slug,
                'title' => $a->title,
                'current' => $a->slug === $article->slug,
            ]),
        ]);
    }

    /**
     * @return list<string>
     */
    protected function allowedAudiences(?User $user): array
    {
        if (! $user) {
            return ['tenant', 'buyer', 'supplier', 'all'];
        }

        if ($user->isBuyerPortal()) {
            return ['buyer', 'all'];
        }

        if ($user->isSupplierPortal()) {
            return ['supplier', 'all'];
        }

        return ['tenant', 'buyer', 'supplier', 'all'];
    }

    protected function defaultAudience(?User $user): string
    {
        if (! $user) {
            return 'all';
        }

        if ($user->isBuyerPortal()) {
            return 'buyer';
        }

        if ($user->isSupplierPortal()) {
            return 'supplier';
        }

        return 'tenant';
    }

    /**
     * @param  list<string>  $audiences
     * @return list<array{id: string, label: string, count: int}>
     */
    protected function audienceFilters(array $audiences, $articles): array
    {
        $labels = [
            'all' => 'All guides',
            'tenant' => 'Platform admin',
            'buyer' => 'Buyer portal',
            'supplier' => 'Supplier portal',
        ];

        $counts = $articles->groupBy('audience')->map->count();

        return collect($audiences)
            ->map(fn ($id) => [
                'id' => $id,
                'label' => $labels[$id] ?? ucfirst($id),
                'count' => $id === 'all'
                    ? $articles->count()
                    : (int) ($counts[$id] ?? 0) + ($id !== 'all' ? (int) ($counts['all'] ?? 0) : 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $audiences
     * @return list<array{id: string, title: string, description: string, slugs: list<string>}>
     */
    protected function learningPaths(array $audiences): array
    {
        $paths = [
            [
                'id' => 'tenant-start',
                'audience' => 'tenant',
                'title' => 'Launch your first campaign',
                'description' => 'From login to selling a test lead in under 30 minutes.',
                'slugs' => ['welcome', 'quick-start', 'campaign-setup', 'lead-ingest', 'delivery-methods'],
            ],
            [
                'id' => 'tenant-ops',
                'audience' => 'tenant',
                'title' => 'Operations & troubleshooting',
                'description' => 'Reports, quarantine, validation, and day-to-day monitoring.',
                'slugs' => ['reports', 'lead-validation', 'lead-quarantine', 'api-keys', 'automation-responders'],
            ],
            [
                'id' => 'buyer-start',
                'audience' => 'buyer',
                'title' => 'Buyer portal essentials',
                'description' => 'Login, leads, downloads, and billing.',
                'slugs' => ['buyer-portal-overview', 'buyer-portal-login', 'buyer-portal-leads', 'buyer-portal-billing'],
            ],
            [
                'id' => 'supplier-start',
                'audience' => 'supplier',
                'title' => 'Supplier tracking & payouts',
                'description' => 'SIDs, postbacks, CSV exports, and portal basics.',
                'slugs' => ['supplier-portal-overview', 'supplier-portal-login', 'supplier-tracking-sids', 'supplier-portal-payouts'],
            ],
        ];

        return collect($paths)
            ->filter(fn ($path) => in_array($path['audience'], $audiences, true) || in_array('all', $audiences, true))
            ->map(fn ($path) => [
                'id' => $path['id'],
                'audience' => $path['audience'],
                'title' => $path['title'],
                'description' => $path['description'],
                'slugs' => $path['slugs'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{slug: string, title: string, summary: string, audience: string}>
     */
    protected function featuredArticles($articles, array $audiences): array
    {
        $featuredSlugs = ['quick-start', 'campaign-setup', 'delivery-methods', 'buyer-portal-overview', 'supplier-portal-overview', 'automation-responders'];

        return $articles
            ->filter(fn ($a) => in_array($a->slug, $featuredSlugs, true))
            ->sortBy(fn ($a) => array_search($a->slug, $featuredSlugs, true))
            ->map(fn ($a) => $a->only(['slug', 'title', 'summary', 'audience']))
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, level: int, text: string}>
     */
    protected function tableOfContents(string $body): array
    {
        $items = [];
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '## ')) {
                $text = substr($trimmed, 3);
                $items[] = ['id' => $this->headingId($text), 'level' => 2, 'text' => $text];
            } elseif (str_starts_with($trimmed, '### ')) {
                $text = substr($trimmed, 4);
                $items[] = ['id' => $this->headingId($text), 'level' => 3, 'text' => $text];
            }
        }

        return $items;
    }

    protected function headingId(string $text): string
    {
        $id = strtolower($text);
        $id = preg_replace('/[^a-z0-9\s-]/', '', $id) ?? $id;
        $id = preg_replace('/\s+/', '-', trim($id)) ?? $id;

        return $id ?: 'section';
    }
}
