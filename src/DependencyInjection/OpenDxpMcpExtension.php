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

use Composer\InstalledVersions;
use OpenDxp\Bundle\McpBundle\OpenDxpMcpBundle;
use Override;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;


class OpenDxpMcpExtension extends Extension implements PrependExtensionInterface
{
    public const string SERVER_NAME = 'opendxp';

    private const string DEFAULT_INSTRUCTIONS = <<<'TEXT'
        The tools of this server read the OpenDXP installation you are working on: its bundles and
        versions, its site configuration, its data model, the contracts of its classes and the
        documentation its bundles ship. Those answers come from the container, the class
        definitions and the database, so they match the installed versions and cannot go stale.

        Call a tool before listing, searching or reading files. Read files only when a tool has
        answered and the answer was not enough.
        TEXT;

    #[Override]
    public function getAlias(): string
    {
        return 'opendxp_mcp';
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(
            new Configuration(),
            $container->getExtensionConfig($this->getAlias()),
        );

        $server = [
            'name' => 'OpenDXP',
            'version' => $this->version(),
            'transports' => [
                'stdio' => true,
                'http' => false,
            ],
            'registry' => [
                'tools' => ['OpenDxp\\Bundle\\McpBundle\\Mcp\\'],
            ],
        ];

        $instructions = $config['instructions'] ?? self::DEFAULT_INSTRUCTIONS;

        if ('' !== trim($instructions)) {
            $server['instructions'] = $instructions;
        }

        $container->prependExtensionConfig('mcp', [
            'servers' => [self::SERVER_NAME => $server],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('opendxp_mcp.docs_url', $configuration['docs_url']);

        $loader = new YamlFileLoader($container, new FileLocator([__DIR__ . '/../../config']));
        $loader->load('services.yaml');
    }

    private function version(): string
    {
        if (!InstalledVersions::isInstalled(OpenDxpMcpBundle::PACKAGE_NAME)) {
            return '0.0.0';
        }

        return (string) InstalledVersions::getPrettyVersion(OpenDxpMcpBundle::PACKAGE_NAME);
    }
}
