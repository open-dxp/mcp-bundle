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
use ReflectionAttribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use function implode;
use function is_scalar;
use function sprintf;

final class ClassContractTool
{
    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'class_contract',
        description: 'The public surface of a class, interface, trait or enum: constructor dependencies, method signatures, attributes and constants, without any method body. Use it instead of reading a source file when you only need to know how to call something.',
    )]
    public function __invoke(
        #[Schema(description: 'Fully qualified name, e.g. "OpenDxp\\Bundle\\HeadlessBundle\\Transformer\\AssetThumbnailTransformer".')]
        string $class,
    ): array {
        $name = ltrim($class, '\\');

        if (!class_exists($name) && !interface_exists($name) && !trait_exists($name) && !enum_exists($name)) {
            return ['error' => sprintf('"%s" does not exist in this installation.', $name)];
        }

        $reflection = new ReflectionClass($name);
        $constructor = $reflection->getConstructor();

        $contract = [
            'class' => $reflection->getName(),
            'kind' => $this->kind($reflection),
        ];

        if (false !== ($parent = $reflection->getParentClass())) {
            $contract['extends'] = $parent->getName();
        }

        if ([] !== $reflection->getInterfaceNames()) {
            $contract['implements'] = $reflection->getInterfaceNames();
        }

        if ([] !== ($attributes = $this->attributes($reflection->getAttributes()))) {
            $contract['attributes'] = $attributes;
        }

        if ([] !== ($constants = $this->constants($reflection))) {
            $contract['constants'] = $constants;
        }

        if (null !== $constructor) {
            $contract['constructor'] = $this->parameters($constructor);
        }

        $contract['methods'] = $this->methods($reflection);
        $inherited = $this->inherited($reflection);

        if ([] !== $inherited) {
            $contract['inherited'] = $inherited;
        }

        return $contract;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function kind(ReflectionClass $reflection): string
    {
        return match (true) {
            $reflection->isInterface() => 'interface',
            $reflection->isEnum() => 'enum',
            $reflection->isAbstract() => 'abstract class',
            $reflection->isFinal() => 'final class',
            default => 'class',
        };
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return array<string, string>
     */
    private function methods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName() || $method->isConstructor()) {
                continue;
            }

            $methods[$method->getName()] = sprintf(
                '%s%s(%s): %s',
                $method->isStatic() ? 'static ' : '',
                $method->getName(),
                implode(', ', $this->parameters($method)),
                $this->type($method->getReturnType()),
            );
        }

        ksort($methods);

        return $methods;
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return array<string, list<string>>
     */
    private function inherited(ReflectionClass $reflection): array
    {
        $inherited = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            $declaring = $method->getDeclaringClass()->getName();

            if ($declaring === $reflection->getName()) {
                continue;
            }

            $inherited[$declaring][] = $method->getName();
        }

        foreach ($inherited as &$names) {
            sort($names);
        }

        return $inherited;
    }

    /**
     * @return list<string>
     */
    private function parameters(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameters[] = sprintf(
                '%s $%s%s',
                $this->type($parameter->getType()),
                $parameter->getName(),
                $this->defaultValue($parameter),
            );
        }

        return $parameters;
    }

    private function defaultValue(ReflectionParameter $parameter): string
    {
        if (!$parameter->isDefaultValueAvailable()) {
            return '';
        }

        try {
            $value = $parameter->getDefaultValue();
        } catch (Throwable) {
            return ' = ?';
        }

        return sprintf(' = %s', match (true) {
            null === $value => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => '[]',
            is_string($value) => sprintf("'%s'", $value),
            is_scalar($value) => (string) $value,
            default => '?',
        });
    }

    private function type(?ReflectionType $type): string
    {
        return match (true) {
            $type instanceof ReflectionNamedType => ($type->allowsNull() && 'null' !== $type->getName() && 'mixed' !== $type->getName() ? '?' : '') . $type->getName(),
            $type instanceof ReflectionUnionType => implode('|', array_map(fn (ReflectionType $t): string => $this->type($t), $type->getTypes())),
            $type instanceof ReflectionIntersectionType => implode('&', array_map(fn (ReflectionType $t): string => $this->type($t), $type->getTypes())),
            default => 'mixed',
        };
    }

    /**
     * @param list<ReflectionAttribute<object>> $attributes
     *
     * @return list<string>
     */
    private function attributes(array $attributes): array
    {
        $described = [];

        foreach ($attributes as $attribute) {
            $arguments = [];

            foreach ($attribute->getArguments() as $key => $value) {
                $rendered = match (true) {
                    is_string($value) => sprintf("'%s'", $value),
                    is_bool($value) => $value ? 'true' : 'false',
                    is_scalar($value) => (string) $value,
                    default => '…',
                };

                $arguments[] = is_string($key) ? sprintf('%s: %s', $key, $rendered) : $rendered;
            }

            $described[] = [] === $arguments
                ? $attribute->getName()
                : sprintf('%s(%s)', $attribute->getName(), implode(', ', $arguments));
        }

        return $described;
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return array<string, string>
     */
    private function constants(ReflectionClass $reflection): array
    {
        $constants = [];

        foreach ($reflection->getReflectionConstants() as $constant) {
            if (!$constant->isPublic() || $constant->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $value = $constant->getValue();

            $constants[$constant->getName()] = match (true) {
                is_string($value) => sprintf("'%s'", $value),
                is_bool($value) => $value ? 'true' : 'false',
                is_array($value) => sprintf('array(%d)', count($value)),
                is_scalar($value) => (string) $value,
                default => '…',
            };
        }

        return $constants;
    }
}
