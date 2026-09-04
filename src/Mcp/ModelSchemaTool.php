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
use OpenDxp\Model\DataObject\ClassDefinition;
use OpenDxp\Model\DataObject\ClassDefinition\Data;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Localizedfields;
use OpenDxp\Model\DataObject\ClassDefinition\Listing as ClassDefinitionListing;
use function implode;
use function sprintf;

final class ModelSchemaTool
{
    #[McpTool(
        name: 'model_schema',
        description: 'The DataObject classes of this installation. Without a class name it lists every class; with one it describes every field of that class, including localized fields and the classes a relation may point at. Read this before writing a handler that loads or filters DataObjects.',
    )]
    public function __invoke(
        #[Schema(description: 'Class name, e.g. "EcommerceProduct". Omit it to list the classes first.')]
        ?string $class = null,
    ): array {
        if (null === $class) {
            return ['classes' => $this->classNames()];
        }

        $definition = ClassDefinition::getByName($class);

        if (!$definition instanceof ClassDefinition) {
            return [
                'error' => sprintf('No class "%s" in this installation.', $class),
                'classes' => $this->classNames(),
            ];
        }

        return [
            'class' => $definition->getName(),
            'fields' => $this->fields($definition->getFieldDefinitions()),
        ];
    }

    /**
     * @return list<string>
     */
    private function classNames(): array
    {
        $names = [];

        foreach ((new ClassDefinitionListing())->getClasses() as $definition) {
            $name = $definition->getName();

            if (null !== $name) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @param array<string, Data> $definitions
     *
     * @return array<string, string>
     */
    private function fields(array $definitions, bool $localized = false): array
    {
        $fields = [];

        foreach ($definitions as $definition) {
            if ($definition instanceof Localizedfields) {
                $fields += $this->fields($definition->getFieldDefinitions(), true);

                continue;
            }

            $name = $definition->getName();

            if (null === $name) {
                continue;
            }

            $fields[$name] = $this->describe($definition, $localized);
        }

        return $fields;
    }

    private function describe(Data $definition, bool $localized): string
    {
        $description = $definition->getFieldType();

        if ($localized) {
            $description .= ', localized';
        }

        if ($definition->getMandatory()) {
            $description .= ', mandatory';
        }

        $targets = [...$this->targets($definition), ...$this->elementTypes($definition)];

        if ([] !== $targets) {
            $description .= sprintf(' -> %s', implode('|', $targets));
        }

        return $description;
    }

    /**
     * @return list<string>
     */
    private function elementTypes(Data $definition): array
    {
        $types = [];

        if (method_exists($definition, 'getAssetsAllowed') && true === $definition->getAssetsAllowed()) {
            $types[] = 'assets';
        }

        if (method_exists($definition, 'getDocumentsAllowed') && true === $definition->getDocumentsAllowed()) {
            $types[] = 'documents';
        }

        return $types;
    }

    /**
     * @return list<string>
     */
    private function targets(Data $definition): array
    {
        if (!method_exists($definition, 'getClasses')) {
            return [];
        }

        $targets = [];

        foreach ($definition->getClasses() as $entry) {
            $name = is_array($entry) ? ($entry['classes'] ?? null) : $entry;

            if (is_string($name) && '' !== $name) {
                $targets[] = $name;
            }
        }

        return $targets;
    }
}
