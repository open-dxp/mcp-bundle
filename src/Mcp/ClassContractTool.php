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
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use function count;
use function implode;
use function in_array;
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
            'declaration' => $this->getDeclaration($reflection),
        ];

        if (false !== ($parent = $reflection->getParentClass())) {
            $contract['extends'] = $parent->getName();
        }

        if ([] !== $reflection->getInterfaceNames()) {
            $contract['implements'] = $reflection->getInterfaceNames();
        }

        if ([] !== ($attributes = $this->describeAttributes($reflection->getAttributes()))) {
            $contract['attributes'] = $attributes;
        }

        if ($reflection->isEnum()) {
            $contract += $this->describeEnum(new ReflectionEnum($name));
        }

        if ([] !== ($constants = $this->describeConstants($reflection))) {
            $contract['constants'] = $constants;
        }

        if (null !== $constructor) {
            $contract['constructor'] = $this->describeParameters($constructor);
        }

        if ([] !== ($methods = $this->describeMethods($reflection))) {
            $contract['methods'] = $methods;
        }

        $inherited = $this->getInheritedMethods($reflection);

        if ([] !== $inherited) {
            $contract['inherited'] = $inherited;
        }

        return $contract;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function getDeclaration(ReflectionClass $reflection): string
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
     * @param ReflectionEnum<\UnitEnum> $enum
     *
     * @return array<string, mixed>
     */
    private function describeEnum(ReflectionEnum $enum): array
    {
        $backingType = $enum->getBackingType();
        $cases = [];

        foreach ($enum->getCases() as $case) {
            if ($case instanceof ReflectionEnumBackedCase) {
                $cases[$case->getName()] = $this->describeValue($case->getBackingValue());

                continue;
            }

            $cases[] = $case->getName();
        }

        return null === $backingType
            ? ['cases' => $cases]
            : ['backed_by' => $this->describeType($backingType), 'cases' => $cases];
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return array<string, string>
     */
    private function describeMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName() || $method->isConstructor()) {
                continue;
            }

            // Every enum declares these three itself. They say nothing about this one.
            if ($reflection->isEnum() && in_array($method->getName(), ['cases', 'from', 'tryFrom'], true)) {
                continue;
            }

            $methods[$method->getName()] = sprintf(
                '%s%s(%s): %s',
                $method->isStatic() ? 'static ' : '',
                $method->getName(),
                implode(', ', $this->describeParameters($method)),
                $this->describeType($method->getReturnType()),
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
    private function getInheritedMethods(ReflectionClass $reflection): array
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
    private function describeParameters(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameters[] = sprintf(
                '%s $%s%s',
                $this->describeType($parameter->getType()),
                $parameter->getName(),
                $this->describeDefaultValue($parameter),
            );
        }

        return $parameters;
    }

    private function describeDefaultValue(ReflectionParameter $parameter): string
    {
        if (!$parameter->isDefaultValueAvailable()) {
            return '';
        }

        try {
            $value = $parameter->getDefaultValue();
        } catch (Throwable) {
            return ' = ?';
        }

        return sprintf(' = %s', $this->describeValue($value));
    }

    private function describeValue(mixed $value): string
    {
        return match (true) {
            null === $value => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => sprintf("'%s'", $value),
            is_scalar($value) => (string) $value,
            is_array($value) => [] === $value ? '[]' : sprintf('array(%d)', count($value)),
            default => sprintf('<%s>', get_debug_type($value)),
        };
    }

    private function describeType(?ReflectionType $type): string
    {
        return match (true) {
            $type instanceof ReflectionNamedType => ($type->allowsNull() && 'null' !== $type->getName() && 'mixed' !== $type->getName() ? '?' : '') . $type->getName(),
            $type instanceof ReflectionUnionType => implode('|', array_map(fn (ReflectionType $t): string => $this->describeType($t), $type->getTypes())),
            $type instanceof ReflectionIntersectionType => implode('&', array_map(fn (ReflectionType $t): string => $this->describeType($t), $type->getTypes())),
            default => 'mixed',
        };
    }

    /**
     * @param list<ReflectionAttribute<object>> $attributes
     *
     * @return list<string>
     */
    private function describeAttributes(array $attributes): array
    {
        $described = [];

        foreach ($attributes as $attribute) {
            $arguments = [];

            foreach ($attribute->getArguments() as $key => $value) {
                $rendered = $this->describeValue($value);

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
    private function describeConstants(ReflectionClass $reflection): array
    {
        $constants = [];

        foreach ($reflection->getReflectionConstants() as $constant) {
            if (!$constant->isPublic() || $constant->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            // Enum cases are constants too, and they are reported as cases.
            if ($constant->isEnumCase()) {
                continue;
            }

            $constants[$constant->getName()] = $this->describeValue($constant->getValue());
        }

        return $constants;
    }
}
