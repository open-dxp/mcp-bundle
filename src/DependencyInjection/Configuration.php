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

namespace OpenDxp\Bundle\McpBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('opendxp_mcp');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('tools')
                    ->info('Tool sources contributed by the project itself: service ids, FQCNs or namespace prefixes (trailing backslash). Bundles are picked up through their own "Mcp" namespace and do not belong here.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('instructions')
                    ->info('Sent to every client during the handshake. The place for conventions that hold across the whole project, so they do not have to live in each client\'s own instruction file.')
                    ->defaultNull()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
