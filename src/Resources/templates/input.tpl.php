<?php

namespace App\Api\InputDto;

use Symfony\Component\Validator\Constraints as Assert;
use App\Api\Field;

class InputName
{
    // public mixed $attribute;

    /**
     * This method runs after attribute validation and allows
     * implementing custom logic that depends on multiple fields, or mutate data as needed.
     */
    public function extra(): void
    {
    }
}