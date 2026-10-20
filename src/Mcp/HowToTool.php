<?php

declare(strict_types=1);

/**
 * OpenDXP
 *
 * This source file is licensed under the GNU General Public License version 3 (GPLv3).
 *
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) OpenDXP (https://www.opendxp.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0.html  GNU General Public License version 3 (GPLv3)
 */

namespace OpenDxp\Bundle\McpBundle\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use OpenDxp\Bundle\McpBundle\Guidance\Decision;
use OpenDxp\Bundle\McpBundle\Guidance\DecisionProviderInterface;
use OpenDxp\Bundle\McpBundle\Guidance\Option;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use function array_map;
use function implode;
use function sprintf;

final readonly class HowToTool
{
    /**
     * @param iterable<DecisionProviderInterface> $decisionProviders
     */
    public function __construct(
        #[AutowireIterator(DecisionProviderInterface::TAG)]
        private iterable $decisionProviders,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'how_to',
        description: <<<'TEXT'
        Tells which of several mechanisms is the right one for a given case. The core and the installed bundles
        contribute the questions. Ask this before you model data, build a page or use an extension point of a
        bundle. Without an argument it lists every question. Then ask again with the id of the one that fits.
        TEXT,
    )]
    public function __invoke(
        #[Schema(description: 'Id of a decision, taken from the list this tool returns without arguments, for example "object_field_group".')]
        ?string $decision = null,
    ): array {
        $decisions = [];

        foreach ($this->decisionProviders as $provider) {
            $decisions = [...$decisions, ...$provider->getDecisions()];
        }

        if (null === $decision) {
            return ['decisions' => array_map($this->buildSummary(...), $decisions)];
        }

        foreach ($decisions as $candidate) {
            if ($candidate->id === $decision) {
                return $this->buildDetail($candidate);
            }
        }

        return [
            'error'     => sprintf('There is no decision "%s".', $decision),
            'decisions' => array_map($this->buildSummary(...), $decisions),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildSummary(Decision $decision): array
    {
        return [
            'id'       => $decision->id,
            'question' => $decision->question,
            'options'  => implode(' | ', array_map(static fn (Option $option): string => $option->name, $decision->options)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDetail(Decision $decision): array
    {
        $detail = [
            'question' => $decision->question,
            'options'  => array_map($this->buildOption(...), $decision->options),
        ];

        if (null !== $decision->note) {
            $detail['note'] = $decision->note;
        }

        if (null !== $decision->documentation) {
            $detail['documentation'] = $decision->documentation;
        }

        return $detail;
    }

    /**
     * @return array<string, string>
     */
    private function buildOption(Option $option): array
    {
        $detail = ['option' => $option->name, 'when' => $option->when];

        foreach (['symbol' => $option->symbol, 'next' => $option->next, 'note' => $option->note] as $key => $value) {
            if (null !== $value) {
                $detail[$key] = $value;
            }
        }

        return $detail;
    }
}
