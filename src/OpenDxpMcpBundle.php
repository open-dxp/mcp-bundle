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

namespace OpenDxp\Bundle\McpBundle;

use OpenDxp\Bundle\McpBundle\DependencyInjection\OpenDxpMcpExtension;
use OpenDxp\Extension\Bundle\AbstractOpenDxpBundle;
use OpenDxp\Extension\Bundle\Traits\PackageVersionTrait;
use OpenDxp\HttpKernel\Bundle\DependentBundleInterface;
use OpenDxp\HttpKernel\BundleCollection\BundleCollection;
use Override;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use function dirname;

class OpenDxpMcpBundle extends AbstractOpenDxpBundle implements DependentBundleInterface
{
    use PackageVersionTrait;

    public const string PACKAGE_NAME = 'open-dxp/mcp-bundle';

    public static function registerDependentBundles(BundleCollection $collection): void
    {
        $collection->addBundle(new McpBundle());
    }

    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new OpenDxpMcpExtension();
        }

        return $this->extension;
    }

    #[Override]
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    protected function getComposerPackageName(): string
    {
        return self::PACKAGE_NAME;
    }
}
