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
        private array $docsConfig,
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
            'info' => array_filter([
                'title' => $this->docsConfig['title'],
                'version' => $this->docsConfig['version'],
                'description' => $this->docsConfig['description'] ?? null,
            ]),
            'servers' => array_values(array_filter(
                $this->docsConfig['servers'] ?? [],
                fn ($s) => !empty($s['url'])
            )),
            'paths' => [],
        ];
        $paths = &$openapi['paths'];

        // Build YAML for paths
        $routes = $this->router->getRouteCollection();
        foreach ($routes as $routeName => $route) {
            // Get path
            $path = $route->getPath();
            $prefix = $this->docsConfig['path_prefix'] ?? '/api';
            if (!str_starts_with($path, $prefix)) {
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
                    $operation['responses'] = $this->docsConfig['default_responses'] ?? [
                        '200' => ['description' => 'OK'],
                    ];

                    // Dump Input
                    $reflection = new \ReflectionMethod($class, $methodName);
                    foreach ($reflection->getParameters() as $param) {
                        $type = $param->getType()?->getName();

                        if ($type && str_starts_with($type, 'App\\Api\\InputDto\\')) {
                            $schema = $this->generateSchema($type, $output);

                            if ('get' !== $method) {
                                $operation['requestBody'] = [
                                    'content' => [
                                        'application/json' => [
                                            'schema' => $schema,
                                        ],
                                    ],
                                ];
                            } else {
                                $params = [];

                                foreach ($schema['properties'] as $name => $prop) {
                                    $params[] = [
                                        'name' => $name,
                                        'in' => 'query',
                                        'required' => \in_array($name, $schema['required'] ?? []),
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
        file_put_contents($this->docsConfig['output'], $yaml);
        $output->writeln("<info>Docs generated:</info> " . $this->docsConfig['output']);

        return Command::SUCCESS;
    }

    private function generateSchema(string $dtoClass, $output): array
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

            $schemaProp = [];

            // -------- TYPE --------
            $openApiType = 'string';
            if ($type) {
                $typeName = $type->getName();
                $openApiType = $typeMap[$typeName] ?? 'string';
            }

            $schemaProp['type'] = $openApiType;

            // -------- REQUIRED --------
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

            // -------- ATTRIBUTES --------
            $attributes = $property->getAttributes();

            foreach ($attributes as $attr) {
                $instance = $attr->newInstance();

                // Symfony constraint
                $this->applyConstraint($instance, $schemaProp);

                // Field
                if ($instance instanceof \Symfony\Component\Validator\Constraints\Compound) {
                    /** @var object{getConstraints: callable} $instance */
                    if (method_exists($instance, 'getConstraints')) {
                        foreach ($instance->getConstraints([]) as $constraint) {
                            $this->applyConstraint($constraint, $schemaProp);
                        }
                    }
                }
            }

            $properties[$name] = $schemaProp;
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

    private function applyConstraint(object $constraint, array &$schemaProp): void
    {
        // --------------------
        // LENGTH
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Length) {
            if ($constraint->min !== null) {
                $schemaProp['minLength'] = $constraint->min;
            }
            if ($constraint->max !== null) {
                $schemaProp['maxLength'] = $constraint->max;
            }
        }

        // --------------------
        // RANGE
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Range) {
            if ($constraint->min !== null) {
                $schemaProp['minimum'] = $constraint->min;
            }
            if ($constraint->max !== null) {
                $schemaProp['maximum'] = $constraint->max;
            }
        }

        // --------------------
        // REGEX
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Regex) {
            $pattern = $constraint->pattern;

            // strip delimiters (/.../)
            if (preg_match('#^(.)(.*)\\1[imsxuADSUXJ]*$#', $pattern, $matches)) {
                $pattern = $matches[2];
            }

            $schemaProp['pattern'] = $pattern;
        }

        // --------------------
        // CHOICE (enum)
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Choice) {
            if (is_array($constraint->choices)) {
                $schemaProp['enum'] = $constraint->choices;
            }
        }

        // --------------------
        // EMAIL
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Email) {
            $schemaProp['format'] = 'email';
        }

        // --------------------
        // URL
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Url) {
            $schemaProp['format'] = 'uri';
        }

        // --------------------
        // UUID
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Uuid) {
            $schemaProp['format'] = 'uuid';
        }

        // --------------------
        // DATE
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Date) {
            $schemaProp['format'] = 'date';
        }

        if ($constraint instanceof \Symfony\Component\Validator\Constraints\DateTime) {
            $schemaProp['format'] = 'date-time';
        }

        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Time) {
            $schemaProp['format'] = 'time';
        }

        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Timezone) {
            $schemaProp['format'] = 'timezone';
        }

        // --------------------
        // COUNT (arrays)
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Count) {
            if ($constraint->min !== null) {
                $schemaProp['minItems'] = $constraint->min;
            }
            if ($constraint->max !== null) {
                $schemaProp['maxItems'] = $constraint->max;
            }
        }

        // --------------------
        // POSITIVE / NEGATIVE
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Positive) {
            $schemaProp['minimum'] = 0;
            $schemaProp['exclusiveMinimum'] = true;
        }

        if ($constraint instanceof \Symfony\Component\Validator\Constraints\PositiveOrZero) {
            $schemaProp['minimum'] = 0;
        }

        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Negative) {
            $schemaProp['maximum'] = 0;
            $schemaProp['exclusiveMaximum'] = true;
        }

        if ($constraint instanceof \Symfony\Component\Validator\Constraints\NegativeOrZero) {
            $schemaProp['maximum'] = 0;
        }

        // --------------------
        // DIVISIBLEBY
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\DivisibleBy) {
            $schemaProp['multipleOf'] = $constraint->value;
        }

        // --------------------
        // NOT BLANK
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\NotBlank) {
            $schemaProp['minLength'] = max($schemaProp['minLength'] ?? 0, 1);
        }

        // --------------------
        // ALL
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\All) {
            $schemaProp['items'] = $schemaProp['items'] ?? [];

            foreach ($constraint->constraints as $inner) {
                $this->applyConstraint($inner, $schemaProp['items']);
            }
        }

        // --------------------
        // CONSTRAINT
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Collection) {
            $schemaProp['type'] = 'object';
            $schemaProp['properties'] = [];

            foreach ($constraint->fields as $fieldName => $fieldConstraints) {
                $schemaProp['properties'][$fieldName] = ['type' => 'string']; // default

                foreach ($fieldConstraints->constraints as $inner) {
                    $this->applyConstraint($inner, $schemaProp['properties'][$fieldName]);
                }
            }
        }

        // --------------------
        // UUID
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Uuid) {
            $schemaProp['format'] = 'uuid';

            if ($constraint->versions) {
                $schemaProp['description'] = 'UUID version: ' . implode(',', $constraint->versions);
            }
        }

        // --------------------
        // NOT NULL
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\NotNull) {
            $schemaProp['nullable'] = false;
        }

        // --------------------
        // IS NULL
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\IsNull) {
            $schemaProp['nullable'] = true;
        }

        // --------------------
        // LESS THAN
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\LessThan) {
            $schemaProp['maximum'] = $constraint->value;
            $schemaProp['exclusiveMaximum'] = true;
        }

        // --------------------
        // LESS THAN OR EQUAL
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\LessThanOrEqual) {
            $schemaProp['maximum'] = $constraint->value;
        }

        // --------------------
        // GREATER THAN
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\GreaterThan) {
            $schemaProp['minimum'] = $constraint->value;
            $schemaProp['exclusiveMinimum'] = true;
        }

        // --------------------
        // GREATER THAN OR EQUAL
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\GreaterThanOrEqual) {
            $schemaProp['minimum'] = $constraint->value;
        }

        // --------------------
        // EQUAL TO
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\EqualTo) {
            $schemaProp['enum'] = [$constraint->value];
        }

        // --------------------
        // NOT EQUAL TO
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\NotEqualTo) {
            $schemaProp['not'] = ['enum' => [$constraint->value]];
        }

        // --------------------
        // IDENTICAL TO
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\IdenticalTo) {
            $schemaProp['enum'] = [$constraint->value];
        }

        // --------------------
        // NOT IDENTICAL TO
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\NotIdenticalTo) {
            $schemaProp['not'] = ['enum' => [$constraint->value]];
        }

        // --------------------
        // BLANK
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Blank) {
            $schemaProp['maxLength'] = 0;
        }

        // --------------------
        // COUNTRY
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Country) {
            $schemaProp['format'] = 'country-code';
        }

        // --------------------
        // LANGUAGE
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Language) {
            $schemaProp['format'] = 'language-code';
        }

        // --------------------
        // LOCALE
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Locale) {
            $schemaProp['format'] = 'locale';
        }

        // --------------------
        // JSON
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Json) {
            $schemaProp['type'] = 'object';
        }

        // --------------------
        // IP
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Ip) {
            $schemaProp['format'] = 'ipv4';
        }

        // --------------------
        // FILE
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\File) {
            $schemaProp['type'] = 'string';
            $schemaProp['format'] = 'binary';
        }

        // --------------------
        // IMAGE
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Image) {
            $schemaProp['type'] = 'string';
            $schemaProp['format'] = 'binary';
        }

        // --------------------
        // CARD SCHEME
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\CardScheme) {
            $schemaProp['format'] = 'credit-card';
        }

        // --------------------
        // ISBN
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Isbn) {
            $schemaProp['format'] = 'isbn';
        }

        // --------------------
        // ISSN
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Issn) {
            $schemaProp['format'] = 'issn';
        }

        // --------------------
        // CURRENCY
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Currency) {
            $schemaProp['format'] = 'currency';
        }

        // --------------------
        // BIC
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Bic) {
            $schemaProp['format'] = 'bic';
        }

        // --------------------
        // AT LEAST ONE OF
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\AtLeastOneOf) {
            $schemaProp['anyOf'] = [];

            foreach ($constraint->constraints as $inner) {
                $sub = [];
                $this->applyConstraint($inner, $sub);
                $schemaProp['anyOf'][] = $sub;
            }
        }

        // --------------------
        // SEQUENTIAL (order matters)
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Sequentially) {
            foreach ($constraint->constraints as $inner) {
                $this->applyConstraint($inner, $schemaProp);
            }
        }

        // --------------------
        // UNIQUE
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Unique) {
            $schemaProp['uniqueItems'] = true;
        }

        // --------------------
        // ULID
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Ulid) {
            $schemaProp['format'] = 'ulid';
        }

        // --------------------
        // CSS COLOR
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\CssColor) {
            $schemaProp['format'] = 'color';
        }

        // --------------------
        // HOSTNAME
        // --------------------
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Hostname) {
            $schemaProp['format'] = 'hostname';
        }
    }
}
