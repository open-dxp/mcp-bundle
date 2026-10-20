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

namespace OpenDxp\Bundle\McpBundle\DependencyInjection\Compiler;

use OpenDxp\Config\LocationAwareConfigRepository;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Throwable;
use function is_string;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

final class CollectConfigLocationsPass implements CompilerPassInterface
{
    public const string PARAMETER = 'opendxp_mcp.config_locations';

    public function process(ContainerBuilder $container): void
    {
        $projectDir = $container->getParameter('kernel.project_dir') . '/';
        $locations = [];

        foreach ($container->getExtensions() as $alias => $extension) {
            if (!$extension instanceof ConfigurationExtensionInterface) {
                continue;
            }

            $configs = $container->getExtensionConfig($alias);

            try {
                $configuration = $extension->getConfiguration($configs, $container);
                $tree = $configuration?->getConfigTreeBuilder()->buildTree();

                if (!$tree instanceof ArrayNode || !isset($tree->getChildren()[LocationAwareConfigRepository::CONFIG_LOCATION])) {
                    continue;
                }

                $config = (new Processor())->processConfiguration($configuration, $container->resolveEnvPlaceholders($configs, true));
            } catch (Throwable) {
                continue;
            }

            foreach ($config[LocationAwareConfigRepository::CONFIG_LOCATION] as $type => $location) {
                $writeTarget = $location[LocationAwareConfigRepository::WRITE_TARGET];
                $directory = $writeTarget[LocationAwareConfigRepository::OPTIONS][LocationAwareConfigRepository::DIRECTORY] ?? null;

                if (is_string($directory) && str_starts_with($directory, $projectDir)) {
                    $directory = substr($directory, strlen($projectDir));
                }

                $locations[] = [
                    'key'          => sprintf('%s.%s.%s', $alias, LocationAwareConfigRepository::CONFIG_LOCATION, $type),
                    'write_target' => $writeTarget[LocationAwareConfigRepository::TYPE],
                    'directory'    => LocationAwareConfigRepository::LOCATION_SYMFONY_CONFIG === $writeTarget[LocationAwareConfigRepository::TYPE] ? $directory : null,
                    'read_target'  => $location[LocationAwareConfigRepository::READ_TARGET][LocationAwareConfigRepository::TYPE] ?? null,
                ];
            }
        }

        $container->setParameter(self::PARAMETER, $locations);
    }
}
