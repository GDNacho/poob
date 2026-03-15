<?php

namespace Gdnacho\Poob\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ValidationException extends HttpException
{
    private array $errors = [];

    public function __construct(array $errors)
    {
        parent::__construct(
            422,
            'Validation error.',
            null,
            [],
            0
        );

        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
