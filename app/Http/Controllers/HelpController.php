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

        $articles = HelpArticle::where('is_published', true)
            ->whereIn('audience', $audiences)
            ->where('category', '!=', 'Admin')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('title')
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

    public function show(Request $request, string $slug): Response
    {
        $article = HelpArticle::where('slug', $slug)->where('is_published', true)->first();

        if (! $article || ! in_array($article->audience, $this->allowedAudiences($request->user()), true)) {
            throw new NotFoundHttpException;
        }

        $related = HelpArticle::where('category', $article->category)
            ->where('id', '!=', $article->id)
            ->where('is_published', true)
            ->whereIn('audience', $this->allowedAudiences($request->user()))
            ->orderBy('sort_order')
            ->limit(5)
            ->get(['slug', 'title']);

        return Inertia::render('Help/Show', [
            'article' => $article,
            'related' => $related,
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

        // Tenant staff + account admins (+ super admin browsing help)
        return ['tenant', 'buyer', 'supplier', 'all'];
    }
}
