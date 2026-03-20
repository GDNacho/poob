<?php

namespace App\Api\Field;

use Symfony\Component\Validator\Constraints as Assert;

#[\Attribute]
class FieldName extends Assert\Compound
{
    public function getConstraints(array $options): array
    {
        return [
            // new Assert\NotBlank(),
        ];
    }
}
