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
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use function count;
use function sprintf;
use function strlen;

final class DocsTool
{
    private const string CORE_PACKAGE = 'open-dxp/opendxp';

    private const int EXCERPTS = 5;

    private const int EXCERPT_LENGTH = 700;

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'docs',
        description: 'Searches the documentation that ships with the bundles installed here, so the answer matches the versions in use rather than the current website. Without a query it lists which bundles carry documentation and which do not.',
    )]
    public function __invoke(
        #[Schema(description: 'What to search for, e.g. "thumbnail definition" or "workflow transition". Omit it to see what documentation is available.')]
        ?string $query = null,
        #[Schema(description: 'Restrict the search to one package, e.g. "open-dxp/headless-bundle".')]
        ?string $package = null,
    ): array {
        $sources = $this->sources();

        if (null !== $package) {
            $sources = array_filter($sources, static fn(array $source): bool => $source['package'] === $package);

            if ([] === $sources) {
                return ['error' => sprintf('No documentation installed for "%s".', $package)];
            }
        }

        if (null === $query || '' === trim($query)) {
            return $this->overview($sources);
        }

        return $this->search($sources, trim($query));
    }

    /**
     * @return list<array{package: string, version: string, path: string}>
     */
    private function sources(): array
    {
        $packages = [self::CORE_PACKAGE, ...InstalledVersions::getInstalledPackagesByType('opendxp-bundle')];
        $sources = [];

        foreach (array_unique($packages) as $package) {
            if (!InstalledVersions::isInstalled($package)) {
                continue;
            }

            $installPath = InstalledVersions::getInstallPath($package);

            if (null === $installPath) {
                continue;
            }

            foreach (['doc', 'docs'] as $directory) {
                $path = sprintf('%s/%s', rtrim($installPath, '/'), $directory);

                if (is_dir($path)) {
                    $sources[] = [
                        'package' => $package,
                        'version' => (string) InstalledVersions::getPrettyVersion($package),
                        'path'    => $path,
                    ];

                    break;
                }
            }
        }

        return $sources;
    }

    /**
     * @param list<array{package: string, version: string, path: string}> $sources
     *
     * @return array<string, mixed>
     */
    private function overview(array $sources): array
    {
        $documented = [];

        foreach ($sources as $source) {
            $documented[$source['package']] = sprintf('%s, %d pages', $source['version'], count($this->pages($source['path'])));
        }

        $undocumented = [];

        foreach ([self::CORE_PACKAGE, ...InstalledVersions::getInstalledPackagesByType('opendxp-bundle')] as $package) {
            if (!array_key_exists($package, $documented)) {
                $undocumented[] = $package;
            }
        }

        sort($undocumented);
        ksort($documented);

        return [
            'documented'            => $documented,
            'without_documentation' => $undocumented,
            'note'                  => 'Most bundles exclude their docs from the composer archive. What is missing here is on docs.opendxp.io, but at the current release rather than the installed one.',
        ];
    }

    /**
     * @param list<array{package: string, version: string, path: string}> $sources
     *
     * @return array<string, mixed>
     */
    private function search(array $sources, string $query): array
    {
        $hits = [];

        foreach ($sources as $source) {
            foreach ($this->pages($source['path']) as $page) {
                $content = $page->getContents();
                $count = substr_count(mb_strtolower($content), mb_strtolower($query));

                if (0 === $count) {
                    continue;
                }

                $hits[] = [
                    'package'     => $source['package'],
                    'page'        => $page->getRelativePathname(),
                    'occurrences' => $count,
                    'content'     => $content,
                ];
            }
        }

        usort($hits, static fn(array $a, array $b): int => $b['occurrences'] <=> $a['occurrences']);

        $excerpts = [];

        foreach (array_slice($hits, 0, self::EXCERPTS) as $hit) {
            $excerpts[] = [
                'package' => $hit['package'],
                'page'    => $hit['page'],
                'excerpt' => $this->excerpt($hit['content'], $query),
            ];
        }

        return [
            'query'         => $query,
            'pages_matched' => count($hits),
            'excerpts'      => $excerpts,
            'other_pages'   => array_map(
                static fn(array $hit): string => sprintf('%s: %s', $hit['package'], $hit['page']),
                array_slice($hits, self::EXCERPTS),
            ),
        ];
    }

    /**
     * The markdown section the match sits in, so the excerpt keeps its heading.
     */
    private function excerpt(string $content, string $query): string
    {
        $position = mb_stripos($content, $query);

        if (false === $position) {
            return mb_substr($content, 0, self::EXCERPT_LENGTH);
        }

        $before = mb_substr($content, 0, $position);
        $heading = mb_strrpos($before, "\n#");
        $start = false === $heading ? max(0, $position - 200) : $heading + 1;

        $excerpt = mb_substr($content, $start, self::EXCERPT_LENGTH);

        return strlen($excerpt) < strlen(mb_substr($content, $start)) ? $excerpt . '…' : $excerpt;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function pages(string $path): array
    {
        return iterator_to_array((new Finder())->files()->in($path)->name('*.md')->sortByName(), false);
    }
}
