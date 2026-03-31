<?php

namespace Gdnacho\Poob\Input;

use Gdnacho\Poob\Exception\ValidationException;

/**
 * Base class for all Input DTOs.
 */
abstract class InputDto
{
    public function __construct(array $data = [])
    {
        $reflection = new \ReflectionClass($this);
        $typeMap = [
            'int' => 'integer',
            'bool' => 'boolean',
            'float' => 'double',
            'string' => 'string',
            'array' => 'array',
        ];

        $errors = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            $type = $property->getType();
            $required = true;

            if ($type) {
                $required = !$type->allowsNull();
            }

            if ($property->hasDefaultValue()) {
                $required = false;
            }

            // Field missing
            if ($required && !array_key_exists($name, $data)) {
                $errors[] = [
                    'field' => $name,
                    'message' => 'This value is required.',
                ];
                continue; // Skip further checks for this property
            }

            // Assign if provided
            if (array_key_exists($name, $data)) {
                $value = $data[$name];

                // Skip type check if value is null and nullable
                if ($type && null !== $value) {
                    $expected = $type->getName();
                    $expectedGetType = $typeMap[$expected] ?? $expected;

                    if (gettype($value) !== $expectedGetType && 'mixed' !== $expected) {
                        $errors[] = [
                            'field' => $name,
                            'message' => "Wrong type for property $name, expected $expected.",
                        ];
                        continue;
                    }
                }

                $this->$name = $value;
            }
        }

        // Throw a single exception if any errors occurred
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    protected function extra()
    {
    }
}
