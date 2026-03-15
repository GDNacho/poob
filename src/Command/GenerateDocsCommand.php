<?php

namespace Gdnacho\Poob\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'poob:make:docs')]
class GenerateDocsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Generate documentation for the API');
    }

    protected function execute($input, $output): int
    {
        $output->writeln('TODO lol');

        return Command::SUCCESS;
    }
}
