<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\User;
use App\Support\Help\HelpContentLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCentreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->seed(\Database\Seeders\HelpArticleSeeder::class);
        $this->withoutVite();
    }

    public function test_help_articles_loaded_from_content_files(): void
    {
        $expected = count(HelpContentLoader::articles());
        $this->assertGreaterThan(20, $expected);
        $this->assertSame($expected, HelpArticle::count());
    }

    public function test_admin_audience_articles_never_published(): void
    {
        $this->assertSame(0, HelpArticle::where('audience', 'admin')->count());
        $this->assertSame(0, HelpArticle::where('category', 'Admin')->count());
    }

    public function test_help_index_excludes_admin_category(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('categories')
                ->where('categories', fn ($categories) => collect($categories)->pluck('name')->doesntContain('Admin'))
            );
    }

    public function test_guest_sees_tenant_buyer_supplier_articles(): void
    {
        $this->get(route('help.index'))->assertOk();

        $tenantArticle = HelpArticle::where('audience', 'tenant')->first();
        $buyerArticle = HelpArticle::where('audience', 'buyer')->first();

        $this->get(route('help.show', $tenantArticle->slug))->assertOk();
        $this->get(route('help.show', $buyerArticle->slug))->assertOk();
    }

    public function test_buyer_portal_user_only_sees_buyer_articles(): void
    {
        $buyerUser = User::factory()->create([
            'role' => 'buyer_portal',
            'buyer_id' => \App\Models\Buyer::first()->id,
            'account_id' => \App\Models\Account::first()->id,
        ]);

        $tenantSlug = HelpArticle::where('audience', 'tenant')->value('slug');
        $buyerSlug = HelpArticle::where('audience', 'buyer')->value('slug');

        $this->actingAs($buyerUser)
            ->get(route('help.show', $buyerSlug))
            ->assertOk();

        $this->actingAs($buyerUser)
            ->get(route('help.show', $tenantSlug))
            ->assertNotFound();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->get(route('help.show', 'admin-command-center-internal'))->assertNotFound();
    }
}
