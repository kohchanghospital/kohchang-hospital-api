<?php

namespace Tests\Feature;

use App\Models\HeroSlider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeroSliderApiTest extends TestCase
{
    use RefreshDatabase;

    private function image(string $name): \Illuminate\Http\Testing\File
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        return UploadedFile::fake()->createWithContent($name, file_get_contents(__DIR__.'/../Fixtures/hero.'.$extension));
    }

    public function test_public_only_receives_active_slides_in_order(): void
    {
        HeroSlider::create(['desktop_image' => 'hero-sliders/a.webp', 'title' => 'Second', 'display_order' => 2]);
        HeroSlider::create(['desktop_image' => 'hero-sliders/b.webp', 'title' => 'First', 'display_order' => 1]);
        HeroSlider::create(['desktop_image' => 'hero-sliders/c.webp', 'is_active' => false]);
        $this->getJson('/api/hero-sliders')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.title', 'First');
    }

    public function test_all_admin_actions_require_authentication(): void
    {
        $this->getJson('/api/admin/hero-sliders')->assertUnauthorized();
        $this->postJson('/api/admin/hero-sliders')->assertUnauthorized();
        $this->putJson('/api/admin/hero-sliders/1')->assertUnauthorized();
        $this->deleteJson('/api/admin/hero-sliders/1')->assertUnauthorized();
        $this->postJson('/api/admin/hero-sliders/reorder', ['ids' => [1]])->assertUnauthorized();
    }

    public function test_create_replace_remove_mobile_toggle_and_delete_clean_up_files(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin(User::factory()->create());
        $response = $this->postJson('/api/admin/hero-sliders', [
            'desktop_image' => $this->image('desktop.jpg'),
            'mobile_image' => $this->image('mobile.png'),
            'title' => 'Welcome', 'button_url' => '/th/about', 'alt_text' => 'Hospital',
        ])->assertCreated();
        $slide = $response->json('data');
        Storage::disk('public')->assertExists([$slide['desktop_image'], $slide['mobile_image']]);
        $updated = $this->post('/api/admin/hero-sliders/'.$slide['id'], [
            '_method' => 'PUT', 'desktop_image' => $this->image('new.webp'), 'remove_mobile_image' => '1', 'title' => 'Updated',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.mobile_image', null)->json('data');
        Storage::disk('public')->assertMissing([$slide['desktop_image'], $slide['mobile_image']]);
        Storage::disk('public')->assertExists($updated['desktop_image']);
        $this->putJson('/api/admin/hero-sliders/'.$slide['id'], ['is_active' => false])->assertOk();
        $this->getJson('/api/hero-sliders')->assertJsonCount(0, 'data');
        $this->putJson('/api/admin/hero-sliders/'.$slide['id'], ['is_active' => true])->assertOk();
        $this->getJson('/api/hero-sliders')->assertJsonCount(1, 'data');
        $this->deleteJson('/api/admin/hero-sliders/'.$slide['id'])->assertNoContent();
        Storage::disk('public')->assertMissing($updated['desktop_image']);
        $this->assertDatabaseMissing('hero_sliders', ['id' => $slide['id']]);
    }

    public function test_validation_rejects_unsafe_urls_and_invalid_uploads_without_orphans(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin(User::factory()->create());
        $this->postJson('/api/admin/hero-sliders', [])->assertUnprocessable()->assertJsonValidationErrors('desktop_image');
        $this->postJson('/api/admin/hero-sliders', ['desktop_image' => UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml')])->assertUnprocessable();
        $this->postJson('/api/admin/hero-sliders', ['desktop_image' => $this->image('large.jpg')->size(5121)])->assertUnprocessable();
        foreach (['javascript:alert(1)', '//example.com', '/\\example.com', '/path with spaces', 'data:text/html,x'] as $url) {
            $this->postJson('/api/admin/hero-sliders', ['desktop_image' => $this->image('ok.png'), 'button_url' => $url])->assertUnprocessable()->assertJsonValidationErrors('button_url');
        }
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->postJson('/api/admin/hero-sliders', ['desktop_image' => $this->image('ok.png'), 'button_url' => 'https://example.com/about'])->assertCreated();
    }

    public function test_reorder_is_persisted_and_stale_or_duplicate_lists_are_rejected(): void
    {
        $this->actingAsAdmin(User::factory()->create());
        $first = HeroSlider::create(['desktop_image' => 'hero-sliders/a.webp']);
        $second = HeroSlider::create(['desktop_image' => 'hero-sliders/b.webp']);
        $this->postJson('/api/admin/hero-sliders/reorder', ['ids' => [$second->id, $first->id]])->assertOk()->assertJsonPath('data.0.id', $second->id);
        $this->getJson('/api/hero-sliders')->assertJsonPath('data.0.id', $second->id);
        $this->postJson('/api/admin/hero-sliders/reorder', ['ids' => [$first->id]])->assertStatus(409);
        $this->postJson('/api/admin/hero-sliders/reorder', ['ids' => [$first->id, $first->id]])->assertUnprocessable();
    }

    public function test_failed_database_save_removes_new_uploads(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin(User::factory()->create());
        $this->withoutExceptionHandling();
        HeroSlider::saving(function () { throw new \RuntimeException('Simulated database failure'); });
        try {
            $this->postJson('/api/admin/hero-sliders', ['desktop_image' => $this->image('banner.jpg')]);
            $this->fail('Expected the simulated failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated database failure', $error->getMessage());
        } finally {
            HeroSlider::flushEventListeners();
        }
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('hero_sliders', 0);
    }

    public function test_shared_and_missing_images_are_safe_to_delete(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin(User::factory()->create());
        Storage::disk('public')->put('hero-sliders/shared.webp', 'test');
        $first = HeroSlider::create(['desktop_image' => 'hero-sliders/shared.webp']);
        $second = HeroSlider::create(['desktop_image' => 'hero-sliders/shared.webp', 'mobile_image' => 'hero-sliders/missing.webp']);
        $this->deleteJson('/api/admin/hero-sliders/'.$first->id)->assertNoContent();
        Storage::disk('public')->assertExists('hero-sliders/shared.webp');
        $this->deleteJson('/api/admin/hero-sliders/'.$second->id)->assertNoContent();
        Storage::disk('public')->assertMissing('hero-sliders/shared.webp');
    }
}
