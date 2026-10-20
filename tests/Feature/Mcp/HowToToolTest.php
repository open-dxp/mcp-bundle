<?php

declare(strict_types=1);

namespace OpenDxp\Bundle\McpBundle\Tests\Feature\Mcp;

use OpenDxp\Bundle\McpBundle\Mcp\HowToTool;
use OpenDxp\TestFoundation\Container;

it('lists the decisions of every provider', function () {
    expect(Container::get(HowToTool::class)())->toBe([
        'decisions' => [
            ['id' => 'query_or_command', 'question' => 'Query or command?', 'options' => 'Query | Command'],
        ],
    ]);
});

it('answers a decision by its id', function () {
    expect(Container::get(HowToTool::class)('query_or_command'))->toBe([
        'question' => 'Query or command?',
        'options'  => [
            ['option' => 'Query', 'when' => 'The endpoint reads.', 'next' => 'make:api-query'],
            ['option' => 'Command', 'when' => 'The endpoint writes.'],
        ],
        'note' => 'A command may answer with a body.',
    ]);
});

it('names the decisions it knows when the id is unknown', function () {
    expect(Container::get(HowToTool::class)('unknown'))
        ->toHaveKey('error', 'There is no decision "unknown".')
        ->toHaveKey('decisions');
});
