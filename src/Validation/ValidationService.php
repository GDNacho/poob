<?php

namespace Gdnacho\Poob\Validation;

use Gdnacho\Poob\Exception\ValidationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidationService
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    public function validate(object $input): void
    {
        $violations = $this->validator->validate($input);

        if (\count($violations) > 0) {
            $errors = [];

            foreach ($violations as $v) {
                $errors[] = [
                    'field' => $v->getPropertyPath(),
                    'message' => $v->getMessage(),
                ];
            }

            throw new ValidationException($errors);
        }
    }
}
