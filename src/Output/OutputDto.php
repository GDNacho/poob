<?php

namespace Gdnacho\Poob\Output;

/**
 * Base class for all Output DTOs.
 *
 * Provides helper methods to create DTOs from objects and collections.
 */
abstract class OutputDto
{
    /**
     * Creates an instance of the DTO from a source object.
     *
     * Tries to match constructor parameters to either:
     * 1. A getter method on the source (e.g., getId())
     * 2. A public property on the source (e.g., $id)
     * 3. Uses default constructor value or null if neither exists
     *
     * @param object $source Source object to map from
     *
     * @return static New instance of the OutputDto
     */
    public static function from(object $source): static
    {
        $reflection = new \ReflectionClass(static::class);
        $ctor = $reflection->getConstructor();

        $args = [];

        foreach ($ctor->getParameters() as $param) {
            $name = $param->getName();
            $ucName = ucfirst($name);

            $value = null;
            $found = false;

            $getters = [
                'get'.$ucName,
                'is'.$ucName,
                'has'.$ucName,
                $ucName,
                $name,
            ];

            foreach ($getters as $getter) {
                if (method_exists($source, $getter)) {
                    $value = $source->$getter();
                    $found = true;
                    break;
                }
            }

            if (!$found && property_exists($source, $name)) {
                $value = $source->$name;
                $found = true;
            }

            if (!$found) {
                $value = $param->isDefaultValueAvailable()
                    ? $param->getDefaultValue()
                    : null;
            }

            // Detect nested DTOs
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if (
                    is_subclass_of($typeName, OutputDto::class) &&
                    $value !== null &&
                    !$value instanceof $typeName
                ) {
                    $value = $typeName::from($value);
                }
            }

            $args[] = $value;
        }

        return new static(...$args);
    }

    /**
     * Converts a collection of objects into an array of DTOs.
     *
     * @param iterable $items Source objects to map
     *
     * @return array Array of OutputDto instances
     */
    public static function collection(iterable $items): array
    {
        $out = [];

        foreach ($items as $item) {
            $out[] = static::from($item);
        }

        return $out;
    }
}
