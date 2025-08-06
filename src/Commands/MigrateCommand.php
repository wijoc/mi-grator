<?php

namespace Wijoc\MIGrator\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wijoc\MIGrator\Migration;

class MigrateCommand extends Command
{
    protected static $defaultName = 'migrate';

    protected function configure()
    {
        $this->setDescription('Migrating file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrator = new Migration();
        $migrator->migrate('migrate', []);

        return Command::SUCCESS;
    }
}
