<?php

declare(strict_types=1);

namespace OpenDxp\Bundle\McpBundle\Tests\Fixtures;

use OpenDxp\Bundle\McpBundle\Guidance\Decision;
use OpenDxp\Bundle\McpBundle\Guidance\DecisionProviderInterface;
use OpenDxp\Bundle\McpBundle\Guidance\Option;
use OpenDxp\Model\WebsiteSetting;

final readonly class DecisionProvider implements DecisionProviderInterface
{
    public function getDecisions(): array
    {
        return [
            new Decision(
                id: 'fixture_value',
                question: 'Where does a value live that an editor changes?',
                options: [
                    new Option(name: 'Website setting', when: 'The value holds for the whole site.', symbol: WebsiteSetting::class),
                    new Option(name: 'Property', when: 'The value belongs to a document and its children.'),
                ],
                note: 'A property is inherited unless a child overrides it.',
            ),
        ];
    }
}
