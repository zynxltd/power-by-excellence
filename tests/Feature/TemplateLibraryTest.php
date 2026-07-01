<?php

namespace Tests\Feature;

use App\Models\LibraryMessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TemplateLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->seed(\Database\Seeders\MessageTemplateLibrarySeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        require_once base_path('routes/e-delivery.php');
        registerEDeliveryAdminRoutes();

        Route::middleware('web')->get('e-delivery/template-library', [\App\Http\Controllers\Admin\EDeliveryController::class, 'templateLibraryIndex'])
            ->name('e-delivery.template-library.index');

        Route::middleware('web')->post('e-delivery/templates/from-library', [\App\Http\Controllers\Admin\EDeliveryController::class, 'importTemplateFromLibrary'])
            ->name('e-delivery.templates.from-library');

        Route::middleware('web')->post('e-delivery/templates/preview', [\App\Http\Controllers\Admin\EDeliveryController::class, 'previewTemplate'])
            ->name('e-delivery.templates.preview');
    }

    public function test_template_library_index_returns_seeded_templates(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/e-delivery/template-library?vertical_id=solar');

        $response->assertOk()
            ->assertJsonStructure(['templates' => [['id', 'vertical_id', 'channel', 'name', 'preview']]]);

        $templates = $response->json('templates');
        $this->assertNotEmpty($templates);
        $this->assertTrue(collect($templates)->every(fn (array $t) => $t['vertical_id'] === 'solar'));
    }

    public function test_import_from_library_creates_account_message_template(): void
    {
        $account = $this->admin->resolveAccount();
        $library = LibraryMessageTemplate::query()->where('vertical_id', 'solar')->where('channel', 'email')->first();
        $this->assertNotNull($library);

        $response = $this->actingAs($this->admin)
            ->post('/e-delivery/templates/from-library', [
                'library_template_id' => $library->id,
                'name' => 'My solar intro',
            ]);

        $response->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Template added to your account.');

        $this->assertDatabaseHas('message_templates', [
            'account_id' => $account->id,
            'name' => 'My solar intro',
            'channel' => 'email',
            'subject' => $library->subject,
        ]);
    }

    public function test_library_template_preview_renders_merge_tags(): void
    {
        $library = LibraryMessageTemplate::query()->where('vertical_id', 'solar')->where('channel', 'email')->first();
        $this->assertNotNull($library);

        $response = $this->actingAs($this->admin)
            ->postJson('/e-delivery/templates/preview', [
                'subject' => $library->subject,
                'body' => $library->body,
                'html_body' => $library->html_body,
                'preview_data' => $library->preview_data,
            ]);

        $response->assertOk();
        $this->assertStringContainsString('Alex', $response->json('subject'));
        $this->assertStringContainsString('SW1A 1AA', $response->json('body'));
    }
}
