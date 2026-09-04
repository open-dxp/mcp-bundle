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
use OpenDxp\Bundle\McpBundle\Contribution\McpContributorInterface;
use OpenDxp\Bundle\McpBundle\OpenDxpMcpBundle;
use Override;
use ReflectionClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

use function dirname;
use function is_a;

class OpenDxpMcpExtension extends Extension implements PrependExtensionInterface
{
    public const string SERVER_NAME = 'opendxp';

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
                'tools' => $this->collectToolSources($container, $config['tools']),
            ],
        ];

        if (null !== $config['instructions']) {
            $server['instructions'] = $config['instructions'];
        }

        $container->prependExtensionConfig('mcp', [
            'servers' => [self::SERVER_NAME => $server],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator([__DIR__ . '/../../config']));
        $loader->load('services.yaml');
    }

    /**
     * @param list<string> $projectSources
     *
     * @return list<string>
     */
    private function collectToolSources(ContainerBuilder $container, array $projectSources): array
    {
        $sources = [];

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        foreach ($bundles as $bundleClass) {
            $sources = [...$sources, ...$this->sourcesOf($bundleClass)];
        }

        return array_values(array_unique([...$sources, ...$projectSources]));
    }

    /**
     * A bundle contributes tools by putting them in its own "Mcp" namespace. That keeps
     * contributing bundles free of any dependency on this one, which matters for bundles that
     * ship without it. McpContributorInterface covers the cases where the convention does not fit.
     *
     * @param class-string $bundleClass
     *
     * @return list<string>
     */
    private function sourcesOf(string $bundleClass): array
    {
        if (is_a($bundleClass, McpContributorInterface::class, true)) {
            return $bundleClass::getMcpToolSources();
        }

        $reflection = new ReflectionClass($bundleClass);
        $file = $reflection->getFileName();

        if (false === $file || !is_dir(dirname($file) . '/Mcp')) {
            return [];
        }

        return [$reflection->getNamespaceName() . '\\Mcp\\'];
    }

    private function version(): string
    {
        if (!InstalledVersions::isInstalled(OpenDxpMcpBundle::PACKAGE_NAME)) {
            return '0.0.0';
        }

        return (string) InstalledVersions::getPrettyVersion(OpenDxpMcpBundle::PACKAGE_NAME);
    }
}
