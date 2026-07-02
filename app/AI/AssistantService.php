<?php

namespace App\AI;

use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The AI business assistant. Runs a Claude tool-use loop over AssistantTools:
 * Claude decides which read-only, tenant-scoped tool to call, we execute it,
 * feed the result back, and repeat until Claude produces a final answer.
 *
 * Safety: Claude only ever chooses from our predefined tools (see AssistantTools)
 * — it cannot run arbitrary queries, mutate data, or reach another tenant.
 */
class AssistantService
{
    private const MAX_ITERATIONS = 6;
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(
        private AssistantTools $tools,
        private TenantManager $tenants,
    ) {
    }

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.api_key'));
    }

    /**
     * Answer a natural-language business question.
     *
     * @return array{answer: string, tools_used: array<int, string>}
     */
    public function ask(string $question): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Asisten AI belum aktif. Setel ANTHROPIC_API_KEY di .env.');
        }

        $messages = [
            ['role' => 'user', 'content' => $question],
        ];

        $toolsUsed = [];

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $response = $this->call($messages);

            $content = $response['content'] ?? [];

            if (($response['stop_reason'] ?? null) === 'tool_use') {
                // Preserve the assistant turn verbatim (required for the loop).
                $messages[] = ['role' => 'assistant', 'content' => $content];

                $results = [];
                foreach ($content as $block) {
                    if (($block['type'] ?? null) !== 'tool_use') {
                        continue;
                    }

                    $toolsUsed[] = $block['name'];
                    $output = $this->tools->run($block['name'], $block['input'] ?? []);

                    $results[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content' => json_encode($output, JSON_UNESCAPED_UNICODE),
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $results];

                continue; // let Claude read the results and answer
            }

            // Final answer — concatenate text blocks.
            return [
                'answer' => $this->extractText($content),
                'tools_used' => array_values(array_unique($toolsUsed)),
            ];
        }

        throw new RuntimeException('Asisten tidak dapat menyelesaikan jawaban (terlalu banyak langkah).');
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    private function call(array $messages): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => self::ANTHROPIC_VERSION,
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post(rtrim(config('services.anthropic.base_url'), '/').'/v1/messages', [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 1024,
                'system' => $this->systemPrompt(),
                'tools' => $this->tools->definitions(),
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'Gagal menghubungi layanan AI.';
            throw new RuntimeException('AI error: '.$message);
        }

        return $response->json();
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

    /**
     * @param array<int, array<string, mixed>> $content
     */
    private function extractText(array $content): string
    {
        $text = collect($content)
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return trim($text) !== '' ? trim($text) : 'Maaf, saya tidak dapat menyusun jawaban.';
    }
}
