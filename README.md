# Poob

**Poob** – Input/Output DTO generator, validator, and lightweight API scaffolding helper for Symfony.

Poob allows you to quickly create Input DTOs, Output DTOs, and Field definitions using Symfony Validator. Input DTOs are automatically validated through a value resolver. In the future, Poob will also provide a command to generate OpenAPI documentation by scanning your routes.

The goal is to provide a less opinionated micro-framework than API Platform, and a bundle like Nelmio/ApiDocBundle without the annotation boilerplate.


---

## Features

- Generate Input DTOs (`poob:make:input-dto <name>`)  
- Generate Output DTOs (`poob:make:output-dto <name>`)  
- Generate Field definitions (`poob:make:field <name>`)  
- Automatic request validation using a value resolver (`RequestInputResolver`)  
- Organizes generated classes under `/src/Api`:
  - `/InputDto`
  - `/OutputDto`
  - `/Field`  
- Generate API docs (`poob:make:docs`)  

---

## Installation

Add Poob to your Symfony project via Composer:

```bash
composer require gdnacho/poob
```

## Usage

### Controller
```php
use App\Api\InputDto\UsernameInput;
use App\Api\OutputDto\UsernameOutput;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/users', name: 'app_user_get')]
public function getUser(
    UsernameInput $data,
    UserRepository $userRepository
): JsonResponse {
    // Find the user entity using the input DTO
    $user = $userRepository->findOneBy(['username' => $data->username]);

    // Return a response DTO, serialized to JSON automatically
    return $this->json(
        $user ? UsernameOutput::from($user) : ['error' => 'User not found']
    );
}
```
- `$data` is automatically populated from the request and validated, using the UsernameInput DTO.
  - For GET requests, Poob reads the query parameters.
  - For POST, PUT, PATCH, or other request methods, Poob parses and validates the JSON request body.
- If validation fails, the value resolver throws a `ValidationException`, which can be handled by an event listener.
- The `from()` method from the Output DTO takes any object and serializes it. This example, thus, responds:
```json
{
    "username": "string"
}
```

### Input DTO
```php
// src/Api/InputDto/UsernameInput.php
class UsernameInput
{
    #[Field\UsernameField]
    public $username;

    /**
     * This method runs after attribute validation and allows
     * implementing custom logic that depends on multiple fields, or mutate data as needed.
     */
    public function extra(): void
    {
    }
}
```

### Field
```php
// src/Api/Field/UsernameField.php
#[\Attribute]
class UsernameField extends Assert\Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Type('string'),
            new Assert\Length(min: 2, max: 24),
            new Assert\Regex(
                pattern: '/^[A-Za-z0-9_]+$/',
                message: 'Username may only contain letters, numbers, and underscores.'
            ),
        ];
    }
}
```

### Output DTO
```php
// src/Api/OutputDto/UsernameOutput.php
class UsernameOutput extends OutputDto
{
    public function __construct(
        public $username,
    ) {
    }
}
```

#### Output DTO Helpers

All Output DTOs should extend OutputDto. This provides two convenient static methods:
- `from(object $source): static`: Creates a new DTO from any object, such as entities.
- `collection(iterable $items): array`: Converts a list of objects into an array of DTOs. Uses from() internally for each item.

## Contributing

Poob is just a small package with not a lot of thought put into it, mostly for me to use in my own projects. Regardless, contributions as small as just submitting issues are welcome.

*"u gota get a groov!!!"*