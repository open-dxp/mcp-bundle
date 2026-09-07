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
                ->scalarNode('docs_url')
                    ->info('Base URL of a Docusaurus site. Set to null to keep the tool offline.')
                    ->defaultValue('https://docs.opendxp.io')
                ->end()
                ->scalarNode('instructions')
                    ->info('Sent to every client during the handshake, replacing the default text. The place for conventions that hold across the whole project, so they do not have to live in each client\'s own instruction file. An empty string sends none.')
                    ->defaultNull()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
