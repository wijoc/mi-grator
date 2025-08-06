<?php

use Wijoc\MIGrator;

if (defined('WP_CLI') && WP_CLI) {
    class MigrationCommand
    {
        protected $migrator;

        public function __construct()
        {
            $this->migrator = new Migration();
        }

        /**
         * Create migration file function
         *
         * @param Array $args
         * @param Array $assocArgs
         * @return void
         */
        public function createMigration(array $args, array $assocArgs)
        {
            $arguments = [
                'command' => 'wpcli'
            ];
            if (isset($assocArgs['table'])) {
                $arguments['table'] = $assocArgs['table'];
            }

            $migrations = $this->migrator->create($args[0], $arguments);
        }

        /**
         * Migrate new migration file function
         *
         * @param array $args
         * @param array $assocArgs
         * @return void
         */
        public function migrate(array $args, array $assocArgs)
        {
            $migrations = $this->migrator->migrate('migrate', ['command' => 'wpcli']);
        }

        /**
         * Freashly migrate migration file function
         *
         * @param array $args
         * @param array $assocArgs
         * @return void
         */
        public function fresh(array $args, array $assocArgs)
        {
            $migrations = $this->migrator->migrate('fresh', ['command' => 'wpcli']);
        }

        /**
         * Rollback migration file function
         *
         * @param array $args
         * @param array $assocArgs
         * @return void
         */
        public function rollback(array $args, array $assocArgs)
        {
            $migrations = $this->migrator->migrate('rollback', ['command' => 'wpcli']);
        }

        /**
         * Reset all migration function
         *
         * @param array $args
         * @param array $assocArgs
         * @return void
         */
        public function reset(array $args, array $assocArgs)
        {
            $migrations = $this->migrator->migrate('reset', ['command' => 'wpcli']);
        }
    }

    /** Register WP CLI Command */
    $migrationCommand = new MigrationCommand();
    WP_CLI::add_command('make:migration', [new MigrationCommand(), 'createMigration']);
    WP_CLI::add_command('migrate', [new MigrationCommand(), 'migrate']);
    WP_CLI::add_command('migrate:fresh', [new MigrationCommand(), 'fresh']);
    WP_CLI::add_command('migrate:rollback', [new MigrationCommand(), 'rollback']);
    WP_CLI::add_command('migrate:reset', [new MigrationCommand(), 'reset']);
}
