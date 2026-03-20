<?php

namespace Gdnacho\Poob\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'poob:make:docs')]
class GenerateDocsCommand extends Command
{
    public function __construct(
        private RouterInterface $router,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate documentation for the API');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Poob API',
                'version' => '1.0.0',
            ],
            'paths' => [],
        ];
        $paths = &$openapi['paths'];

        // Build YAML for paths
        $routes = $this->router->getRouteCollection();
        foreach ($routes as $routeName => $route) {
            // Get path
            $path = $route->getPath();
            if (!str_starts_with($path, '/api')) {
                continue;
            }

            // Get methods
            $methods = $route->getMethods();
            if (!$methods) {
                $methods = ['GET'];
            }

            // Get controller
            $controller = $route->getDefault('_controller');

            // Build YAML for this path
            foreach ($methods as $method) {
                $method = strtolower($method);
                $operation = [];

                if ($controller && str_contains($controller, '::')) {
                    // Dump operation ID (Controller)
                    [$class, $methodName] = explode('::', $controller);
                    $shortClass = (new \ReflectionClass($class))->getShortName();
                    $operation['operationId'] = $shortClass.'::'.$methodName;

                    // Dump output (TODO)
                    $operation['responses'] = [
                        '200' => [
                            'description' => 'OK',
                        ],
                    ];

                    // Dump Input
                    $reflection = new \ReflectionMethod($class, $methodName);
                    foreach ($reflection->getParameters() as $param) {
                        $type = $param->getType()?->getName();

                        if ($type && str_starts_with($type, 'App\\Api\\InputDto\\')) {
                            $schema = $this->generateSchema($type);

                            if ('get' !== $method) {
                                $operation['requestBody'] = [
                                    'content' => [
                                        'application/json' => [
                                            'schema' => $schema,
                                        ],
                                    ],
                                ];
                            } else {
                                $schema = $this->generateSchema($type);

                                $params = [];

                                foreach ($schema['properties'] as $name => $prop) {
                                    $params[] = [
                                        'name' => $name,
                                        'in' => 'query',
                                        'required' => false, // we'll improve this later
                                        'schema' => $prop,
                                    ];
                                }

                                $operation['parameters'] = $params;
                            }
                            break;
                        }
                    }
                }

                // Write
                $paths[$path][$method] = $operation;
            }
        }

        $yaml = Yaml::dump($openapi, 10);
        file_put_contents('openapi.yaml', $yaml);

        return Command::SUCCESS;
    }

    private function generateSchema(string $dtoClass): array
    {
        $reflection = new \ReflectionClass($dtoClass);

        $typeMap = [
            'int' => 'integer',
            'bool' => 'boolean',
            'float' => 'number',
            'string' => 'string',
            'array' => 'array',
        ];

        $properties = [];
        $required = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            $type = $property->getType();

            // Determine OpenAPI type
            $openApiType = 'string'; // default
            if ($type) {
                $typeName = $type->getName();
                $openApiType = $typeMap[$typeName] ?? 'string';
            }

            $properties[$name] = [
                'type' => $openApiType,
            ];

            // Track required fields
            $isRequired = true;
            if ($type && $type->allowsNull()) {
                $isRequired = false;
            }
            if ($property->hasDefaultValue()) {
                $isRequired = false;
            }

            if ($isRequired) {
                $required[] = $name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }
}
