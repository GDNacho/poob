<?php

namespace Gdnacho\Poob\Command;

use Gdnacho\Poob\Generator\Paths;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'poob:make:output-dto')]
class MakeOutputDtoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Create an Output DTO')
            ->addArgument('name', InputArgument::REQUIRED, 'DTO class name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $class = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        $dir = 'src/Api/OutputDto';
        $path = "$dir/$class.php";

        $fs = new Filesystem();

        if ($fs->exists($path)) {
            $output->writeln('<error>DTO already exists.</error>');

            return Command::FAILURE;
        }

        $fs->mkdir($dir);

        $template = file_get_contents(Paths::TEMPLATE_DIR.'/output.tpl.php');
        $code = str_replace('OutputName', $class, $template);

        $fs->dumpFile($path, $code);

        $output->writeln("<info>Created:</info> $path");

        return Command::SUCCESS;
    }
}
