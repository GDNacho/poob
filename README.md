# Poob

**Poob** – Input/Output DTO generator, validator, and OpenAPI docs generator.

Quickly create Input DTOs, Output DTOs, and Field definitions using Symfony Validator. Input DTOs are automatically validated through a value resolver, as well as OpenAPI docs generation.

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

Then initialize (This will create the directories /src/Api and /config/packages/poob_api.yaml):
```bash
php bin/console poob:init
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

Optionally, you can also map requests directly to an unvalidated array if the parameter is named `$requestData`:
```php
public function getUser(
    array $requestData,
    UserRepository $userRepository
): JsonResponse {
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

## API Docs generation

You may generate rudimentary OpenAPI documentation for your API with the `poob:make:docs` command.
Poob will scan all your routes (With a prefix of `/api` by default) and Input DTOs schema and validation rules to generate it. $ref is not yet supported.

You can configure your API docs in `config/packages/poob_api.yaml`:
```yaml
poob:
  docs:
    title: 'Poob API'
    version: '1.0.0'
    description: ''

    servers:
      - url: 'http://localhost:8000'
        description: 'Local'
      - url: 'https://api.example.com'
        description: 'Production'

    default_responses:
      '200':
        description: OK

    path_prefix: '/api'
    output: '%kernel.project_dir%/openapi.yaml'
```

### Description & Summary attributes

You may use these attributes atop controllers for the API docs generation:
```php
#[Route('/api/user/{id}', methods: ['GET'], name: 'app_user_get')]
#[Summary('Get user')] // Adds a summary
public function get(string $id, ListUserInput $data): JsonResponse
```

You can also use description for properties in InputDTOs:
```php
class CreateUserInput extends InputDto
{
    #[Field\UsernameField]
    #[Description('Username must be 3-24 characters long')] // Adds a description
    public string $username;
}
```

## Full CRUD controller example

```php
final class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $repo,
    ) {
    }

    #[Route('/api/user/{id}', methods: ['GET'], name: 'app_user_get')]
    #[Summary('Get user')]
    public function get(string $id, ListUserInput $data): JsonResponse
    {
        $user = $this->repo->find($id);
        if (!$user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        return $this->json(ListUserOutput::from($user));
    }

    #[Route('/api/user', methods: ['GET'], name: 'app_user_list')]
    #[Summary('List user')]
    public function list(ListUserInput $data): JsonResponse
    {
        $users = $this->repo->findByFilters($data);

        return $this->json(ListUserOutput::collection($users));
    }

    #[Route('/api/user', methods: ['POST'], name: 'app_user_create')]
    #[Summary('Create user')]
    public function create(CreateUserInput $data, EntityManagerInterface $em): JsonResponse
    {
        $user = new User();
        $user->setUsername($data->username);
        $user->setAge($data->age);
        $user->setEmail($data->email ?? null);

        $em->persist($user);
        $em->flush();

        return $this->json(CreateUserOutput::from($user), 201);
    }

    #[Route('/api/user/{id}', methods: ['PATCH'], name: 'app_user_update')]
    #[Summary('Update user')]
    public function update(string $id, UpdateUserInput $data, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->repo->find($id);
        if (!$user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ($data->username !== null) $user->setUsername($data->username);
        if ($data->age !== null) $user->setAge($data->age);
        if ($data->email !== null) $user->setEmail($data->email);
        $em->flush();

        return $this->json(CreateUserOutput::from($user));
    }

    #[Route('/api/user/{id}', methods: ['DELETE'], name: 'app_user_delete')]
    #[Summary('Delete user')]
    public function delete(string $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->repo->find($id);
        if (!$user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $em->remove($user);
        $em->flush();

        return $this->json('', 204);
    }
}

```

## Contributing

Poob is just a small package with not a lot of thought put into it, mostly for me to use in my own projects. Regardless, contributions as small as just submitting issues are welcome.

*"u gota get a groov!!!"*