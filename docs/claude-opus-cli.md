# Claude Opus CLI runbook (MayWrites)

## Production

Laravel calls the Anthropic Messages API using:

- `ANTHROPIC_API_KEY`
- `ANTHROPIC_MODEL` (default `claude-opus-4-7` in `config/services.php`)

Prompt source of truth: `resources/prompts/writing-brief-system.md` (loaded by `App\Services\ClaudeService`).

## Export a card fixture

```bash
php artisan trello:export-card-fixture {trelloCardId}
```

Writes `storage/fixtures/card-{id}.json` with `title`, `description`, `aggregated_content`, and `description_word_count`.

## Local prompt iteration (Claude Code / CLI)

After exporting a fixture:

```bash
claude -p "$(cat resources/prompts/writing-brief-system.md)" --append-user "$(jq -r .aggregated_content storage/fixtures/card-YOUR_CARD_ID.json)"
```

Compare JSON output to the schema in `ClaudeService` before changing production prompts.

## CI

Pest tests mock Anthropic via `Http::fake`. Do not require the CLI in CI.
