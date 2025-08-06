<?php

namespace Wijoc\MIGrator;

use Exception;
use mysqli;
use mysqli_result;

class Migration
{
    private $connection;
    private $host;
    private $username;
    private $password;
    private $database;
    private $wordpress;
    private $wpdb;
    private $migrationTable;
    private $migrations;
    private $migrationTableName;
    private $migrationFilePath;

    public function __construct(string $host = '', string $username = '', string $password = '', string $database = '')
    {
        $this->connection();
        $this->createTableMigration();
        $this->resolveTableMigrationName();
        $this->resolveFileMigrationPath();

        $this->migrations = [
            $this->migrationFilePath . '/*.php',
            $this->migrationFilePath . '/**/*.php',
        ];
    }

    /**
     * Create connection function
     *
     * - Check if current project is a wordpress or not.
     * - Then create a connection if not a wordpress project.
     *
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $database
     * @return self
     * @throws Exception - When failed to create connection.
     */
    protected function connection(string $host = '', string $username = '', string $password = '', string $database = ''): self
    {
        if ($this->checkIfWordpress()) {
            global $wpdb;
            $this->wpdb = $wpdb;
        } else {
            $this->host = $host;
            $this->username = $username;
            $this->password = $password;
            $this->database = $database;

            $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);

            if ($this->connection->connect_error) {
                throw new Exception("Failed to connect to database: " . $this->connection->connect_error);
            }
        }

        return $this;
    }

    /**
     * Check if current project is wordpress function
     *
     * @return bool
     */
    protected function checkIfWordpress(): bool
    {
        $directory = $_SERVER['DOCUMENT_ROOT'];
        if (file_exists($directory . '/wp-config.php')) {
            if (is_dir($directory . '/wp-includes')) {
                if (is_dir($directory . '/wp-admin')) {
                    if (is_dir($directory . '/wp-content')) {
                        $this->wordpress = true;
                        return $this->wordpress;
                    }
                }
            }
        }

        $this->wordpress = false;

        return $this->wordpress;
    }

    /**
     * Execute query function
     *
     * @param string $query
     * @return mysqli_result|bool
     */
    protected function execute(string $query): mysqli_result|bool
    {
        return $this->connection->query($query);
    }

    /**
     * Create Table Migration function
     *
     * @return void
     */
    public function createTableMigration()
    {
        $query = "CREATE TABLE IF NOT EXISTS `{$this->migrationTableName}` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `migration` VARCHAR(255),
                    `table` VARCHAR(255),
                    `type` VARCHAR(255),
                    `batch` INT
                )";

        if ($this->wordpress) {
            $this->wpdb->get_results($query, 'ARRAY_A');
        } else {
            $this->execute($query);
        }
    }

    /**
     * Drop migration table function
     *
     * @return void
     */
    public function dropTableMigration()
    {
        $query = "DROP TABLE IF EXISTS `{$this->migrationTableName}`";

        if ($this->wordpress) {
            $this->wpdb->get_results($query, 'ARRAY_A');
        } else {
            $this->execute($query);
        }
    }

    /**
     * Get table migration name function
     *
     * @return string
     */
    private function resolveTableMigrationName(): string
    {
        $migrationTable = getenv("MIGRATION_TABLE");

        if ($migrationTable !== false && $migrationTable !== '') {
            $this->migrationTableName = $migrationTable;
            return $migrationTable;
        }

        if (defined('MIGRATION_TABLE')) {
            $this->migrationTableName = constant('MIGRATION_TABLE');
            return constant('MIGRATION_TABLE');
        }

        $this->migrationTableName = 'migrations';
        return 'migrations';
    }

    /**
     * Get migrations file path function
     *
     * @return string
     */
    private function resolveFileMigrationPath(): string
    {
        $migrationTable = getenv("MIGRATION_FILE_PATH");

        if ($migrationTable !== false && $migrationTable !== '') {
            $this->migrationFilePath = $migrationTable;
            return $migrationTable;
        }

        if (defined('MIGRATION_FILE_PATH')) {
            $this->migrationFilePath = constant('MIGRATION_FILE_PATH');
            return constant('MIGRATION_FILE_PATH');
        }

        if ($this->wordpress) {
            $this->migrationFilePath = get_stylesheet_directory() . '/migrations';
            return get_stylesheet_directory() . '/migrations';
        } else {
            $this->migrationFilePath = ABSPATH . '/migrations';
            return ABSPATH . '/migrations';
        }
    }

    /**
     * Create Migration File function
     *
     * @param String $name
     * @param array $args
     * @return void
     */
    public function create(String $name, array $args = [])
    {
        $tableName = isset($args['table']) ? $args['table'] : '{your_table_name_goes_here}';

        $defaultMigrationCode = <<<PHP
            <?php

            use MI\DB\Migration;
            use MI\DB\Schema;

            defined("ABSPATH") or die("Direct access not allowed!");

            return new class extends Migration
            {
                public function up()
                {
                    Schema::create('{$tableName}', function (Schema \$table) {
                        \$table->id();
                        \$table->timestamps();
                    });
                }

                public function down()
                {
                    Schema::dropIfExists("{$tableName}");
                }
            };
        PHP;

        /** Check if path exists, If not create dir */
        if (!file_exists($this->migrationFilePath)) {
            mkdir($this->migrationFilePath, 0777, true);
        }

        /** Generate date to name */
        $date = date('Y_m_d_His');
        $migrationFileName = $date . '_' . $name . '.php';

        /** Generate File */
        if (file_put_contents($this->migrationFilePath . '/' . $migrationFileName, $defaultMigrationCode)) {
            echo "Migration file created: {$migrationFileName}. \n" . PHP_EOL;
        } else {
            echo "Failed to create migration file: {$migrationFileName}. \n" . PHP_EOL;
        }
    }

    /**
     * Run migration file function
     *
     * @param string $type
     * @param array $args
     * @return void
     */
    public function migrate(String $type = '', array $args = [])
    {
        switch (strtolower($type)) {
            case 'fresh':
                return $this->runUpFresh($args);
                break;
            case 'rollback':
                return $this->runRollback($args);
                break;
            case 'reset':
                return $this->runReset($args);
                break;
            default:
                return $this->runUp($args);
                break;
        }
    }

    /**
     * Migrate function
     *
     * Will check from table migration if the file already migrated
     *
     * @param array $args
     * @return void
     */
    protected function runUp(array $args = [])
    {
        /** Get data from migration table */
        $migrated = $this->getMigrationFile();
        $nextBatchNumber = $this->getNextBatchNumber();

        /** Loop all migration path
         * Skip if already in migrations data
         */
        $newMigrated = [];
        foreach ($this->migrations as $path) {
            foreach (glob($path) as $file) {
                if (!in_array(basename($file), $migrated)) {
                    $migration = require $file;
                    if (is_object($migration) && method_exists($migration, 'up')) {
                        $migration->up();

                        /** Prepare migration data */
                        foreach (Schema::$log as $log) {
                            /** Prepare to store migrations data */
                            $fileMigrated = [
                                'migration' => basename($file),
                                'table' => $log['table'],
                                'type' => $log['type'],
                                'batch' => $nextBatchNumber
                            ];
                            $newMigrated[] = $fileMigrated;
                        }

                        Schema::$log = [];
                    }
                }
            }
        }

        $this->writeMigrationLog($newMigrated);
    }

    /**
     * Fresh migrate function
     *
     * @param array $args
     * @return void
     */
    protected function runUpFresh(array $args = [])
    {
        /** Drop All Created Table */
        $this->dropAllCreatedTable();
        $this->truncateMigrationTable();
        $nextBatchNumber = $this->getNextBatchNumber();

        /** Loop all migration path
         * Skip if already in migrations data
         */
        $newMigrated = [];
        foreach ($this->migrations as $path) {
            foreach (glob($path) as $file) {
                $migration = require $file;
                if (is_object($migration) && method_exists($migration, 'up')) {
                    $migration->up();

                    /** Prepare migration data */
                    $filename = basename($file);
                    foreach (Schema::$log as $log) {
                        /** Prepare to store migrations data */
                        $fileMigrated = [
                            'migration' => $filename,
                            'table' => $log['table'],
                            'type' => $log['type'],
                            'batch' => $nextBatchNumber
                        ];
                        $newMigrated[] = $fileMigrated;
                    }

                    Schema::$log = [];

                    echo "Successfully migrate {$filename}";
                }
            }
        }

        $this->writeMigrationLog($newMigrated);
    }

    /**
     * Rollback Migration function
     *
     * @param array $args
     * @return void
     */
    protected function runRollback(array $args = [])
    {
        /** Get data from migration table */
        $migrated = $this->getLastBatchMigration();

        /** Loop all migration path
         * Skip if already in migrations data
         */
        foreach ($this->migrations as $path) {
            foreach (glob($path) as $file) {
                $filename = basename($file);
                if (in_array($filename, $migrated)) {
                    $migration = require $file;
                    if (is_object($migration) && method_exists($migration, 'down')) {
                        $migration->down();
                    }
                }
            }
        }

        /** Delete last batch migration */
        $this->deleteLastBatchMigration();
    }

    /**
     * Reset all migration function
     *
     * @param array $args
     * @return void
     */
    protected function runReset(array $args = [])
    {
        /** Loop all migration path
         * Skip if already in migrations data
         */
        $newMigrated = [];
        foreach ($this->migrations as $path) {
            foreach (glob($path) as $file) {
                $migration = require $file;
                if (is_object($migration) && method_exists($migration, 'down')) {
                    $migration->down();
                    $newMigrated[] = $file;
                }
            }
        }

        /** Get data from migration table */
        $migrated = $this->truncateMigrationTable();
    }

    /**
     * Get migrated file function
     *
     * @return array
     */
    protected function getMigrationFile(): array
    {
        $qb = new QueryBuilder();
        $results = $qb->table("{$this->migrationTableName}")->select('migrations')->orderBy('batch', 'DESC')->get();
        $migratedFiles = [];

        if ($this->wordpress) {
            if (is_array($results)) {
                $migrations = $results;
            } else if ($results == NULL) {
                $migrations = [];
            } else {
                $migrations = $results->get_results();
            }

            foreach ($migrations as $migration) {
                $migratedFiles[] = $migration['migrations'];
            }
        } else {
            $migrations = $results->fetch_array(MYSQLI_ASSOC);
            $results->free(); // free the memory used for data

            foreach ($migrations as $migration) {
                $migratedFiles[] = $migration['migrations'];
            }
        }

        return $migratedFiles;
    }

    /**
     * Get created table from migration function
     *
     * @return array
     */
    protected function getMigrationTable(): array
    {
        $qb = new QueryBuilder();
        $results = $qb->table("{$this->migrationTableName}")->select('table')->where('type', '=', 'create')->orderBy('batch', 'DESC')->get();
        $migratedFiles = [];

        if ($this->wordpress) {
            if (is_array($results)) {
                $migrations = $results;
            } else if ($results == NULL) {
                $migrations = [];
            } else {
                $migrations = $results->get_results();
            }

            foreach ($migrations as $migration) {
                $migratedFiles[] = $migration['table'];
                array_unique($migratedFiles);
            }
        } else {
            $migrations = $results->fetch_array(MYSQLI_ASSOC);
            $results->free(); // free the memory used for data

            foreach ($migrations as $migration) {
                $migratedFiles[] = $migration['table'];
                array_unique($migratedFiles);
            }
        }

        return $migratedFiles;
    }

    /**
     * Get last batch migration function
     *
     * @return array
     */
    protected function getLastBatchMigration(): array
    {
        $qb = new QueryBuilder();
        $results = $qb->table("{$this->migrationTableName}")->select("*")->where("batch", "insubquery", "SELECT MAX(batch) FROM {$this->migrationTableName}")->orderBy('id', 'DESC')->get();
        $migratedFiles = [];

        if ($this->wordpress) {
            if (is_array($results)) {
                $migrations = $results;
            } else if ($results == NULL) {
                $migrations = [];
            } else {
                $migrations = $results->get_results();
            }

            foreach ($migrations as $migration) {
                $migratedFiles[$migration['id']] = $migration['migration'];
                array_unique($migratedFiles);
            }
        } else {
            $migrations = $results->fetch_array(MYSQLI_ASSOC);
            $results->free(); // free the memory used for data

            foreach ($migrations as $migration) {
                $migratedFiles[$migration['id']] = $migration['migration'];
                array_unique($migratedFiles);
            }
        }

        return $migratedFiles;
    }

    /**
     * Get Next Batch Number function
     *
     * @return integer
     */
    protected function getNextBatchNumber(): int
    {
        $qb = new QueryBuilder();
        $results = $qb->table("{$this->migrationTableName}")->select("batch")->orderBy('batch', 'DESC')->get();
        $nextBatchNumber = 1;

        if ($this->wordpress) {
            if (is_array($results)) {
                $migrations = $results;
            } else if ($results == NULL) {
                $migrations = [];
            } else {
                $migrations = $results->get_results();
            }

            if (isset($migrations[0])) {
                if (isset($migrations[0]['batch']) && is_numeric($migrations[0]['batch'])) {
                    $nextBatchNumber = (int)$migrations[0]['batch'] + 1;
                }
            }
        } else {
            $migrations = $results->fetch_array(MYSQLI_ASSOC);
            $results->free(); // free the memory used for data

            if (isset($migrations[0])) {
                if (isset($migrations[0]['batch']) && is_numeric($migrations[0]['batch'])) {
                    $nextBatchNumber = (int)$migrations[0]['batch'] + 1;
                }
            }
        }

        return $nextBatchNumber;
    }

    /**
     * Drop table created from migration function
     *
     * @return void
     */
    protected function dropAllCreatedTable()
    {
        /** Get data from migration table */
        $createdTable = $this->getMigrationTable();

        $query = "";
        foreach ($createdTable as $table) {
            $query .= "DROP TABLE IF EXISTS `{$table}`\n";
        }

        $queryDisableForeignKeyChecks = "SET FOREIGN_KEY_CHECKS=0;";
        $queryEnableForeignKeyChecks = "SET FOREIGN_KEY_CHECKS=1;";
        if ($this->wordpress) {
            /** Disable foreign key checks */
            $this->wpdb->get_results($queryDisableForeignKeyChecks, 'ARRAY_A');

            $this->wpdb->get_results($query, 'ARRAY_A');

            /** Enable foreign key checks */
            $this->wpdb->get_results($queryEnableForeignKeyChecks, 'ARRAY_A');
        } else {
            /** Disable foreign key checks */
            $this->execute($queryDisableForeignKeyChecks);

            $this->execute($query);

            /** Enable foreign key checks */
            $this->execute($queryEnableForeignKeyChecks);
        }
    }

    /**
     * Truncate migration table function
     *
     * @return void
     */
    protected function truncateMigrationTable()
    {
        $migrationTable = $this->resolveTableMigrationName();
        $query = "TRUNCATE TABLE `{$migrationTable}`;";

        if ($this->wordpress) {
            $this->wpdb->get_results($query, 'ARRAY_A');
        } else {
            $this->execute($query);
        }
    }

    /**
     * Delete Last Batch Migration function
     *
     * @return void
     */
    protected function deleteLastBatchMigration()
    {
        $qb = new QueryBuilder();

        $deleteCondition = [
            'operator' => 'insubquery',
            'value' => "SELECT MAX(batch) FROM {$this->migrationTableName}"
        ];
        $query = $qb->table("{$this->migrationTableName}")->delete($deleteCondition);

        return $query;
    }

    /**
     * Write migration log to migration Table function
     *
     * @param array $migrations
     * @return void
     */
    protected function writeMigrationLog(array $migrations)
    {
        if (!empty($migrations)) {
            $qb = new QueryBuilder();

            $query = $qb->table("{$this->migrationTableName}")->insert($migrations);

            return $query;
        }
    }
}
