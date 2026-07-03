<?php

namespace App\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI-compatible chat-completions driver (Groq, Ollama, OpenRouter,
 * LM Studio, OpenAI, …). Uses the `/chat/completions` endpoint with `tools`.
 */
class OpenAiClient implements LlmClient
{
    private const MAX_ITERATIONS = 6;

    public function isConfigured(): bool
    {
        return filled(config('services.openai.api_key'));
    }

    public function ask(string $system, string $question, array $toolDefs, callable $runTool): array
    {
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $question],
        ];
        $tools = $this->convertTools($toolDefs);
        $toolsUsed = [];

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $message = $this->call($messages, $tools)['choices'][0]['message'] ?? [];
            $calls = $message['tool_calls'] ?? [];

            if (! empty($calls)) {
                // Echo the assistant turn (with tool_calls) back verbatim.
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $message['content'] ?? '',
                    'tool_calls' => $calls,
                ];

                foreach ($calls as $call) {
                    $name = $call['function']['name'] ?? '';
                    $args = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];
                    $toolsUsed[] = $name;
                    $output = $runTool($name, is_array($args) ? $args : []);
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'] ?? '',
                        'content' => json_encode($output, JSON_UNESCAPED_UNICODE),
                    ];
                }

                continue;
            }

            $answer = trim((string) ($message['content'] ?? ''));

            return [
                'answer' => $answer !== '' ? $answer : 'Maaf, saya tidak dapat menyusun jawaban.',
                'tools_used' => array_values(array_unique($toolsUsed)),
            ];
        }

        throw new RuntimeException('Asisten tidak dapat menyelesaikan jawaban (terlalu banyak langkah).');
    }

    /**
     * Adapt canonical tool specs to OpenAI's function-tool shape.
     *
     * @param array<int, array<string, mixed>> $toolDefs
     * @return array<int, array<string, mixed>>
     */
    private function convertTools(array $toolDefs): array
    {
        return array_map(fn ($d) => [
            'type' => 'function',
            'function' => [
                'name' => $d['name'],
                'description' => $d['description'],
                'parameters' => $d['input_schema'],
            ],
        ], $toolDefs);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @return array<string, mixed>
     */
    private function call(array $messages, array $tools): array
    {
        $url = rtrim(config('services.openai.base_url'), '/').'/chat/completions';
        $payload = [
            'model' => config('services.openai.model'),
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'temperature' => 0.2,
            'max_tokens' => 1024,
        ];

        for ($attempt = 0; ; $attempt++) {
            $response = Http::withToken(config('services.openai.api_key'))
                ->acceptJson()->timeout(60)->post($url, $payload);

            // Free tiers (e.g. Groq) enforce tokens-per-minute limits — back off and retry.
            if ($response->status() === 429 && $attempt < 2) {
                usleep(1_500_000);

                continue;
            }
            break;
        }

        if ($response->failed()) {
            throw new RuntimeException('AI error: '.($response->json('error.message') ?? 'Gagal menghubungi layanan AI.'));
        }

        return $response->json();
    }
}
