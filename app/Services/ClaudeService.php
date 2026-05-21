<?php

namespace App\Services;

use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls the Anthropic Messages API (x-api-key). Token and request usage for billing
 * and rate limits appear in the Anthropic Console under API usage, not necessarily
 * the same meters as consumer products such as "Claude Design" in the account dashboard.
 */
class ClaudeService
{
    public string $apiKey;

    public string $model;

    public string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey = (string) config('services.anthropic.api_key');
        $this->model = (string) config('services.anthropic.model', 'claude-opus-4-7');
    }

    /**
     * @return array<string, string>
     */
    public function summarizeVersion(TrelloTask $task, TrelloTaskVersion $version, string $aiContext): array
    {
        $systemPrompt = $this->systemPrompt();
        $userMessage = "Client name: {$task->customer->name}\n"
            .'Plan: '.($task->customer->plan?->name ?? 'N/A')."\n"
            ."Version: {$version->version_number}\n\n"
            ."Request content:\n{$aiContext}";

        $response = Http::baseUrl($this->baseUrl)
            ->timeout(60)
            ->connectTimeout(10)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('/messages', [
                'model' => $this->model,
                'max_tokens' => 3000,
                'system' => $systemPrompt,
                'messages' => [[
                    'role' => 'user',
                    'content' => $userMessage,
                ]],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Claude API error: '.$response->body());
        }

        $usage = $response->json('usage');
        if (is_array($usage)) {
            Log::info('Anthropic API usage', [
                'model' => $this->model,
                'usage' => $usage,
            ]);
        }

        $raw = (string) data_get($response->json(), 'content.0.text', '');

        return $this->parseBriefResponse($raw);
    }

    /**
     * @return array<string, string>
     */
    private function parseBriefResponse(string $raw): array
    {
        $json = $this->extractJson($raw);

        if ($json !== null) {
            return $this->normalizeBrief($json);
        }

        return [
            'title' => 'Writing Brief',
            'description_summary' => $raw,
            'content_type' => '',
            'goal_objective' => '',
            'target_audience' => '',
            'tone_style' => '',
            'length_words' => '',
            'cta_recommendations' => '',
            'references_examples' => '',
            'additional_requirements' => '',
            'writer_notes' => '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJson(string $raw): ?array
    {
        $trimmed = trim($raw);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function normalizeBrief(array $data): array
    {
        $keys = [
            'title',
            'description_summary',
            'content_type',
            'goal_objective',
            'target_audience',
            'tone_style',
            'length_words',
            'cta_recommendations',
            'references_examples',
            'additional_requirements',
            'writer_notes',
        ];

        $brief = [];

        foreach ($keys as $key) {
            $brief[$key] = trim((string) ($data[$key] ?? ''));
        }

        if ($brief['title'] === '') {
            $brief['title'] = 'Writing Brief';
        }

        return $brief;
    }

    private function systemPrompt(): string
    {
        $path = resource_path('prompts/writing-brief-system.md');

        if (File::exists($path)) {
            return trim(File::get($path));
        }

        return 'You are an expert writing project manager. Return JSON only.';
    }

    /**
     * @return array{status:string,model:string}
     */
    public function testConnection(): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout(20)
            ->connectTimeout(10)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('/messages', [
                'model' => $this->model,
                'max_tokens' => 32,
                'messages' => [[
                    'role' => 'user',
                    'content' => 'Reply with OK only.',
                ]],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Claude API connection failed: '.$response->body());
        }

        return ['status' => 'ok', 'model' => $this->model];
    }
}
