<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBrandingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_stores_branding_fields(): void
    {
        $tenant = Tenant::create([
            'name' => 'Kaltim Rental', 'slug' => 'kaltim-rental', 'plan' => 'basic',
            'subscription_status' => 'active',
            'display_name' => 'Kaltim Rental Mobil',
            'tagline' => 'Sewa Mobil Samarinda Termurah',
            'contact_phone' => '+62 811-1111-2222',
            'contact_address' => 'Jl. Juanda No. 1, Samarinda',
            'contact_email' => 'halo@kaltimrental.id',
            'logo_path' => 'logos/x.png',
            'accent_color' => '#2C6E8F',
        ]);

        $fresh = $tenant->fresh();
        $this->assertSame('Kaltim Rental Mobil', $fresh->display_name);
        $this->assertSame('#2C6E8F', $fresh->accent_color);
        $this->assertSame('logos/x.png', $fresh->logo_path);
    }

    public function test_branding_fields_default_to_null(): void
    {
        $tenant = Tenant::create([
            'name' => 'Polos Co', 'slug' => 'polos-co', 'plan' => 'basic',
            'subscription_status' => 'active',
        ]);

        $this->assertNull($tenant->fresh()->display_name);
        $this->assertNull($tenant->fresh()->accent_color);
    }
}
