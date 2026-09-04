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

namespace OpenDxp\Bundle\McpBundle\Contribution;

/**
 * Implemented by a bundle class to expose its own MCP tools through the OpenDXP server
 */
interface McpContributorInterface
{
    /**
     * @return list<string>
     */
    public static function getMcpToolSources(): array;
}
