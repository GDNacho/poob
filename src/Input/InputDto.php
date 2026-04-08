<?php

namespace Gdnacho\Poob\Input;

use Gdnacho\Poob\Exception\ValidationException;
use BackedEnum;

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
                continue;
            }

            if (array_key_exists($name, $data)) {
                $value = $data[$name];

                if ($type && null !== $value) {
                    $expected = $type->getName();

                    // 🆕 Handle enums
                    if (enum_exists($expected)) {
                        if (is_subclass_of($expected, BackedEnum::class)) {
                            try {
                                $value = $expected::from($value);
                            } catch (\ValueError) {
                                $cases = $expected::cases();
                                $allowed = array_map(fn($c) => $c->value, $cases);

                                $errors[] = [
                                    'field' => $name,
                                    'message' => sprintf(
                                        "Invalid value. Allowed values: %s.",
                                        implode(', ', $allowed)
                                    ),
                                ];
                                continue;
                            }
                        } else {
                            // Unit enum (no backing value)
                            $cases = $expected::cases();
                            $matched = null;

                            foreach ($cases as $case) {
                                if ($case->name === $value) {
                                    $matched = $case;
                                    break;
                                }
                            }

                            if (!$matched) {
                                $cases = $expected::cases();
                                $allowed = array_map(fn($c) => $c->value, $cases);

                                $errors[] = [
                                    'field' => $name,
                                    'message' => sprintf(
                                        "Invalid value. Allowed values: %s.",
                                        implode(', ', $allowed)
                                    ),
                                ];
                                continue;
                            }

                            $value = $matched;
                        }

                        $this->$name = $value;
                        continue;
                    }

                    // Normal scalar type check
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

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    public function extra()
    {
    }
}
