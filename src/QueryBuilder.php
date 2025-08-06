<?php

namespace Wijoc;

use Exception;
use mysqli;

class QueryBuilder
{
    private $table;
    private $select     = [];
    private $columns    = [];
    private $where      = [];
    private $whereRaw   = [];
    private $having     = [];
    private $havingRaw  = [];
    private $orderBy;
    private $groupBy;
    private $limit;
    private $offset;
    private $joins      = [];
    private $subquery;
    private $data;
    private $insertData;
    private $upsertData;
    private $upsertSetData;
    private $updateData;
    private $delete;
    private $parameters  = [];
    private $whereParameters  = [];
    private $havingParameters = [];
    private $query = '';

    private $connection;
    private $host;
    private $username;
    private $password;
    private $database;
    private $wordpress;
    private $wpdb;

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
    private function connection(string $host = '', string $username = '', string $password = '', string $database = ''): self
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
    private function checkIfWordpress(): bool
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
     * Set table function
     *
     * @param string $table
     * @return self
     */
    public function table(string $table, string $alias = ''): self
    {
        if ($alias !== "") {
            $this->table = "{$table} AS {$alias}";
        } else {
            $this->table = $table;
        }
        return $this;
    }

    /**
     * Select from table function
     *
     * @param array<string>|string|null $columns - Array|string of field name.
     * @return self
     */
    public function select(array|string|null $columns = NULL): self
    {
        if (!empty($columns) && $columns !== NULL) {
            if (is_array($columns)) {
                $select = $columns;
            } else if (is_string($columns)) {
                $select = explode(',', $columns);
                $select = array_map(function ($column) {
                    return trim($column);
                }, $select);
            }
        } else {
            $select = ['*'];
        }

        if (isset($this->select) && !empty($this->select) && $this->select !== NULL) {
            $this->select = array_unique(array_merge($this->select, $select));
        } else {
            $this->select = $select;
        }

        return $this;
    }

    /**
     * Set field to use function
     *
     * @param array $fields
     * @return self
     */
    public function column(array $fields): self
    {
        $this->columns = $fields;
        return $this;
    }

    /**
     * Grouping Where function
     *
     * @param string $type
     * @return self
     */
    public function groupWhere(string $type = 'start'): self
    {
        if ($type == 'start') {
            $this->where[]  = "(";
        } else {
            $this->where[]  = ")";
        }

        return $this;
    }

    /**
     * Where Condition function
     *
     * @param string $column - string of field name.
     * @param string $operator
     * @param mixed $value
     * @param mixed $relation - value oneOf : 'AND' | 'OR'
     * @param array $cast - value must be an array with key : by and into. By to indicate the the column or value to cast, into is casting format
     * @return self
     */
    public function where(string $column, string $operator, mixed $value, string $relation = 'AND', array $cast = [], bool $strict = true): self
    {
        /** store raw where */
        $this->whereRaw[$column][] = [
            'operator' => $operator,
            'relation' => $relation,
            'value' => $value,
            'cast' => $cast
        ];

        /** Prepare column that need a strict query and/or need to cast */
        $needCastValue = false;
        if ($strict) {
            if (!empty($cast) && isset($cast['by']) && isset($cast['into'])) {
                if (strtolower($cast['by']) == 'column' || strtolower($cast['by']) == 'coloumn' || strtolower($cast['by']) == 'field') {
                    $column = $this->_prepareCast($column, $cast['into'], $strict);
                } else if (strtolower($cast['by']) == 'value') {
                    $needCastValue = true;
                }
            } else {
                $column = "`{$column}`";
            }
        }

        /** Check if last where is a group bracket */
        $lastIsGroupBracket = false;
        if (isset($this->where) && is_array($this->where)) {
            $lastIsGroupBracket = (end($this->where) == "(");
        }

        $whereQuery = null;
        switch (strtolower($operator)) {
            case 'isnull':
            case 'is null':
                $whereQuery  = "{$column} IS NULL";
                break;
            case 'isnotnull':
            case 'is not null':
                $whereQuery  = "{$column} IS NOT NULL";
                break;
            case 'like':
                $parameters = $this->_prepareParameters("%$value%");
                $this->whereParameters[] = $parameters;

                /** Prepare cast value */
                $castedParametersKey = [];
                if ($needCastValue) {
                    foreach ($parameters as $key => $value) {
                        $castedParametersKey[] = $this->_prepareCast($key, $cast['into'], $strict);
                    }
                } else {
                    $castedParametersKey = array_keys($parameters);
                }

                if ($this->wordpress) {
                    $whereQuery = "{$column} LIKE '" . implode("', '", $castedParametersKey) . "'"; // not neccesaraly using implode cause this will always
                } else {
                    $whereQuery = "{$column} LIKE ?";
                }
                break;
            case 'in':
                $parameterPlaceholders  = [];

                if (is_array($value)) {
                    foreach ($value as $val) {
                        $parameters = $this->_prepareParameters($val, false);
                        $parameterPlaceholders = array_merge($parameterPlaceholders, array_keys($parameters));
                        $this->whereParameters[] = $parameters;
                    }
                } else {
                    $values = $this->_prepareQueryValue('where', $value);
                    $values = explode(",", $values);

                    foreach ($values as $val) {
                        $parameters = $this->_prepareParameters($val, false);
                        $parameterPlaceholders = array_merge($parameterPlaceholders, array_keys($parameters));
                        $this->whereParameters[] = $parameters;
                    }
                }

                /** Prepare cast value */
                $castedParametersKey = [];
                if ($needCastValue) {
                    foreach ($parameterPlaceholders as $key => $value) {
                        $castedParametersKey[] = $this->_prepareCast($key, $cast['into'], $strict);
                    }
                } else {
                    $castedParametersKey = array_is_list($parameterPlaceholders) ? array_values($parameterPlaceholders) : array_keys($parameterPlaceholders);
                }

                if ($this->wordpress) {
                    $whereQuery = "{$column} IN ('" . implode("', '", $castedParametersKey) . "')";
                } else {
                    $whereQuery = "{$column} IN ('" . implode("', '", $castedParametersKey) . "')";
                }
                break;
            case 'insubquery':
            case 'in-subquery':
            case 'in-sub-query':
            case 'in subquery':
            case 'in sub-query':
                if ($this->wordpress) {
                    $whereQuery = "{$column} IN ({$value})";
                } else {
                    $whereQuery = "{$column} IN ({$value})";
                }
                break;
            case 'notinsubquery':
            case 'notin-subquery':
            case 'not-in-sub-query':
            case 'not in subquery':
            case 'not-in subquery':
            case 'not in sub-query':
            case 'not-in sub-query':
                if ($this->wordpress) {
                    $whereQuery = "{$column} NOT IN ({$value})";
                } else {
                    $whereQuery = "{$column} NOT IN ({$value})";
                }
                break;
            case 'subquery':
                $whereQuery = "$column";
                break;
            case 'between':
                // $whereQuery = "{$column} BETWEEN ({$value[0]}, {$value[1]})";
                $betweenParameters = [];

                $parameters = $this->_prepareParameters("$value[0]");
                $this->whereParameters[] = $parameters;
                $betweenParameters[] = array_keys($parameters)[0];

                $parameters = $this->_prepareParameters("$value[1]");
                $this->whereParameters[] = $parameters;
                $betweenParameters[] = array_keys($parameters)[0];

                /** Prepare cast value */
                $castedParametersKey = [];
                if ($needCastValue) {
                    foreach ($parameters as $key => $value) {
                        $castedParametersKey[] = $this->_prepareCast($key, $cast['into'], $strict);
                    }
                } else {
                    $castedParametersKey = $betweenParameters;
                }

                if ($this->wordpress) {
                    $whereQuery = "{$column} BETWEEN '" . implode("' AND '", $castedParametersKey) . "'"; // not neccesaraly using implode cause this will always
                } else {
                    $whereQuery = "{$column} BETWEEN ? AND ?";
                }
                break;
            case 'regexp':
                if (is_array($value)) {
                    $value = "(" . implode('|', $value) . ")";
                }

                if ($this->wordpress) {
                    $whereQuery = "{$column} REGEXP {$value}";
                } else {
                    $whereQuery = "{$column} REGEXP {$value}";
                }
                break;
            case 'notin':
            case 'not in':
                $parameterPlaceholders  = [];

                if (is_array($value)) {
                    foreach ($value as $val) {
                        $parameters = $this->_prepareParameters($val, false);
                        $parameterPlaceholders = array_merge($parameterPlaceholders, array_keys($parameters));
                        $this->whereParameters[] = $parameters;
                    }
                } else {
                    $values = $this->_prepareQueryValue('where', $value);
                    $values = explode(",", $values);

                    foreach ($values as $val) {
                        $parameters = $this->_prepareParameters($val, false);
                        $parameterPlaceholders = array_merge($parameterPlaceholders, array_keys($parameters));
                        $this->whereParameters[] = $parameters;
                    }
                }

                /** Prepare cast value */
                $castedParametersKey = [];
                if ($needCastValue) {
                    foreach ($parameterPlaceholders as $key => $value) {
                        $castedParametersKey[] = $this->_prepareCast($key, $cast['into'], $strict);
                    }
                } else {
                    $castedParametersKey = array_keys($parameterPlaceholders);
                }

                if ($this->wordpress) {
                    $whereQuery = "{$column} NOT IN ('" . implode("', '", $castedParametersKey) . "')";
                } else {
                    $whereQuery = "{$column} NOT IN ('" . implode("', '", $castedParametersKey) . "')";
                }
                break;
            case '!=':
            case '<>':
                $parameters  = $this->_prepareParameters($value, false);
                $this->whereParameters[] = $parameters;

                /** Prepare cast value */
                $castedParametersKey = [];
                if ($needCastValue) {
                    foreach ($parameters as $key => $value) {
                        $castedParametersKey[] = $this->_prepareCast($key, $cast['into'], $strict);
                    }
                } else {
                    $castedParametersKey = array_keys($parameters);
                }

                if ($this->wordpress) {
                    $whereQuery = "{$column} != '" . implode("', '", $castedParametersKey) . "'"; // not neccesaraly using implode cause this will always
                } else {
                    $whereQuery = "{$column} != ?";
                }
                break;
            case '>':
            case '>=':
            case '<=':
            case '<':
                $parameters  = $this->_prepareParameters($value, false);
                $this->whereParameters[] = $parameters;

                /** Prepare cast value */
                $castedParametersKey = [];
                if ($needCastValue) {
                    foreach ($parameters as $key => $value) {
                        $castedParametersKey[] = $this->_prepareCast($key, $cast['into'], $strict);
                    }
                } else {
                    $castedParametersKey = array_keys($parameters);
                }

                if ($this->wordpress) {
                    $whereQuery = "{$column} {$operator} '" . implode("', '", $castedParametersKey) . "'"; // not neccesaraly using implode cause this will always
                } else {
                    $whereQuery = "{$column} {$operator} ?";
                }
                break;
            case '=':
            default:
                $parameters  = $this->_prepareParameters($value, false);
                $this->whereParameters[] = $parameters;

                /** Prepare cast value */
                $castedParametersKey = [];
                if ($needCastValue) {
                    foreach ($parameters as $key => $value) {
                        $castedParametersKey[] = $this->_prepareCast($key, $cast['into'], $strict);
                    }
                } else {
                    $castedParametersKey = array_keys($parameters);
                }

                if ($this->wordpress) {
                    $whereQuery = "{$column} = '" . implode("', '", $castedParametersKey) . "'"; // not neccesaraly using implode cause this will always
                } else {
                    $whereQuery = "{$column} = ?";
                }
                break;
        }

        /** Add start of group bracket
         * end of group bracket will imploded in build where query
         * that will run in function "build()"
         */
        if ($lastIsGroupBracket) {
            $whereQuery = '(' . $whereQuery;
        }

        /** Add relation to query */
        if (!in_array(strtolower($relation), ['and', 'or'])) {
            $relation = 'AND';
        }

        if (isset($this->where) && is_array($this->where) && count($this->where) >= 1) {
            if ($lastIsGroupBracket) {
                /** Add relation if $lastIsGroupBracket and $lastIsGroupBracket is not in first index */
                $this->where[count($this->where) - 1] = (count($this->where) - 1) > 0 ? " {$relation} {$whereQuery}" : $whereQuery;
            } else {
                $this->where[] = " {$relation} {$whereQuery}";
            }
        } else {
            $this->where[] = $whereQuery;
        }

        return $this;
    }

    /**
     * Where In condition function
     *
     * @param string $column
     * @param mixed $value
     * @return self
     */
    public function whereIn(string $column, mixed $value): self
    {
        $parameterPlaceholders  = [];

        if (is_array($value)) {
            foreach ($value as $val) {
                $parameters         = $this->_prepareParameters($val);
                $parameterPlaceholders  = array_merge($parameterPlaceholders, array_keys($parameters));
                $this->whereParameters[]     = $parameters;
            }
        } else {
            $value = $this->_prepareQueryValue('where', $value);
            $parameters         = $this->_prepareParameters($value);
            $this->whereParameters[] = $parameters;
        }

        if ($this->wordpress) {
            if (is_array($value)) {
                if (end($this->where) == "(") {
                    if ((count($this->where) - 1) > 0) {
                        $this->where[count($this->where) - 1]  = " ( `{$column}` IN ('" . implode("', '", $parameterPlaceholders) . "')";
                    } else {
                        $this->where[]  = " `{$column}` IN ('" . implode("', '", $parameterPlaceholders) . "')";
                    }
                } else {
                    $this->where[]  = " `{$column}` IN ('" . implode("', '", $parameterPlaceholders) . "')";
                }
            } else {
                $this->where[]  = " `{$column}` IN ('" . array_keys($parameters) . "')";
            }
        } else {
            if (is_array($value)) {
                $placeholders = [];
                foreach ($parameterPlaceholders as $param) {
                    $placeholders[] = '?';
                }
                $this->where[]  = " `{$column}` IN ('" . implode("', '", $placeholders) . "')";
            } else {
                $this->where[]  = " `{$column}` IN ('" . array_keys($parameters) . "')";
            }
        }

        return $this;
    }

    /**
     * Order By query function
     *
     * @param string|array $column
     * @param string $direction
     * @return self
     */
    public function orderBy(mixed $column, ?string $direction = 'ASC'): self
    {
        if (is_array($column)) {
            if (array_is_list($column)) {
                $this->orderBy = '';
                foreach ($column as $col) {
                    $this->orderBy .= "{$col} {$direction}";

                    if ($col !== end($column)) {
                        $this->orderBy .= ', ';
                    }
                }
            } else {
                $this->orderBy = '';
                foreach ($column as $field => $direction) {
                    $this->orderBy .= "{$field} {$direction}";

                    if ($field !== array_key_last($column)) {
                        $this->orderBy .= ', ';
                    }
                }
            }
        } else if (is_string($column)) {
            $this->orderBy = "{$column} {$direction}";
        }
        return $this;
    }

    /**
     * Group By query function
     *
     * @param string $column
     * @return self
     */
    public function groupBy(string $column, bool $strict = false): self
    {
        if (str_contains($column, '.')) {
            $columns = explode('.', $column);
            // for ($i = 0; $i < count($columns); $i++) {
            //     if (i < )
            // }
            if ($strict) {
                $column = '`' . implode("`.`", $columns);
            } else {
                $column = implode(".", $columns);
            }

            $this->groupBy = "{$column}";
        } else {
            if ($strict) {
                $this->groupBy = "`{$column}`";
            } else {
                $this->groupBy = "{$column}";
            }
        }
        return $this;
    }

    public function having(string $column, string $operator, mixed $value, string $relation = 'AND', array $cast = [], bool $strict = true): self
    {
        /** store raw having */
        $this->havingRaw[$column][] = [
            'operator' => $operator,
            'relation' => $relation,
            'value' => $value,
            'cast' => $cast
        ];

        /** Prepare column that need a strict query and/or need to cast */
        $needCastValue = false;
        if ($strict) {
            if (!empty($cast) && isset($cast['by']) && isset($cast['into'])) {
                if (strtolower($cast['by']) == 'column' || strtolower($cast['by']) == 'coloumn' || strtolower($cast['by']) == 'field') {
                    $column = $this->_prepareCast($column, $cast['into'], $strict);
                } else if (strtolower($cast['by']) == 'value') {
                    $needCastValue = true;
                }
            } else {
                $column = "`{$column}`";
            }
        }

        /** Check if last having is a group bracket */
        $lastIsGroupBracket = false;
        if (isset($this->having) && is_array($this->having)) {
            $lastIsGroupBracket = (end($this->having) == "(");
        }

        $havingQuery = null;
        switch (strtolower($operator)) {
            case '!=':
            case '<>':
                $parameters  = $this->_prepareParameters($value, false);
                $this->havingParameters[] = $parameters;

                /** Prepare cast value */
                $castedParametersKey = [];
                if ($needCastValue) {
                    foreach ($parameters as $key => $value) {
                        $castedParametersKey[] = $this->_prepareCast($key, $cast['into'], $strict);
                    }
                } else {
                    $castedParametersKey = array_keys($parameters);
                }

                if ($this->wordpress) {
                    $havingQuery = "{$column} != '" . implode("', '", $castedParametersKey) . "'"; // not neccesaraly using implode cause this will always
                } else {
                    $havingQuery = "{$column} != ?";
                }
                break;
            case '>':
            case '>=':
            case '<=':
                $parameters  = $this->_prepareParameters($value, false);
                $this->havingParameters[] = $parameters;

                /** Prepare cast value */
                $castedParametersKey = [];
                if ($needCastValue) {
                    foreach ($parameters as $key => $value) {
                        $castedParametersKey[] = $this->_prepareCast($key, $cast['into'], $strict);
                    }
                } else {
                    $castedParametersKey = array_keys($parameters);
                }

                if ($this->wordpress) {
                    $havingQuery = "{$column} {$operator} '" . implode("', '", $castedParametersKey) . "'"; // not neccesaraly using implode cause this will always
                } else {
                    $havingQuery = "{$column} {$operator} ?";
                }
                break;
            case '=':
            default:
                $parameters  = $this->_prepareParameters($value, false);
                $this->havingParameters[] = $parameters;

                /** Prepare cast value */
                $castedParametersKey = [];
                if ($needCastValue) {
                    foreach ($parameters as $key => $value) {
                        $castedParametersKey[] = $this->_prepareCast($key, $cast['into'], $strict);
                    }
                } else {
                    $castedParametersKey = array_keys($parameters);
                }

                if ($this->wordpress) {
                    $havingQuery = "{$column} = '" . implode("', '", $castedParametersKey) . "'"; // not neccesaraly using implode cause this will always
                } else {
                    $havingQuery = "{$column} = ?";
                }
                break;
        }

        /** Add start of group bracket
         * end of group bracket will imploded in build having query
         * that will run in function "build()"
         */
        if ($lastIsGroupBracket) {
            $havingQuery = '(' . $havingQuery;
        }

        /** Add relation to query */
        if (!in_array(strtolower($relation), ['and', 'or'])) {
            $relation = 'AND';
        }

        if (isset($this->having) && is_array($this->having) && count($this->having) >= 1) {
            if ($lastIsGroupBracket) {
                /** Add relation if $lastIsGroupBracket and $lastIsGroupBracket is not in first index */
                $this->having[count($this->having) - 1] = (count($this->having) - 1) > 0 ? " {$relation} {$havingQuery}" : $havingQuery;
            } else {
                $this->having[] = " {$relation} {$havingQuery}";
            }
        } else {
            $this->having[] = $havingQuery;
        }

        return $this;
    }

    /**
     * Set Limit function
     *
     * @param Int|null $limit
     * @param Int|null $offset
     * @return self
     */
    public function limit(?Int $limit, ?Int $offset): self
    {
        if (isset($limit) && is_numeric($limit) && $limit > 0) {
            $this->limit = $limit;

            if (isset($offset) && is_numeric($offset)) {
                $this->offset = $offset;
            }
        }

        return $this;
    }

    /**
     * Join query function
     *
     * @param string $table - table to join
     * @param string $firstColumn
     * @param string $operator
     * @param string $secondColumn
     * @param Array $conditions - can be array associative or list with structure each element : 'column', 'operator', and 'value'
     * @param string $type - accepted value : self | left | right | inner | full or full-outer | cross
     * @return self
     */
    public function join(string $table, string $firstColumn, string $operator, string $secondColumn, array $conditions = [], ?string $type = 'self', ?string $alias = NULL, Bool $joinSubquery = false): self
    {
        if (!in_array(strtolower($type), ["self", "left", "right", "inner", "full", "full-outer", "cross"])) {
            throw new Exception("Join type must be one of : self | left | right | inner | full or full-outer | cross!");
        }

        if (array_is_list($conditions)) {
            foreach ($conditions as $condition) {
                if (count(array_diff_key(array_flip(['column', 'operator', 'value']), $condition)) > 0) {
                    throw new Exception("Join condition must array-associative or list with structure each element : 'column', 'operator', and 'value'!");
                }
            }
        } else {
            if (count(array_diff_key(array_flip(['column', 'operator', 'value']), $conditions)) > 0) {
                throw new Exception("Join condition must array-associative or list with structure each element : 'column', 'operator', and 'value'!");
            }
        }

        $this->joins[] = [
            'type'  => strtolower($type),
            'table' => $table,
            'alias' => $alias,
            'firstColumn'   => $firstColumn,
            'secondColumn'  => $secondColumn,
            'operator'      => $operator,
            'conditions'    => array_is_list($conditions) ? $conditions : [$conditions],
            'joinSubquery'  => $joinSubquery
        ];

        return $this;
    }

    /**
     * Add string query as subquery function
     *
     * @param string $subquery
     * @return self
     */
    public function subquery($subquery): self
    {
        $this->subquery = "({$subquery})";
        return $this;
    }

    /**
     * Set data for insert / save function
     *
     * @param array $data
     * @return self
     */
    public function data(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Insert function
     *
     * @param array $data
     * @param string|null $execution
     * @return mixed
     */
    public function insert(array $data = [], ?string $execution = 'execute'): mixed
    {
        if (!empty($data) && $data !== NULL) {
            $this->insertData = $data;
        } else {
            $this->insertData = isset($this->data) ? $this->data : [];
        }

        if (!isset($this->insertData) || empty($this->insertData)) {
            throw new Exception('Please provide data to insert!');
        }

        if (strtolower($execution) == 'self') {
            return $this;
        } else if (strtolower($execution) == 'build') {
            $query = $this->build();

            $query = $this->wpdb->prepare($query, $this->_prepareQueryParameter());

            /** Reset property */
            $this->_reset();

            return $query;
        } else {
            $query = $this->build();

            if ($this->wordpress) {
                // $result = $this->wpdb->insert($this->wpdb->prepare($query, $this->_prepareQueryParameter()), 'ARRAY_A');
                $result = $this->wpdb->query($this->wpdb->prepare($query, $this->_prepareQueryParameter()), 'ARRAY_A');
            } else {
                $result = $this->execute($query);
            }

            /** Reset property */
            $this->_reset();

            return $result;
        }
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @param array $condition
     * @param string|null $execution
     * @return mixed
     */
    public function update(array $data = [], array $condition = [], string $execution = 'execute'): mixed
    {
        if ((!isset($this->where) || empty($this->where)) && (empty($condition) || $condition === NULL)) {
            throw new Exception('Please provide condition!');
        } else if (!empty($condition)) {
            foreach ($condition as $field => $condition) {
                if (is_array($condition)) {
                    if (!isset($condition['operator']) || !in_array($condition['operator'], ['isnull', 'is null', 'isnotnull', 'is not null', 'like', 'in', 'notin', 'not in', '!=', '<>', '>', '>=', '<=', '='])) {
                        continue;
                    }

                    $this->where($field, $condition['operator'], $condition['value'], (isset($condition['relation']) ? $condition['relation'] : 'AND'), (isset($condition['cast']) ? $condition['cast'] : []), (isset($condition['strict']) ? $condition['strict'] : false));
                } else {
                    $this->where($field, "=", $condition, 'AND', [], false, false);
                }
            }
        }

        if (!empty($data) && $data !== NULL) {
            $this->updateData = $data;
        } else {
            $this->updateData = isset($this->data) ? $this->data : [];
        }

        if (!isset($this->updateData) || empty($this->updateData)) {
            throw new Exception('Please provide data to update!');
        }

        if (strtolower($execution) == 'self') {
            return $this;
        } else if (strtolower($execution) == 'build') {
            $query = $this->build();
            $query = $this->wpdb->prepare($query, $this->_prepareQueryParameter());

            /** Reset property */
            $this->_reset();

            return $query;
        } else {
            $query = $this->build($this->wordpress);

            if ($this->wordpress) {
                $result = $this->wpdb->query($this->wpdb->prepare($query, $this->_prepareQueryParameter()), 'ARRAY_A');
            } else {
                $result = $this->execute($query);
            }

            /** Reset property */
            $this->_reset();

            return $result;
        }
    }

    /**
     * Insert / update using update on duplicate key update function
     *
     * @param array $data
     * @param array $set
     * @param string|null $execution
     * @return mixed
     */
    public function upsert(array $data = [], array $set = [], string $execution = 'execute'): mixed
    {
        if (!empty($data) && $data !== NULL) {
            $this->upsertData = $data;
        } else {
            $this->upsertData = isset($this->data) ? $this->data : [];
        }

        if (!empty($set) && $set !== NULL) {
            $this->upsertSetData = $set;
        } else {
            $this->upsertSetData = isset($this->updateData) ? $this->updateData : [];
        }


        if (!isset($this->upsertData) || empty($this->upsertData)) {
            throw new Exception('Please provide data to insert or update!');
        }

        if (!isset($this->upsertSetData) || empty($this->upsertSetData)) {
            throw new Exception('Please provide data to update!');
        }

        if (strtolower($execution) == 'self') {
            return $this;
        } else if (strtolower($execution) == 'build') {
            $query = $this->wpdb->prepare($this->build(), $this->_prepareQueryParameter());

            /** Reset property */
            $this->_reset();

            return $query;
        } else {
            $query = $this->build();

            if ($this->wordpress) {
                $result = $this->wpdb->query($this->wpdb->prepare($query, $this->_prepareQueryParameter()), 'ARRAY_A');
            } else {
                $result = $this->execute($query);
            }

            /** Reset property */
            $this->_reset();

            return $result;
        }

        $this->updateData = $data;
        return $this;
    }

    /**
     * Delete query builder function
     *
     * @param array $condition - must be array assoc with "field name" as key, and each "value" is array assoc with key : operator, cast, strict, relation
     * @param string $execution - "build" to return string sql query, "execute" to run the query and return results. Default is "execute"
     * @return mixed
     */
    public function delete(array $condition = [], string $execution = 'execute'): mixed
    {
        if ((!isset($this->where) || empty($this->where)) && (empty($condition) || $condition === NULL)) {
            throw new Exception('Please provide condition!');
        } else if (!empty($condition)) {
            // $this->where = array_unique(array_merge($this->where, $condition));
            foreach ($condition as $field => $condition) {
                if (is_array($condition)) {
                    if (!isset($condition['operator']) || !in_array($condition['operator'], ['isnull', 'is null', 'isnotnull', 'is not null', 'like', 'in', 'insubquery', 'in-subquery', 'in-sub-query', 'in subquery', 'in sub-query', 'notinsubquery', 'notin-subquery', 'not-in-sub-query', 'not in subquery', 'not-in subquery', 'not in sub-query', 'not-in sub-query', 'subquery', 'between', 'regexp', 'notin', 'not in', '!=', '<>', '>', '>=', '<=', '<', '='])) {
                        continue;
                    }

                    $this->where($field, $condition['operator'], $condition['value'], (isset($condition['relation']) ? $condition['relation'] : 'AND'), (isset($condition['cast']) ? $condition['cast'] : []), (isset($condition['strict']) ? $condition['strict'] : false));
                } else {
                    $this->where($field, "=", $condition, 'AND', [], false, false);
                }
            }
        }

        $this->delete = true;

        if (strtolower($execution) == 'self') {
            return $this;
        } else if (strtolower($execution) == 'build') {
            $query = $this->build();
            $query = $this->wpdb->prepare($query, $this->_prepareQueryParameter());

            /** Reset property */
            $this->_reset();

            return $query;
        } else {
            $query = $this->build($this->wordpress);

            if ($this->wordpress) {
                $result = $this->wpdb->query($this->wpdb->prepare($query, $this->_prepareQueryParameter()), 'ARRAY_A');
            } else {
                $result = $this->execute($query);
            }

            /** Reset property */
            $this->_reset();

            return $result;
        }
    }

    /**
     * Build string query function
     *
     * @return string
     */
    public function build(): string
    {
        if (is_array($this->select)) {
            $this->select = implode(', ', $this->select);
        } else {
            $this->select = '*';
        }

        if (!isset($this->query) || empty($this->query) || $this->query == '') {
            $this->query = "SELECT {$this->select} FROM {$this->table}";
        }

        if ($this->delete) {
            $this->query = $this->buildDeleteQuery();
        }

        if ($this->updateData) {
            $this->query = $this->_buildUpdateQuery();
        }

        if ($this->insertData) {
            $this->query = $this->_buildInsertQuery();
        }

        if ($this->upsertData) {
            $this->query = $this->_buildUpsertQuery();
        }

        if (!empty($this->joins)) {
            $this->query .= $this->_buildJoinQuery();
        }

        if (!empty($this->where)) {
            // $this->query .= " WHERE " . implode(' AND ', $this->where);
            $this->query .= " WHERE " . implode($this->where);
        }

        if ($this->subquery) {
            $this->query .= ' ' . $this->subquery;
        }

        if (!empty($this->groupBy)) {
            $this->query .= " GROUP BY {$this->groupBy}";
        }

        if (!empty($this->having)) {
            $this->query .= " HAVING " . implode($this->having);
        }

        if (!empty($this->orderBy)) {
            $this->query .= " ORDER BY {$this->orderBy}";
        }

        if (!empty($this->limit)) {
            $this->query .= " LIMIT {$this->limit}";
            if (!empty($this->offset)) {
                $this->query .= " OFFSET {$this->offset}";
            }
        }

        return $this->query;
    }

    /**
     * Execute select function
     *
     * @return mixed
     */
    public function get(string $execution = 'execution'): mixed
    {
        $query = $this->build();

        if (strtolower($execution) == 'build') {
            $query = $this->wpdb->prepare($query, $this->_prepareQueryParameter());

            /** Reset property */
            $this->_reset();

            return $query;
        }

        if ($this->wordpress) {
            $result = $this->wpdb->get_results($this->wpdb->prepare($query, $this->_prepareQueryParameter()), 'ARRAY_A');
        } else {
            $result = $this->execute($query);
        }

        /** Reset property */
        $this->_reset();

        return $result;
    }

    /**
     * Pluck first data from result function
     *
     * @return mixed
     */
    public function pluck(): mixed
    {
        $query = $this->build();

        if ($this->wordpress) {
            $result = $this->wpdb->get_results($this->wpdb->prepare($query, $this->_prepareQueryParameter()), 'ARRAY_A');
        } else {
            $result = $this->execute($query);
        }

        /** Reset property */
        $this->_reset();

        if (is_array($result) && !empty($result)) {
            return $result[0];
        } else {
            return $result;
        }
    }

    /**
     * Insert execution function
     *
     * @param string|null $execution
     * @return mixed
     */
    public function save(): mixed
    {
        $query = $this->build();

        if ($this->wordpress) {
            $result = $this->wpdb->get_results($this->wpdb->prepare($query, $this->_prepareQueryParameter()), 'ARRAY_A');
        } else {
            $result = $this->execute($query);
        }

        /** Reset property */
        $this->_reset();

        return $result;
    }

    private function execute(string $query)
    {
        return $this->connection->query($query);
    }

    /**
     * Build insert query function
     *
     * @return string
     */
    private function _buildInsertQuery(): string
    {
        if (array_is_list($this->insertData)) {
            if (isset($this->columns) && !empty($this->columns)) {
                if (is_array($this->columns)) {
                    $columns = "`";
                    $columns .= implode('`, `', $this->columns);
                    $columns .= "`";
                } else {
                    $columns = $this->columns;
                }
            } else {
                $columns = [];
                foreach ($this->insertData[0] as $field => $value) {
                    $columns[] = $field;
                }
            }

            if (is_array($columns)) {
                $strColumns = "`";
                $strColumns .= implode('`, `', $columns);
                $strColumns .= "`";
                $columns = $strColumns;
            }

            $values     = '';
            for ($i = 0; $i < count($this->insertData); $i++) {
                $eachDataParameter      = [];
                $parameterPlaceholders  = [];

                foreach ($this->insertData[$i] as $data) {
                    $parameter  = $this->_prepareParameters($data);
                    $eachDataParameter[]    = $parameter;
                    $parameterPlaceholders  = array_merge($parameterPlaceholders, array_keys($parameter));
                }

                /** Flatten the data parameter */
                foreach ($eachDataParameter as $parameter) {
                    $this->parameters[]         = $parameter;
                }

                $values .= "('" . implode("', '", $parameterPlaceholders) . "')";
                if ($i < (count($this->insertData) - 1)) {
                    $values .= ', ';
                }
            }
        } else {
            if (isset($this->columns) && !empty($this->columns)) {
                if (is_array($this->columns)) {
                    $columns = "`";
                    $columns .= implode('`, `', $this->columns);
                    $columns .= "`";
                } else {
                    $columns = $this->columns;
                }
            } else {
                $columns = "`";
                $columns .= implode('`, `', array_keys($this->insertData));
                $columns .= "`";
            }

            $values = "";
            $insertParameters = [];
            foreach ($this->insertData as $data) {
                $parameter = $this->_prepareParameters($data);
                $insertParameters = array_merge($insertParameters, array_keys($parameter));
                $this->parameters[] = $parameter;
            }

            $values .= "('" . implode("', '", array_values($insertParameters)) . "')";
        }

        return "INSERT INTO {$this->table} ({$columns}) VALUES {$values}";
    }

    /**
     * Build insert on duplicate key update query function
     *
     * @return string
     */
    private function _buildUpsertQuery(): string
    {
        if (array_is_list($this->upsertData)) {
            if (isset($this->columns) && !empty($this->columns)) {
                $columns = $this->columns;
            } else {
                $columns = [];
                foreach ($this->upsertData[0] as $field => $value) {
                    $columns[] = $field;
                }
            }

            $columns    = implode(', ', $columns);
            $values     = '';
            for ($i = 0; $i < count($this->upsertData); $i++) {
                $eachDataParameter      = [];
                $parameterPlaceholders  = [];

                foreach ($this->upsertData[$i] as $data) {
                    if ($data[0] == 'query:' || strpos($data, 'query:') === 0 || strpos($data, 'query:') === "0") {
                        // list($queryPlaceHolder, $data) = explode('query:', $data);
                        $eachDataParameter[] = $data;
                    } else {
                        $parameter  = $this->_prepareParameters($data);
                        $eachDataParameter[]    = $parameter;
                        $parameterPlaceholders  = array_merge($parameterPlaceholders, array_keys($parameter));
                    }
                }

                /** Flatten the data parameter */
                foreach ($eachDataParameter as $parameter) {
                    $this->parameters[] = $parameter;
                }

                // $values .= "('" . implode("', '", $parameterPlaceholders) . "')";
                $values .= "(";
                for ($i = 0; $i < count($parameterPlaceholders); $i++) {
                    if ($parameterPlaceholders[$i][0] == 'query:' || strpos($parameterPlaceholders[$i], 'query:') === 0 || strpos($parameterPlaceholders[$i], 'query:') === "0") {
                        list($queryPlaceHolder, $data) = explode('query:', $parameterPlaceholders[$i]);
                        $values .= $data;
                    } else {
                        $values .= "'{$parameterPlaceholders[$i]}'";
                    }

                    if ($i < count($parameterPlaceholders) - 1) {
                        $values .= ', ';
                    }
                }
                $values .= ")";

                if ($i < (count($this->upsertData) - 1)) {
                    $values .= ', ';
                }
            }

            $update = '';
            foreach ($this->upsertSetData as $field => $setValues) {
                if ($setValues[0] == 'query:' || strpos($setValues, 'query:') === 0 || strpos($setValues, 'query:') === "0") {
                    list($queryPlaceHolder, $setValues) = explode('query:', $setValues);
                    $update .= "{$field} = {$setValues}";
                } else if ($setValues[0] == ':' || strpos($setValues, ':') === 0 || strpos($setValues, ':') === "0") {
                    $update .= "{$field} = VALUES($field)" . substr($setValues, 1);
                } else {
                    $update .= "{$field} = VALUES($field)";
                }

                if ($field == array_key_last($this->upsertSetData)) {
                    $update .= ';';
                } else {
                    $update .= ', ';
                }
            }
        } else {
            if (isset($this->columns) && !empty($this->columns)) {
                $columns = $this->columns;
            } else {
                $columns = implode(', ', array_keys($this->upsertData));
            }

            $values = "";
            $parametersKey = [];
            foreach ($this->upsertData as $data) {
                if ((is_array($data) && $data[0] == 'query:') || (is_string($data) && strpos($data, 'query:') === 0) || (is_string($data) && strpos($data, 'query:') === "0")) {
                    // list($queryPlaceHolder, $data) = explode('query:', $data);
                    $parametersKey[] = $data;
                } else {
                    $parameter = $this->_prepareParameters($data);
                    $parametersKey = array_merge($parametersKey, array_keys($parameter));
                    $this->parameters[] = $parameter;
                }
            }

            // $values .= "('" . implode("', '", $parametersKey) . "')";
            $values .= "(";
            for ($i = 0; $i < count($parametersKey); $i++) {
                if ((is_array($parametersKey[$i]) && $parametersKey[$i][0] == 'query:') || strpos($parametersKey[$i], 'query:') === 0 || strpos($parametersKey[$i], 'query:') === "0") {
                    list($queryPlaceHolder, $data) = explode('query:', $parametersKey[$i]);
                    $values .= $data;
                } else {
                    $values .= "'{$parametersKey[$i]}'";
                }

                if ($i < count($parametersKey) - 1) {
                    $values .= ', ';
                }
            }
            $values .= ")";
        }

        $update = '';
        foreach ($this->upsertSetData as $field => $setValues) {
            if ($setValues === NULL) {
                $update .= "{$field} = NULL";
            } else if (is_string($setValues)) {
                if (strpos($setValues, 'query:') === 0 || strpos($setValues, 'query:') === "0") {
                    list($queryPlaceHolder, $setValues) = explode('query:', $setValues);
                    $update .= "{$field} = {$setValues}";
                } else if (strpos($setValues, ':') === 0 || strpos($setValues, ':') === "0") {
                    $update .= "{$field} = VALUES($field)" . substr($setValues, 1);
                } else {
                    $update .= "{$field} = VALUES($field)";
                }
            } else if (is_array($setValues)) {
                if (($setValues[0] == 'query:')) {
                    $update .= "{$field} = VALUES($field)" . $setValues[1];
                } else if (($setValues[0] == ':')) {
                    $update .= "{$field} = VALUES($field)" . $setValues[1];
                } else {
                    $update .= "{$field} = VALUES($field)";
                }
            } else {
                $update .= "{$field} = VALUES($field)";
            }

            if ($field == array_key_last($this->upsertSetData)) {
                $update .= ';';
            } else {
                $update .= ', ';
            }
        }

        return "INSERT INTO {$this->table} ({$columns}) VALUES {$values} ON DUPLICATE KEY UPDATE {$update}";
    }

    /**
     * Delete query function
     *
     * @return string
     */
    private function buildDeleteQuery(): string
    {
        return "DELETE FROM {$this->table}";
    }

    /**
     * Build update query function
     *
     * @param boolean $isWordpress
     * @return string
     */
    private function _buildUpdateQuery(Bool $isWordpress = false): string
    {
        $setClause  = [];

        foreach ($this->updateData as $column => $value) {
            $parameter  = $this->_prepareParameters($value);
            if ($this->wordpress) {
                $setClause[] = "`{$column}` = '" . implode(', ', array_keys($parameter)) . "'";
            } else {
                $setClause[] = "`{$column}` = ?";
            }

            if (!isset($this->parameters) || $this->parameters === NULL) {
                $this->parameters = [];
            }

            // $this->parameters[] = array_merge($this->parameters, $parameter);
            $this->parameters[] = $parameter;
        }

        return "UPDATE {$this->table} SET " . implode(', ', $setClause);
    }

    /**
     * Join query function
     *
     * @return string
     * @throws Exception - If join conditions is not an array list
     */
    private function _buildJoinQuery(): string
    {
        $query = '';
        foreach ($this->joins as $join) {
            switch (strtolower($join['type'])) {
                case "left":
                    $query .= " LEFT JOIN";
                    break;
                case "right":
                    $query .= " RIGHT JOIN";
                    break;
                case "inner":
                    $query .= " INNER JOIN";
                    break;
                case "full":
                case "full-outer":
                    $query .= " FULL JOIN";
                    break;
                case "cross":
                    $query .= " CROSS JOIN";
                    break;
                case "self":
                default:
                    $query .= " JOIN";
                    break;
            }

            if ($join['joinSubquery'] && !(substr($join['table'], 0, 1) === '(' && substr($join['table'], -1) === ')')) {
                $query .= " ({$join['table']})";
            } else {
                $query .= " {$join['table']}";
            }
            if (isset($join['alias']) && $join['alias'] !== NULL && $join['alias'] !== "") {
                $query .= " AS {$join['alias']}";
            }

            $query .= " ON {$join['firstColumn']} {$join['operator']} {$join['secondColumn']}";

            if (array_key_exists('conditions', $join)) {
                if (array_is_list($join['conditions'])) {
                    if (isset($join['conditions']) && !empty($join['conditions'])) {
                        foreach ($join['conditions'] as $condition) {
                            $condition['value'] = $this->_prepareQueryValue('where', $condition['value']);
                            $parameter = $this->_prepareParameters($condition['value']);
                            $this->parameters[] = $parameter;
                            $query .= " AND {$condition['column']} {$condition['operator']} " . array_keys($parameter)[0];
                        }
                    }
                } else {
                    throw new Exception("join conditions must be array of list!");
                }
            }
        }

        return $query;
    }

    /**
     * Prepare parameter for mysql prepare statement function
     *
     * @param mixed $value
     * @return array
     */
    private function _prepareParameters(mixed $value, bool $bindType = true): array
    {
        /** Determine the data type of the value */
        switch (true) {
            case is_int($value):
                if ($this->wordpress) {
                    return ['%d' => $value];
                } else if ($bindType) {
                    return ['i' => $value];
                } else {
                    return ['?' => $value];
                }
            case is_bool($value):
                $value = $value ? 1 : 0;
                if ($this->wordpress) {
                    return ['%d' => $value];
                } else if ($bindType) {
                    return ['i' => $value];
                } else {
                    return ['?' => $value];
                }
            case is_float($value):
                if ($this->wordpress) {
                    return ['%f' => $value];
                } else if ($bindType) {
                    return ['d' => $value];
                } else {
                    return ['?' => $value];
                }
            case is_string($value):
            case $value === NULL:
                if ($this->wordpress) {
                    return ['%s' => $value];
                } else if ($bindType) {
                    return ['s' => $value];
                } else {
                    return ['?' => $value];
                }
            default:
                throw new Exception("Unsupported data type for parameter");
        }
    }

    /**
     * Setup query value function
     *
     * @param string $type
     * @param mixed $value
     * @return mixed
     */
    private function _prepareQueryValue(string $type, mixed $value): mixed
    {
        switch (strtolower($type)) {
            case 'where':
                if (is_array($value)) {
                    $value = implode(',', $value);
                } else if (is_string($value)) {
                    if (strpos($value, '(', 0) !== false && strpos($value, ')', -1) !== false) {
                        $strlen = strlen($value);
                        $value = substr($value, 1, $strlen);

                        $strlen = strlen($value);
                        $value = substr($value, 0, $strlen - 1);
                    }

                    if (strpos($value, '\'', 0) !== false && strpos($value, '\'', -1) !== false) {
                        $strlen = strlen($value);
                        $value = substr($value, 0, $strlen);

                        $strlen = strlen($value);
                        $value = substr($value, 0, $strlen - 1);
                    }
                } else if ($value === NULL) {
                    $value = 'NULL';
                }

                return $value;
            default:
                return $value;
        }
    }

    /**
     * Get parameter value for prepare query statement function
     *
     * @return array
     */
    private function _prepareQueryParameter(): array
    {
        $parameterValue = [];
        if (is_array($this->parameters)) {
            foreach ($this->parameters as $parameter) {
                $paramVal = is_array($parameter) ? array_values($parameter) : $parameter;
                $parameterValue = array_merge($parameterValue, (is_array($paramVal) ? $paramVal : [$paramVal]));
            }
        }

        if (is_array($this->whereParameters)) {
            foreach ($this->whereParameters as $parameter) {
                $parameterValue = array_merge($parameterValue, array_values($parameter));
            }
        }

        if (is_array($this->havingParameters)) {
            foreach ($this->havingParameters as $parameter) {
                $parameterValue = array_merge($parameterValue, array_values($parameter));
            }
        }

        return $parameterValue;
    }

    /**
     * Prepare cast query function
     *
     * @param string $source
     * @param string $into
     * @param boolean $strict
     * @return string
     */
    private function _prepareCast(string $source, string $into, bool $strict = true): string
    {
        switch (strtolower($into)) {
            case 'date':
                return ($strict ? "CAST(`{$source}` AS DATE)" : "CAST({$source} AS DATE)");
            case 'datetime':
                return ($strict ? "CAST(`{$source}` AS DATETIME)" : "CAST({$source} AS DATETIME)");
            case 'time':
                return ($strict ? "CAST(`{$source}` AS TIME)" : "CAST({$source} AS TIME)");
            case 'decimal':
                return ($strict ? "CAST(`{$source}` AS DECIMAL)" : "CAST({$source} AS DECIMAL)");
            case 'char':
            case 'character':
                return ($strict ? "CAST(`{$source}` AS CHAR)" : "CAST({$source} AS CHAR)");
            case 'nchar':
            case 'ncharacter':
                return ($strict ? "CAST(`{$source}` AS NCHAR)" : "CAST({$source} AS NCHAR)");
            case 'signed':
            case 'signedinteger':
                return ($strict ? "CAST(`{$source}` AS SIGNED)" : "CAST({$source} AS SIGNED)");
            case 'unsigned':
            case 'unsignedinteger':
                return ($strict ? "CAST(`{$source}` AS UNSIGNED)" : "CAST({$source} AS UNSIGNED)");
            case 'binary':
                return ($strict ? "CAST(`{$source}` AS BINARY)" : "CAST({$source} AS BINARY)");
            default:
                return $source;
        }
    }

    /**
     * Reset property value function
     *
     * @return void
     */
    private function _reset()
    {
        foreach ($this as $key => $value) {
            if (!in_array($key, ['connection', 'host', 'username', 'password', 'database', 'wordpress', 'wpdb'])) {
                $this->$key = NULL;
            }
        }
    }
}
