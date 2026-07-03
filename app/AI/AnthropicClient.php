<?php

namespace App\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Claude (Anthropic Messages API) tool-use driver.
 */
class AnthropicClient implements LlmClient
{
    private const MAX_ITERATIONS = 6;
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.api_key'));
    }

    public function ask(string $system, string $question, array $toolDefs, callable $runTool): array
    {
        $messages = [['role' => 'user', 'content' => $question]];
        $toolsUsed = [];

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $response = $this->call($system, $messages, $toolDefs);
            $content = $response['content'] ?? [];

            if (($response['stop_reason'] ?? null) === 'tool_use') {
                $messages[] = ['role' => 'assistant', 'content' => $content];

                $results = [];
                foreach ($content as $block) {
                    if (($block['type'] ?? null) !== 'tool_use') {
                        continue;
                    }
                    $toolsUsed[] = $block['name'];
                    $output = $runTool($block['name'], $block['input'] ?? []);
                    $results[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content' => json_encode($output, JSON_UNESCAPED_UNICODE),
                    ];
                }
                $messages[] = ['role' => 'user', 'content' => $results];

                continue;
            }

            return [
                'answer' => $this->extractText($content),
                'tools_used' => array_values(array_unique($toolsUsed)),
            ];
        }

        throw new RuntimeException('Asisten tidak dapat menyelesaikan jawaban (terlalu banyak langkah).');
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $toolDefs
     * @return array<string, mixed>
     */
    private function call(string $system, array $messages, array $toolDefs): array
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => self::ANTHROPIC_VERSION,
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post(rtrim(config('services.anthropic.base_url'), '/').'/v1/messages', array_filter([
                'model' => config('services.anthropic.model'),
                'max_tokens' => 1024,
                'system' => $system,
                'tools' => $toolDefs ?: null,
                'messages' => $messages,
            ], fn ($v) => $v !== null));

        if ($response->failed()) {
            throw new RuntimeException('AI error: '.($response->json('error.message') ?? 'Gagal menghubungi layanan AI.'));
        }

        return $response->json();
    }

    /**
     * @param array<int, array<string, mixed>> $content
     */
    private function extractText(array $content): string
    {
        $text = collect($content)->where('type', 'text')->pluck('text')->implode("\n");

        return trim($text) !== '' ? trim($text) : 'Maaf, saya tidak dapat menyusun jawaban.';
    }
}
