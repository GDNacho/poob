<?php

namespace Gdnacho\Poob\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Gdnacho\Poob\Generator\Paths;

#[AsCommand(name: 'poob:make:field')]
class MakeFieldCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Create a custom field for validation')
            ->addArgument('name', InputArgument::REQUIRED, 'Field class name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $class = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        $dir = 'src/Api/Field';
        $path = "$dir/$class.php";

        $fs = new Filesystem();

        if ($fs->exists($path)) {
            $output->writeln("<error>Field already exists.</error>");
            return Command::FAILURE;
        }

        $fs->mkdir($dir);

        $template = file_get_contents(Paths::TEMPLATE_DIR . '/field.tpl.php');
        $code = str_replace('FieldName', $class, $template);

        $fs->dumpFile($path, $code);

        $output->writeln("<info>Created:</info> $path");

        return Command::SUCCESS;
    }
}