<?php

declare(strict_types=1);

namespace OpenDxp\Bundle\McpBundle\Tests\Fixtures;

use OpenDxp\Bundle\McpBundle\Guidance\Decision;
use OpenDxp\Bundle\McpBundle\Guidance\DecisionProviderInterface;
use OpenDxp\Bundle\McpBundle\Guidance\Option;

final readonly class DecisionProvider implements DecisionProviderInterface
{
    public function getDecisions(): array
    {
        return [
            new Decision(
                id: 'query_or_command',
                question: 'Query or command?',
                options: [
                    new Option(name: 'Query', when: 'The endpoint reads.', next: 'make:api-query'),
                    new Option(name: 'Command', when: 'The endpoint writes.'),
                ],
                note: 'A command may answer with a body.',
            ),
        ];
    }
}
