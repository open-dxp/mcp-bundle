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

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use OpenDxp\Bundle\McpBundle\DocumentationSearch;

final readonly class DocsTool
{
    private const int HITS = 8;

    public function __construct(private DocumentationSearch $documentation)
    {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'docs',
        description: 'Searches the OpenDXP documentation and answers with the matching pages and an excerpt from each. Every result carries the url of the page and, where one exists, the url of its raw markdown.',
    )]
    public function __invoke(
        #[Schema(description: 'What to search for, e.g. "thumbnail definition" or "conditional logic".')]
        string $query,
        #[Schema(description: 'Narrow to one bundle, e.g. "headless", "formbuilder", "i18n", or "core-framework" for the core itself. This is the bundle name from the documentation, not the composer package.')]
        ?string $section = null,
    ): array {
        if (!$this->documentation->isAvailable()) {
            return ['error' => 'No documentation site is configured.'];
        }

        $result = $this->documentation->search(trim($query), $section, self::HITS);

        if (null === $result) {
            return ['error' => 'The documentation site did not answer.'];
        }

        $pages = [];

        foreach ($result['hits'] ?? [] as $hit) {
            $pages[] = [
                'title' => $hit['title'],
                'section' => $hit['section'],
                'url' => $this->documentation->getAbsoluteUrl($hit['url']),
                'markdown_url' => null === $hit['markdown_url'] ? null : $this->documentation->getAbsoluteUrl($hit['markdown_url']),
                'excerpt' => $hit['excerpt'],
            ];
        }

        return [
            'query' => $result['query'] ?? $query,
            'pages_matched' => $result['total'] ?? 0,
            'pages' => $pages,
        ];
    }
}
