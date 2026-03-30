<?php

namespace App\Api\InputDto;

use Gdnacho\Poob\Input\InputDto;

// use App\Api\Field;

class InputName extends InputDto
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
