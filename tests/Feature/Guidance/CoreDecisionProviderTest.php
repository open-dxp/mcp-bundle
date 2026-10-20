<?php

declare(strict_types=1);

namespace OpenDxp\Bundle\McpBundle\Tests\Feature\Guidance;

use OpenDxp\Bundle\McpBundle\Guidance\CoreDecisionProvider;
use OpenDxp\Bundle\McpBundle\Guidance\Decision;
use OpenDxp\Bundle\McpBundle\Guidance\Option;
use OpenDxp\Bundle\McpBundle\Tests\Fixtures\RelationType;
use OpenDxp\TestFoundation\Container;

function decision(string $id): Decision
{
    foreach (Container::get(CoreDecisionProvider::class)->getDecisions() as $decision) {
        if ($decision->id === $id) {
            return $decision;
        }
    }

    throw new \RuntimeException(sprintf('There is no decision "%s".', $id));
}

/**
 * @return array<string, Option>
 */
function options(string $id): array
{
    return array_column(array_map(static fn (Option $option): array => [$option->name, $option], decision($id)->options), 1, 0);
}

it('lists the document types of the installation', function () {
    $options = options('document_type');

    expect($options)->toHaveKeys(['page', 'snippet', 'link', 'hardlink', 'email', 'folder', 'fixture'])
        ->and($options['fixture']->when)->toBe('A type that this installation adds. It cannot have children.')
        ->and($options['page']->when)->toContain('It can have a URL of its own.');
});

it('names the areabricks of the installation', function () {
    expect(decision('page_content')->note)->toContain('fixture-brick');
});

it('lists every relation type of the core', function () {
    expect(options('object_relation'))->toHaveKeys([
        'manyToOneRelation',
        'manyToManyRelation',
        'advancedManyToManyRelation',
        'manyToManyObjectRelation',
        'advancedManyToManyObjectRelation',
        'reverseObjectRelation',
    ]);
});

it('lists a relation type that the installation adds', function () {
    expect(options('object_relation')['fixtureRelation']->symbol)->toBe(RelationType::class);
});
