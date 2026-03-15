<?php

namespace App\Api\Field;

use Symfony\Component\Validator\Constraints as Assert;

#[\Attribute]
class FieldName extends Assert\Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            // new Assert\NotBlank(),
        ];
    }
}
