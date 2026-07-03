<?php

namespace App\AI;

/**
 * A chat LLM that supports tool/function calling. Implementations run the full
 * tool-use loop for a single question and return the final answer.
 */
interface LlmClient
{
    public function isConfigured(): bool;

    /**
     * @param  array<int, array<string, mixed>>  $toolDefs  Canonical tool specs
     *         (name, description, input_schema) — the client adapts them to its
     *         own wire format.
     * @param  callable(string, array<string,mixed>): array<string,mixed>  $runTool
     *         Executes a tool by name with the model-supplied arguments.
     * @return array{answer: string, tools_used: array<int, string>}
     */
    public function ask(string $system, string $question, array $toolDefs, callable $runTool): array;
}
