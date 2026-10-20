<?php

declare(strict_types=1);

namespace OpenDxp\Bundle\McpBundle\Tests\Application;

use OpenDxp\Bundle\McpBundle\OpenDxpMcpBundle;
use OpenDxp\HttpKernel\BundleCollection\BundleCollection;
use OpenDxp\TestFoundation\Kernel\TestKernel as Foundation;

final class TestKernel extends Foundation
{
    public function registerBundlesToCollection(BundleCollection $collection): void
    {
        $collection->addBundle(new OpenDxpMcpBundle());
    }
}
