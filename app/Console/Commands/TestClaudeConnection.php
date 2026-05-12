<?php

namespace App\Console\Commands;

use App\Services\ClaudeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('claude:test')]
#[Description('Test Anthropic Claude API connectivity')]
class TestClaudeConnection extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $result = app(ClaudeService::class)->testConnection();

        $this->info("Claude connection status: {$result['status']}");
        $this->line("Model: {$result['model']}");

        return self::SUCCESS;
    }
}
