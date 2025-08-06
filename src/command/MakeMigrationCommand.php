<?php

namespace Wijoc\MIGrator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wijoc\MIGrator\Migration;

class MakeMigrationCommand extends Command
{
    protected static $defaultName = 'make:migration';

    protected function configure()
    {
        $this
            ->setDescription('Create a new migration file')
            ->addArgument('filename', InputArgument::REQUIRED, 'Migration file name')
            ->addOption('table', null, InputOption::VALUE_REQUIRED, 'Table name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getArgument('filename');
        $table = $input->getOption('table');

        $output->writeln("Creating migration: {$filename} for table: {$table}");

        // Your migration file creation logic here
        $migrator = new Migration();
        $migrator->create($filename, ['table' => $table]);

        return Command::SUCCESS;
    }
}
