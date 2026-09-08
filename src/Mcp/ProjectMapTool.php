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

use Composer\InstalledVersions;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use function getenv;
use function sprintf;

final class ProjectMapTool
{
    private const string CORE_PACKAGE = 'open-dxp/opendxp';

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'project_map',
        description: 'What this OpenDXP installation actually is: the core version, the PHP version, every installed OpenDXP bundle with its version, and the command that runs its console. Call this before answering anything about the stack, instead of assuming a version from documentation.',
    )]
    public function __invoke(): array
    {
        return [
            'opendxp' => [
                'version'     => $this->getVersion(self::CORE_PACKAGE),
                'php'         => PHP_VERSION,
                'environment' => $this->environment,
            ],
            'console' => $this->getConsoleCommand(),
            'bundles' => $this->getBundles(),
        ];
    }

    private function getConsoleCommand(): string
    {
        if ('true' === getenv('IS_DDEV_PROJECT')) {
            return sprintf('ddev exec -d %s php bin/console', $this->projectDir);
        }

        return sprintf('php %s/bin/console', $this->projectDir);
    }

    /**
     * @return array<string, string>
     */
    private function getBundles(): array
    {
        $bundles = [];

        foreach (InstalledVersions::getInstalledPackagesByType('opendxp-bundle') as $package) {
            $bundles[$package] = $this->getVersion($package);
        }

        ksort($bundles);

        return $bundles;
    }

    private function getVersion(string $package): string
    {
        if (!InstalledVersions::isInstalled($package)) {
            return 'not installed';
        }

        return (string) InstalledVersions::getPrettyVersion($package);
    }
}
