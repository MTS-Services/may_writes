<?php

namespace App\Services;

use App\Models\TrelloTask;
use Illuminate\Support\Facades\Http;

class ClaudeService
{
    public string $apiKey;

    public string $model;

    public string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey = (string) config('services.anthropic.api_key');
        $this->model = (string) config('services.anthropic.model', 'claude-opus-4-5');
    }

    public function summarizeTask(TrelloTask $task): string
    {
        $systemPrompt = 'You are an expert writing project manager. A client has submitted a writing request via their project management board. Analyze the request and produce a clear, structured work brief for the writer to follow. Be specific, actionable, and professional.';

        $userMessage = "Client name: {$task->customer->name}\n"
            ."Plan: {$task->customer->plan?->name}\n"
            ."Card title: {$task->title}\n"
            .'Card description: '.($task->description ?: 'No additional description provided.')
            ."\n\nPlease provide a structured work brief that includes:\n"
            ."1. **Content Type**\n2. **Objective**\n3. **Target Audience**\n4. **Key Points to Cover**\n5. **Tone & Style**\n6. **Suggested Word Count**\n7. **SEO Notes**\n8. **Writer Notes**";

        $response = Http::baseUrl($this->baseUrl)
            ->timeout(45)
            ->connectTimeout(10)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('/messages', [
                'model' => $this->model,
                'max_tokens' => 1500,
                'system' => $systemPrompt,
                'messages' => [[
                    'role' => 'user',
                    'content' => $userMessage,
                ]],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Claude API error: '.$response->body());
        }

        return (string) data_get($response->json(), 'content.0.text', '');
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
