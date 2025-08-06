<?php

namespace Wijoc\MIGrator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wijoc\MIGrator\Migration;

class MigrateFreshCommand extends Command
{
    protected static $defaultName = 'migrate:fresh';

    protected function configure()
    {
        $this->setDescription('Migrating All Files');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrator = new Migration();
        $migrator->migrate('fresh', []);

        return Command::SUCCESS;
    }
}
