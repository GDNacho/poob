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
            if ($required && !\array_key_exists($name, $data)) {
                $errors[] = [
                    'field' => $name,
                    'message' => 'This value is required.',
                ];
                continue;
            }

            if (\array_key_exists($name, $data)) {
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
                                    'message' => \sprintf(
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
                                    'message' => \sprintf(
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
                    [$coerced, $ok] = (function ($value, $expected) {
                        $ok = true;
                        $result = $this->coerceValue($value, $expected, $ok);
                        return [$result, $ok];
                    })($value, $expected);

                    if (!$ok) {
                        $errors[] = [
                            'field' => $name,
                            'message' => "Wrong type for property $name, expected $expected.",
                        ];
                        continue;
                    }

                    $value = $coerced;
                }

                $this->$name = $value;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    private function coerceValue(mixed $value, string $expected, bool &$ok): mixed
    {
        $ok = true;

        switch ($expected) {
            case 'bool':
            case 'boolean':
                if (\is_bool($value)) {
                    return $value;
                }

                if (\is_string($value)) {
                    $lower = strtolower($value);
                    if (in_array($lower, ['true', '1'], true)) return true;
                    if (in_array($lower, ['false', '0'], true)) return false;
                }

                if (\is_int($value)) {
                    if ($value === 1) return true;
                    if ($value === 0) return false;
                }

                $ok = false;
                return null;

            case 'int':
            case 'integer':
                if (\is_int($value)) {
                    return $value;
                }

                if (\is_string($value) && preg_match('/^-?\d+$/', $value)) {
                    return (int) $value;
                }

                $ok = false;
                return null;

            case 'float':
            case 'double':
                if (\is_float($value) || is_int($value)) {
                    return (float) $value;
                }

                if (\is_string($value) && is_numeric($value)) {
                    return (float) $value;
                }

                $ok = false;
                return null;

            case 'string':
                if (\is_scalar($value)) {
                    return (string) $value;
                }

                $ok = false;
                return null;

            case 'array':
                if (\is_array($value)) {
                    return $value;
                }

                $ok = false;
                return null;

            case 'mixed':
                return $value;

            default:
                if ($value instanceof $expected) {
                    return $value;
                }

                $ok = false;
                return null;
        }
    }

    public function extra()
    {
    }
}
