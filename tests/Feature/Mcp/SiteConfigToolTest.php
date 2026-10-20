<?php

declare(strict_types=1);

namespace OpenDxp\Bundle\McpBundle\Tests\Feature\Mcp;

use OpenDxp\Bundle\McpBundle\Mcp\SiteConfigTool;
use OpenDxp\TestFoundation\Container;

it('lists the config locations with the target the installation configures', function () {
    $locations = Container::get(SiteConfigTool::class)('config_locations')['config_locations'];

    expect($locations)->toContain([
        'key'          => 'opendxp.config_location.image_thumbnails',
        'write_target' => 'settings-store',
        'directory'    => null,
        'read_target'  => null,
    ]);
});
