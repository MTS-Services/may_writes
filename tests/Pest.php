<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @return array<int, array<string, mixed>>
 */
function trelloTemplateListFixtures(): array
{
    $lists = [];

    foreach (config('trello_template.lists', []) as $key => $name) {
        $lists[] = [
            'id' => 'list_'.$key,
            'name' => $name,
            'closed' => false,
        ];
    }

    return $lists;
}

/**
 * @return array<int, array<string, mixed>>
 */
function trelloTemplateInstructionCardFixtures(): array
{
    $cards = [];

    foreach (config('trello_template.instruction_cards', []) as $slug => $definition) {
        if (! is_array($definition)) {
            continue;
        }

        $cards[] = [
            'id' => 'card_'.$slug,
            'idList' => 'list_'.($definition['list_key'] ?? 'requests'),
            'name' => $definition['name'] ?? '',
        ];
    }

    return $cards;
}

/**
 * @return array<int, array<string, mixed>>
 */
function trelloDefaultKanbanListFixtures(): array
{
    return trelloTemplateListFixtures();
}

/**
 * @return array<mixed>|null
 */
function trelloTemplateStructureHttpResponse(Request $request): ?array
{
    $url = $request->url();
    $method = $request->method();

    if (
        $method === 'GET'
        && preg_match('#/boards/([^/]+)$#', parse_url($url, PHP_URL_PATH) ?: '', $boardMatches)
        && ! str_contains($url, '/lists')
        && ! str_contains($url, '/cards')
        && ! str_contains($url, '/labels')
        && ! str_contains($url, '/members')
    ) {
        return [
            'id' => $boardMatches[1],
            'closed' => false,
            'shortUrl' => 'https://trello.com/b/'.$boardMatches[1],
        ];
    }

    if ($method === 'GET' && str_contains($url, '/boards/') && str_contains($url, '/lists')) {
        return trelloTemplateListFixtures();
    }

    if ($method === 'GET' && str_contains($url, '/boards/') && str_contains($url, '/cards') && ! str_contains($url, '/actions')) {
        return trelloTemplateInstructionCardFixtures();
    }

    if ($method === 'GET' && str_contains($url, '/boards/') && str_contains($url, '/labels')) {
        return [];
    }

    if ($method === 'PUT' && str_contains($url, '/lists/')) {
        return ['id' => 'list_ok'];
    }

    if ($method === 'PUT' && preg_match('#/boards/[^/]+$#', parse_url($url, PHP_URL_PATH) ?: '')) {
        return ['id' => 'board_ok'];
    }

    if ($method === 'POST' && str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/lists')) {
        return ['id' => 'list_created'];
    }

    if ($method === 'POST' && str_contains($url, '/cards') && ! str_contains($url, '/idLabels')) {
        return ['id' => 'card_created'];
    }

    if ($method === 'POST' && str_contains($url, '/idLabels')) {
        return [];
    }

    return null;
}
