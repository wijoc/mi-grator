<?php

namespace Wijoc\MIGrator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wijoc\MIGrator\Migration;

class MigrateRollbackCommand extends Command
{
    protected static $defaultName = 'migrate:rollback';

    protected function configure()
    {
        $this->setDescription('Rollback Migration Files');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrator = new Migration();
        $migrator->migrate('rollback', []);

        return Command::SUCCESS;
    }
}
