<?php

namespace Tests\Feature;

use App\Tenancy\Domain;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_root_shows_page_induk_marketing(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kelola seluruh operasional armada')
            ->assertSee(route('signup.trial.form'), false)
            ->assertDontSee('Sewa Sekarang'); // itu tombol etalase, bukan page induk
    }

    public function test_landing_shows_struck_price_when_plan_discounted(): void
    {
        \App\Models\Plan::where('key', 'pro')->update(['discount_price' => 999000, 'discount_label' => 'Promo Peluncuran']);

        $this->get('/')
            ->assertOk()
            ->assertSee('999.000')
            ->assertSee('Promo Peluncuran')
            ->assertSee('1.299.000'); // harga asli tetap tampil (dicoret)
    }

    public function test_demo_shows_storefront_with_banner(): void
    {
        $this->get('/demo')
            ->assertOk()
            ->assertSee('demo') // banner "Ini demo..."
            ->assertSee('Sewa Sekarang');
    }

    public function test_domain_central_detection(): void
    {
        // Pusat → page induk.
        $this->assertTrue(Domain::isCentral('lajur.id'));
        $this->assertTrue(Domain::isCentral('www.lajur.id'));
        $this->assertTrue(Domain::isCentral('localhost'));
        $this->assertTrue(Domain::isCentral('127.0.0.1'));
        // Subdomain tenant → etalase.
        $this->assertFalse(Domain::isCentral('rentalku.lajur.id'));
        $this->assertFalse(Domain::isCentral('kaltim-rental.lajur.id'));
    }
}
