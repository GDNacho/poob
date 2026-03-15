<?php

namespace Gdnacho\Poob\Output;

abstract class OutputDto
{
    public static function from(object $source): static
    {
        $reflection = new \ReflectionClass(static::class);
        $ctor = $reflection->getConstructor();

        $args = [];

        foreach ($ctor->getParameters() as $param) {
            $name = $param->getName();

            $getter = 'get' . ucfirst($name);

            if (method_exists($source, $getter)) {
                $args[] = $source->$getter();
                continue;
            }

            if (property_exists($source, $name)) {
                $args[] = $source->$name;
                continue;
            }

            $args[] = $param->isDefaultValueAvailable()
                ? $param->getDefaultValue()
                : null;
        }

        return new static(...$args);
    }

    public static function collection(iterable $items): array
    {
        $out = [];

        foreach ($items as $item) {
            $out[] = static::from($item);
        }

        return $out;
    }
}