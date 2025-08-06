<?php

namespace Wijoc\MIGrator;

use Exception;
use mysqli;
use mysqli_result;

// class Blueprint extends QueryBuilder
class Schema
{
    protected $query = '';

    protected $connection;
    protected $host;
    protected $username;
    protected $password;
    protected $database;
    protected $wordpress;
    protected $wpdb;

    protected $columns;
    private ?String $currentColumn;
    private ?String $previousColumn;
    private ?String $schemaType;

    public static array $log = [];

    public function __construct(string $host = '', string $username = '', string $password = '', string $database = '')
    {
        $this->connection($host, $username, $password, $database);
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
     * Reset property value function
     *
     * @return void
     */
    protected function reset()
    {
        foreach ($this as $key => $value) {
            if (!in_array($key, ['connection', 'host', 'username', 'password', 'database', 'wordpress', 'wpdb', 'log'])) {
                $this->$key = NULL;
            }
        }
    }

    /**
     * Create a table if not exists function
     *
     * @param String $table
     * @param callable $callback
     * @return void
     */
    public static function create(String $table, callable $callback)
    {
        $blueprint = new self();
        $blueprint->schemaType = "create";
        $blueprint->query = "CREATE TABLE IF NOT EXISTS `{$table}` (\n";

        /** Run the callback to get coloumns data */
        $blueprint->columns = [];
        $callback($blueprint);

        /** Export */
        $blueprint->query .= implode(",\n", array_values($blueprint->columns)) . "\n";
        $blueprint->query .= ")";

        if ($blueprint->wordpress) {
            $blueprint->wpdb->get_results($blueprint->query, 'ARRAY_A');
        } else {
            $blueprint->execute($blueprint->query);
        }

        echo "Table {$table} created.\n";

        /** Add schema log */
        self::$log[] = [
            'table' => $table,
            'type' => $blueprint->schemaType
        ];

        $blueprint->reset();
    }

    /**
     * Drop table function
     *
     * @param String $table
     * @return void
     */
    public static function drop(String $table)
    {
        $blueprint = new self();
        $blueprint->schemaType = "drop";
        $blueprint->query = "DROP TABLE `{$table}`";

        if ($blueprint->wordpress) {
            $blueprint->wpdb->get_results($blueprint->query, 'ARRAY_A');
        } else {
            $blueprint->execute($blueprint->query);
        }

        echo "Table {$table} droped.\n";

        /** Add schema log */
        self::$log[] = [
            'table' => $table,
            'type' => $blueprint->schemaType
        ];

        $blueprint->reset();
    }

    /**
     * Drop table if table exists function
     *
     * @param String|String[] $table
     * @return void
     */
    public static function dropIfExists(String|array $table)
    {
        $blueprint = new self();
        $blueprint->schemaType = "drop";

        if (is_array($table)) {
            foreach ($table as $tableName) {
                $blueprint->query = "DROP TABLE IF EXISTS `{$tableName}`;";
            }
        } else {
            $blueprint->query = "DROP TABLE IF EXISTS `{$table}`;";
        }

        if ($blueprint->wordpress) {
            $blueprint->wpdb->get_results($blueprint->query, 'ARRAY_A');
        } else {
            $blueprint->execute($blueprint->query);
        }

        echo "Table {$table} droped.\n";

        /** Add schema log */
        self::$log[] = [
            'table' => $table,
            'type' => $blueprint->schemaType
        ];

        $blueprint->reset();
    }

    /**
     * Alter table function
     *
     * @param String $table
     * @param callable $callback
     * @return void
     */
    public static function table(String $table, callable $callback)
    {
        $blueprint = new self();
        $blueprint->schemaType = "alter";
        $blueprint->query = "ALTER TABLE `{$table}`";

        /** Run the callback to get coloumns data */
        $blueprint->columns = [];
        $callback($blueprint);

        if ($blueprint->wordpress) {
            $blueprint->wpdb->get_results($blueprint->query, 'ARRAY_A');
        } else {
            $blueprint->execute($blueprint->query);
        }

        echo "Table {$table} altered.\n";

        /** Add schema log */
        self::$log[] = [
            'table' => $table,
            'type' => $blueprint->schemaType
        ];

        $blueprint->reset();
    }

    /**
     * Alias for "table" static to alter table function
     *
     * @param String $table
     * @param callable $callback
     * @return void
     */
    public static function alter(String $table, callable $callback)
    {
        self::table($table, $callback);
    }

    /**
     * Set whether the query migration is create or alter the table function
     *
     * @param String $name
     * @param String $query
     * @return string
     */
    private function setColumnQuery(String $name, String $query): string
    {
        if ($this->schemaType === 'alter') {
            $prefix = 'MODIFY COLUMN ';
            return $prefix . $query;
        } else {
            return $query;
        }
    }

    /** Custom Fields */
    public function id(String $name = 'id'): self
    {
        $this->columns[$name] = "`{$name}` INT AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    /**
     * Add fields created_at and updated_at with timestamp type function
     *
     * @return self
     */
    public function timestamps(): self
    {
        $this->columns['def_created_at'] = "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns['def_updated_at'] = "`updated_at` TIMESTAMP DEFAULT NULL";
        return $this;
    }

    /** Numeric Types */
    /**
     * Add fields with type Tiny Integer function
     *
     * @param String $name
     * @return self
     */
    public function tinyInt(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` TINYINT");
        return $this;
    }

    /**
     * Add fields with type Small Integer function
     *
     * @param String $name
     * @return self
     */
    public function smallInt(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` SMALLINT");
        return $this;
    }

    /**
     * Add fields with type Medium Integer function
     *
     * @param String $name
     * @return self
     */
    public function mediumInt(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` MEDIUMINT");
        return $this;
    }

    /**
     * Add fields with type Small Int / Integer function
     *
     * @param String $name
     * @return self
     */
    public function int(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` INT");
        return $this;
    }

    /**
     * Add fields with type Int / Integer function
     *
     * @param String $name
     * @return self
     */
    public function integer(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` INTEGER");
        return $this;
    }

    /**
     * Add fields with type Big Integer function
     *
     * @param String $name
     * @return self
     */
    public function bigInt(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` BIGINT");
        return $this;
    }

    /**
     * Add fields with type Big Integer function
     *
     * @param String $name
     * @return self
     */
    public function bigInteger(String $name): self
    {
        return $this->bigInt($name);
    }

    /**
     * Add fields with type Decimal function
     *
     * @param String $name
     * @param integer $precision - precision
     * @param integer $scale - 0 <= scale <= precision
     * @return self
     */
    public function decimal(String $name, Int $precision = 10, Int $scale = 0): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` DECIMAL({$precision}, {$scale})");
        return $this;
    }

    /**
     * Add fields with type Dec / Decimal function
     *
     * @param String $name
     * @param integer $precision - precision
     * @param integer $scale - 0 <= scale <= precision
     * @return self
     */
    public function dec(String $name, Int $precision = 10, Int $scale = 0): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` DEC({$precision}, {$scale})");
        return $this;
    }

    /**
     * Add fields with type Numeric function
     *
     * @param String $name
     * @param integer $precision - precision
     * @param integer $scale - 0 <= scale <= precision
     * @return self
     */
    public function numeric(String $name, Int $precision = 10, Int $scale = 0): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` NUMERIC({$precision}, {$scale})");
        return $this;
    }

    /**
     * Add fields with type Fixed function
     *
     * @param String $name
     * @param integer $precision - precision
     * @param integer $scale - 0 <= scale <= precision
     * @return self
     */
    public function fixed(String $name, Int $precision = 10, Int $scale = 0): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` FIXED({$precision}, {$scale})");
        return $this;
    }

    /**
     * Add fields with type Float function
     *
     * @param String $name
     * @return self
     */
    public function float(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` FLOAT");
        return $this;
    }

    /**
     * Add fields with type Double function
     *
     * @param String $name
     * @return self
     */
    public function double(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` DOUBLE");
        return $this;
    }

    /**
     * Add fields with type Double Precision function
     *
     * @param String $name
     * @return self
     */
    public function doublePrecision(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` DOUBLE PRECISION");
        return $this;
    }

    /**
     * Add fields with type Real function
     *
     * @param String $name
     * @return self
     */
    public function real(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` REAL");
        return $this;
    }

    /**
     * Add fields with type Bit function
     *
     * @param String $name
     * @param integer $length
     * @return self
     */
    public function bit(String $name, int $length = 1): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` BIT({$length})");
        return $this;
    }

    /**
     * Add fields with type Boolean function
     *
     * @param String $name
     * @return self
     */
    public function boolean(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` BOOLEAN");
        return $this;
    }

    /**
     * Add fields with type Boolean function
     *
     * @param String $name
     * @return self
     */
    public function bool(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` BOOL");
        return $this;
    }

    /** Date and Time Types */
    /**
     * Add fields with type Date function
     *
     * @param String $name
     * @return self
     */
    public function date(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` DATE");
        return $this;
    }

    /**
     * Add fields with type DateTime function
     *
     * @param String $name
     * @return self
     */
    public function datetime(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` DATETIME");
        return $this;
    }

    /**
     * Add fields with type TimeStamp function
     *
     * @param String $name
     * @return self
     */
    public function timestamp(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` TIMESTAMP");
        return $this;
    }

    /**
     * Add fields with type Time function
     *
     * @param String $name
     * @return self
     */
    public function time(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` TIME");
        return $this;
    }

    /**
     * Add fields with type Year function
     *
     * @param String $name
     * @return self
     */
    public function year(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` YEAR");
        return $this;
    }

    /** String Types */
    /**
     * Add fields with type Char function
     *
     * @param String $name
     * @param integer $length
     * @return self
     */
    public function char(String $name, int $length = 1): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` CHAR({$length})");
        return $this;
    }

    /**
     * Add fields with type Varchar function
     *
     * @param String $name
     * @param integer $length
     * @return self
     */
    public function varchar(String $name, int $length = 255): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` VARCHAR({$length})");
        return $this;
    }

    /** Text Types */
    /**
     * Add fields with type Tiny Text function
     *
     * @param String $name
     * @return self
     */
    public function tinyText(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` TINYTEXT");
        return $this;
    }

    /**
     * Add fields with type Text function
     *
     * @param String $name
     * @return self
     */
    public function text(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` TEXT");
        return $this;
    }

    /**
     * Add fields with type Medium Text function
     *
     * @param String $name
     * @return self
     */
    public function mediumText(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` MEDIUMTEXT");
        return $this;
    }

    /**
     * Add fields with type Long Text function
     *
     * @param String $name
     * @return self
     */
    public function longText(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` LONGTEXT");
        return $this;
    }

    /** Binary Types */
    /**
     * Add fields with type Binary function
     *
     * @param String $name
     * @param integer $length
     * @return self
     */
    public function binary(String $name, int $length = 1): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` BINARY({$length})");
        return $this;
    }

    /**
     * Add fields with type Var Binary function
     *
     * @param String $name
     * @param integer $length
     * @return self
     */
    public function varBinary(String $name, int $length = 1): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` VARBINARY({$length})");
        return $this;
    }

    /** BLOB Types */
    /**
     * Add fields with type Tiny BLOB function
     *
     * @param String $name
     * @return self
     */
    public function tinyBlob(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` TINYBLOB");
        return $this;
    }

    /**
     * Add fields with type BLOB function
     *
     * @param String $name
     * @return self
     */
    public function blob(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` BLOB");
        return $this;
    }

    /**
     * Add fields with type Medium BLOB function
     *
     * @param String $name
     * @return self
     */
    public function mediumBlob(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` MEDIUMBLOB");
        return $this;
    }

    /**
     * Add fields with type Long BLOB function
     *
     * @param String $name
     * @return self
     */
    public function longBlob(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` LONGBLOB");
        return $this;
    }

    /** JSON type */
    /**
     * Add fields with type Json function
     *
     * @param String $name
     * @return self
     */
    public function json(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` JSON");
        return $this;
    }

    /** GEOMETRY type */
    /**
     * Add fields with type Geometry function
     *
     * @param String $name
     * @return self
     */
    public function geometry(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` GEOMETRY");
        return $this;
    }

    /**
     * Add fields with type Point function
     *
     * @param String $name
     * @return self
     */
    public function point(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` POINT");
        return $this;
    }

    /**
     * Add fields with type Linestring function
     *
     * @param String $name
     * @return self
     */
    public function lineString(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` LINESTRING");
        return $this;
    }

    /**
     * Add fields with type Polygon function
     *
     * @param String $name
     * @return self
     */
    public function polygon(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` POLYGON");
        return $this;
    }

    /**
     * Add fields with type Multi Point function
     *
     * @param String $name
     * @return self
     */
    public function multiPoint(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` MULTIPOINT");
        return $this;
    }

    /**
     * Add fields with type Multi Linestring function
     *
     * @param String $name
     * @return self
     */
    public function multiLinestring(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` MULTILINESTRING");
        return $this;
    }

    /**
     * Add fields with type Multi Polygon function
     *
     * @param String $name
     * @return self
     */
    public function multiPolygon(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` MULTIPOLYGON");
        return $this;
    }

    /**
     * Add fields with type Geometry collection function
     *
     * @param String $name
     * @return self
     */
    public function geometryCollection(String $name): self
    {
        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` GEOMETRYCOLLECTION");
        return $this;
    }

    /** ENUM and SET type */
    /**
     * Add fields with type ENUM function
     *
     * @param String $name
     * @param array $values - Required, need to be an array
     * @return self
     */
    public function enum(String $name, array $values): self
    {
        if (empty($values)) {
            throw new Exception("Values is required for ENUM type!");
        }

        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` ENUM(\"" . implode('", "', $values) . "\")");
        return $this;
    }

    /**
     * Add fields with type SET function
     *
     * @param String $name
     * @param array $values - Required, need to be an array, max element is 64 values
     * @return self
     */
    public function set(String $name, array $values): self
    {
        if (empty($values)) {
            throw new Exception("Values is required for SET type!");
        }

        if (count($values) > 64) {
            throw new Exception("Values cannot be more than 64 value for SET type!");
        }

        $this->columns[$name] = $this->setColumnQuery($name, "`{$name}` SET(\"" . implode('", "', $values) . "\")");
        return $this;
    }

    /** Column-level Constraints and Attributes */
    /**
     * Remove not null function
     *
     * @return void
     */
    public function nullable()
    {
        $this->columns[$this->currentColumn] = str_replace("NOT NULL", "", $this->columns[$this->currentColumn]);
    }

    /**
     * Set unique field or add unique constrain to current field function
     *
     * @param String $name
     * @return void
     */
    public function unique(String $name)
    {
        if ($name == "") {
            $this->columns[] = "UNIQUE (`{$this->currentColumn}`)";
        } else {
            $this->columns[] .= "UNIQUE (`$name`)";
        }

        return $this;
    }

    /** Alter table function */
    /**
     * Drop existing coloumn function
     *
     * @param String $name
     * @return self
     */
    public function dropColumn(String $name): self
    {
        $this->columns[$name] = "DROP COLUMN `{$name}`";
        return $this;
    }
}
