<?php

declare(strict_types=1);

namespace OpenDxp\Bundle\McpBundle\Tests\Feature\Mcp;

use OpenDxp\Bundle\McpBundle\Mcp\HowToTool;
use OpenDxp\Model\WebsiteSetting;
use OpenDxp\TestFoundation\Container;

it('lists the decisions of the core and of every other provider', function () {
    $ids = array_column(Container::get(HowToTool::class)()['decisions'], 'id');

    expect($ids)->toContain('object_field_group', 'fixture_value');
});

it('answers a decision by its id', function () {
    expect(Container::get(HowToTool::class)('fixture_value'))->toBe([
        'question' => 'Where does a value live that an editor changes?',
        'options'  => [
            ['option' => 'Website setting', 'when' => 'The value holds for the whole site.', 'symbol' => WebsiteSetting::class],
            ['option' => 'Property', 'when' => 'The value belongs to a document and its children.'],
        ],
        'note' => 'A property is inherited unless a child overrides it.',
    ]);
});

it('names the decisions it knows when the id is unknown', function () {
    expect(Container::get(HowToTool::class)('unknown'))
        ->toHaveKey('error', 'There is no decision "unknown".')
        ->toHaveKey('decisions');
});
