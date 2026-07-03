<?php

namespace Tests\Feature;

use App\AI\AssistantService;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'lajur')->firstOrFail();
        app(TenantManager::class)->set($this->tenant);
        config()->set('services.anthropic.api_key', 'test-key');
        config()->set('services.anthropic.model', 'claude-opus-4-8');
        config()->set('services.anthropic.base_url', 'https://api.anthropic.com');
    }

    private function seedRevenue(): void
    {
        $car = Car::create([
            'name' => 'Innova', 'brand' => 'Toyota', 'type' => 'MPV', 'transmission' => 'Automatic',
            'fuel_type' => 'Bensin', 'seats' => 7, 'price_per_day' => 400000,
        ]);
        Booking::create([
            'car_id' => $car->id, 'car_name' => 'Innova', 'customer_name' => 'A', 'customer_email' => 'a@x.id',
            'customer_phone' => '0811', 'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'days' => 2, 'price_per_day' => 400000, 'total_price' => 800000, 'status' => 'confirmed',
        ]);
    }

    public function test_assistant_runs_tool_loop_and_returns_answer(): void
    {
        $this->seedRevenue();

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                // 1st turn: Claude asks to call the business_summary tool.
                ->push([
                    'stop_reason' => 'tool_use',
                    'content' => [
                        ['type' => 'text', 'text' => 'Sebentar, saya cek datanya.'],
                        ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'business_summary', 'input' => []],
                    ],
                ])
                // 2nd turn: Claude answers using the tool result.
                ->push([
                    'stop_reason' => 'end_turn',
                    'content' => [
                        ['type' => 'text', 'text' => 'Pendapatan bulan ini adalah Rp 800.000.'],
                    ],
                ]),
        ]);

        $result = app(AssistantService::class)->ask('Pendapatan bulan ini berapa?');

        $this->assertSame('Pendapatan bulan ini adalah Rp 800.000.', $result['answer']);
        $this->assertContains('business_summary', $result['tools_used']);

        // The request carried our tool definitions and the configured model.
        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->hasHeader('x-api-key', 'test-key')
                && $body['model'] === 'claude-opus-4-8'
                && collect($body['tools'])->pluck('name')->contains('business_summary');
        });
    }

    public function test_openai_compatible_provider_runs_tool_loop(): void
    {
        $this->seedRevenue();
        config()->set('services.ai.provider', 'openai');
        config()->set('services.openai.api_key', 'gsk-test');
        config()->set('services.openai.base_url', 'https://api.groq.com/openai/v1');
        config()->set('services.openai.model', 'llama-3.3-70b-versatile');

        Http::fake([
            'api.groq.com/*' => Http::sequence()
                // 1st: model requests the business_summary tool.
                ->push(['choices' => [['message' => [
                    'role' => 'assistant', 'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_1', 'type' => 'function',
                        'function' => ['name' => 'business_summary', 'arguments' => '{}'],
                    ]],
                ]]]])
                // 2nd: model answers using the tool result.
                ->push(['choices' => [['message' => [
                    'role' => 'assistant', 'content' => 'Pendapatan bulan ini adalah Rp 800.000.',
                ]]]]),
        ]);

        $result = app(AssistantService::class)->ask('Pendapatan bulan ini berapa?');

        $this->assertSame('Pendapatan bulan ini adalah Rp 800.000.', $result['answer']);
        $this->assertContains('business_summary', $result['tools_used']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), 'groq.com/openai/v1/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer gsk-test')
                && $body['model'] === 'llama-3.3-70b-versatile'
                && collect($body['tools'])->pluck('function.name')->contains('business_summary');
        });
    }

    public function test_tool_result_reflects_tenant_scoped_data(): void
    {
        $this->seedRevenue();

        // Capture what the tool actually returns for this tenant.
        $output = app(\App\AI\AssistantTools::class)->run('business_summary', []);

        $this->assertSame(800000, $output['pendapatan']);
        $this->assertSame(1, $output['total_booking']);
    }

    public function test_disabled_without_api_key(): void
    {
        config()->set('services.anthropic.api_key', null);

        $service = app(AssistantService::class);
        $this->assertFalse($service->isConfigured());

        $this->expectException(\RuntimeException::class);
        $service->ask('halo');
    }

    public function test_assistant_page_loads(): void
    {
        $owner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@lajur.id',
            'password' => 'password', 'role' => User::ROLE_OWNER, 'is_admin' => true,
        ]);

        $this->actingAs($owner)->get('/admin/assistant')
            ->assertOk()
            ->assertSee('Asisten AI');
    }
}
