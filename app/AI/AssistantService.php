<?php

namespace App\AI;

use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * The AI business assistant. Builds the system prompt, then delegates the
 * tool-use loop to the configured LlmClient driver (Anthropic or an
 * OpenAI-compatible endpoint such as Groq / Ollama / OpenRouter).
 *
 * Safety: the model only ever chooses from our predefined, read-only,
 * tenant-scoped tools (see AssistantTools) — it cannot run arbitrary queries,
 * mutate data, or reach another tenant.
 */
class AssistantService
{
    public function __construct(
        private AssistantTools $tools,
        private TenantManager $tenants,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->client()->isConfigured();
    }

    /**
     * Answer a natural-language business question.
     *
     * @return array{answer: string, tools_used: array<int, string>}
     */
    public function ask(string $question): array
    {
        $client = $this->client();

        if (! $client->isConfigured()) {
            throw new RuntimeException('Asisten AI belum aktif. Setel kredensial AI di .env (OPENAI_API_KEY untuk Groq/OpenAI-compatible, atau ANTHROPIC_API_KEY).');
        }

        return $client->ask(
            $this->systemPrompt(),
            $question,
            $this->tools->definitions(),
            fn (string $name, array $input): array => $this->tools->run($name, $input),
        );
    }

    /** Resolve the active LLM driver from config('services.ai.provider'). */
    private function client(): LlmClient
    {
        return match (config('services.ai.provider')) {
            'openai' => app(OpenAiClient::class),
            default => app(AnthropicClient::class),
        };
    }

    private function systemPrompt(): string
    {
        $business = $this->tenants->current()?->name ?? config('app.name');
        $today = Carbon::today()->translatedFormat('l, d F Y');

        return <<<PROMPT
        Anda adalah asisten analitik untuk bisnis rental mobil "{$business}".
        Hari ini {$today}.

        Tugas Anda: menjawab pertanyaan pemilik tentang data bisnis mereka
        (pendapatan, booking, okupansi armada, mobil terlaris) menggunakan tool
        yang tersedia. Aturan:
        - Selalu gunakan tool untuk mengambil angka. JANGAN mengarang data.
        - Untuk rentang waktu relatif ("bulan ini", "minggu lalu"), hitung tanggalnya
          sendiri berdasarkan tanggal hari ini, lalu berikan sebagai argumen tool.
        - Jawab dalam Bahasa Indonesia, singkat dan jelas. Format angka rupiah, mis.
          "Rp 12.500.000".
        - Jika data tidak tersedia atau tool tidak mencakup pertanyaan, katakan terus
          terang. Jangan menebak.
        - Anda hanya boleh membahas data bisnis "{$business}".
        PROMPT;
    }
}
