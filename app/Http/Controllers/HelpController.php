<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    public function index(Request $request): Response
    {
        $isSuperAdmin = $request->user()?->isSuperAdmin() ?? false;

        $articles = HelpArticle::where('is_published', true)
            ->when(! $isSuperAdmin, fn ($q) => $q->whereIn('audience', ['tenant', 'all']))
            ->when($isSuperAdmin, fn ($q) => $q->whereIn('audience', ['admin', 'tenant', 'all']))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        return Inertia::render('Help/Index', [
            'categories' => $articles->map(fn ($items, $category) => [
                'name' => $category,
                'articles' => $items->map(fn ($a) => $a->only(['slug', 'title', 'summary'])),
            ])->values(),
            'search' => $request->input('q'),
        ]);
    }

    public function show(string $slug): Response
    {
        $article = HelpArticle::where('slug', $slug)->where('is_published', true)->firstOrFail();

        $related = HelpArticle::where('category', $article->category)
            ->where('id', '!=', $article->id)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->limit(5)
            ->get(['slug', 'title']);

        return Inertia::render('Help/Show', [
            'article' => $article,
            'related' => $related,
        ]);
    }
}
