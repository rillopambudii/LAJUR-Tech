<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
    }

    private function owner(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);
    }

    public function test_settings_page_shows_font_and_ui_style_pickers(): void
    {
        $this->actingAs($this->owner())->get('/admin/situs')
            ->assertOk()
            ->assertSee('Gaya Font')
            ->assertSee('Gaya UI')
            ->assertSee('Elegan')
            ->assertSee('Playful');
    }

    public function test_owner_can_set_font_and_ui_style(): void
    {
        $this->actingAs($this->owner())->put('/admin/situs', [
            'font_style' => 'korporat',
            'ui_style' => 'minimalis',
        ])->assertRedirect(route('admin.site.edit'));

        $this->tenant->refresh();
        $this->assertSame('korporat', $this->tenant->font_style);
        $this->assertSame('minimalis', $this->tenant->ui_style);
    }

    public function test_invalid_font_style_is_rejected(): void
    {
        $this->actingAs($this->owner())->put('/admin/situs', [
            'font_style' => 'not-a-real-style',
        ])->assertSessionHasErrors('font_style');
    }

    public function test_invalid_ui_style_is_rejected(): void
    {
        $this->actingAs($this->owner())->put('/admin/situs', [
            'ui_style' => 'not-a-real-style',
        ])->assertSessionHasErrors('ui_style');
    }

    public function test_basic_plan_tenant_can_set_any_style_no_gating(): void
    {
        $basicTenant = Tenant::create(['name' => 'Basic Co', 'slug' => 'basic-co', 'plan' => 'basic', 'subscription_status' => 'active']);
        $basicOwner = User::create([
            'tenant_id' => $basicTenant->id, 'name' => 'Owner', 'email' => 'o@basic-co.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($basicOwner)->put('/admin/situs', [
            'ui_style' => 'lembut',
        ])->assertRedirect(route('admin.site.edit'));

        $this->assertSame('lembut', $basicTenant->fresh()->ui_style);
    }
}
