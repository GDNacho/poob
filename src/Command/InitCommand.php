<?php

namespace Gdnacho\Poob\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'poob:init')]
class InitCommand extends Command
{
    public function __construct(
        private KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Initialize Poob directories and configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = new Filesystem();

        $projectDir = $this->kernel->getProjectDir();

        // DIRECTORIES
        $dirs = [
            $projectDir.'/src/Api/Field',
            $projectDir.'/src/Api/InputDto',
            $projectDir.'/src/Api/OutputDto',
        ];

        foreach ($dirs as $dir) {
            if (!$fs->exists($dir)) {
                $fs->mkdir($dir);
                $output->writeln("<info>Created directory:</info> $dir");
            }

            $gitkeep = $dir.'/.gitkeep';
            if (!$fs->exists($gitkeep)) {
                $fs->touch($gitkeep);
            }
        }

        // CONFIG
        $configPath = "$projectDir/config/packages/poob.yaml";

        if (!$fs->exists($configPath)) {
            $config = <<<YAML
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
YAML;

            $fs->dumpFile($configPath, $config);
            $output->writeln("<info>Created config:</info> $configPath");
        } else {
            $output->writeln("<comment>Config already exists:</comment> $configPath");
        }

        $output->writeln('<info>Poob initialized successfully.</info>');

        return Command::SUCCESS;
    }
}
