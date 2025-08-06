<?php

namespace Wijoc\MIGrator;

use Symfony\Component\Console\Application;
use Wijoc\MIGrator\Commands\MakeMigrationCommand;
use Wijoc\MIGrator\Commands\MigrateCommand;
use Wijoc\MIGrator\Commands\MigrateFreshCommand;
use Wijoc\MIGrator\Commands\MigrateRollbackCommand;
use Wijoc\MIGrator\Commands\MigrateResetCommand;

class Kernel
{
    public function run()
    {
        $app = new Application('CLI Migrate Tool', '1.0.0');

        $app->add(new MakeMigrationCommand());
        $app->add(new MigrateCommand());
        $app->add(new MigrateFreshCommand());
        $app->add(new MigrateRollbackCommand());
        $app->add(new MigrateResetCommand());

        $app->run();
    }
}
