<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    private function ownerOf(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => "owner@{$tenant->slug}.id",
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_default_tenant_home_uses_klasik_font_and_radius_with_no_override(): void
    {
        $response = $this->get('/demo');

        $response->assertOk();
        // Klasik is a no-op: the accent/personalization <style> block only
        // ever emits when there is something non-default to override.
        $response->assertDontSee('--font-display:', false);
        $response->assertDontSee('--radius-pill:', false);
    }

    public function test_elegan_font_style_renders_playfair_display(): void
    {
        $tenant = Tenant::create([
            'name' => 'Kaltim Rental', 'slug' => 'kaltim-rental', 'plan' => 'basic',
            'subscription_status' => 'active', 'font_style' => 'elegan',
        ]);

        $response = $this->actingAs($this->ownerOf($tenant))->get('/demo');

        $response->assertOk();
        $response->assertSee("'Playfair Display'", false);
    }

    public function test_tegas_ui_style_renders_tighter_radius_and_denser_spacing(): void
    {
        $tenant = Tenant::create([
            'name' => 'Kaltim Rental 2', 'slug' => 'kaltim-rental-2', 'plan' => 'basic',
            'subscription_status' => 'active', 'ui_style' => 'tegas',
        ]);

        $response = $this->actingAs($this->ownerOf($tenant))->get('/demo');

        $response->assertOk();
        $response->assertSee('--radius-pill: 8px', false);
        $response->assertSee('padding-block: 72px', false);
    }

    public function test_font_style_and_ui_style_apply_independently_together(): void
    {
        $tenant = Tenant::create([
            'name' => 'Kaltim Rental 3', 'slug' => 'kaltim-rental-3', 'plan' => 'basic',
            'subscription_status' => 'active', 'font_style' => 'korporat', 'ui_style' => 'playful',
        ]);

        $response = $this->actingAs($this->ownerOf($tenant))->get('/demo');

        $response->assertOk();
        $response->assertSee("'Space Grotesk'", false);
        $response->assertSee('--radius-pill: 999px', false);
    }
}
