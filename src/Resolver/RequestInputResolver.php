<?php

namespace Gdnacho\Poob\Resolver;

use Gdnacho\Poob\Validation\ValidationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RequestInputResolver implements ValueResolverInterface
{
    public function __construct(
        private ValidationService $validator,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();
        $content = $request->getContent();

        /* Get data from body or query params */
        $routeParams = $request->attributes->all();
        $routeParams = array_filter(
            $request->attributes->all(),
            fn ($key) => !str_starts_with($key, '_'),
            ARRAY_FILTER_USE_KEY
        );

        $bodyParams = [];
        $queryParams = $request->query->all();

        if ('' !== $content) {
            $bodyParams = json_decode($content, true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new BadRequestHttpException('Invalid JSON: '.json_last_error_msg());
            }

            $data = \array_merge($routeParams, $bodyParams);
        } else {
            $data = \array_merge($routeParams, $queryParams);
        }
        /* ---------------------------------- */

        // Arrays
        if ('array' === $type) {
            yield $data;

            return;
        }

        // DTOs
        if ($type && class_exists($type)) {
            $dto = new $type();

            foreach ($data as $key => $value) {
                if (property_exists($dto, $key)) {
                    $dto->$key = $value;
                }
            }

            // Validate DTO
            $this->validator->validate($dto);

            // Run custom rules
            if (method_exists($type, 'extra')) {
                $dto->extra();
            }

            yield $dto;
        }
    }
}
