<?php

namespace SeinopSys;

/**
 * PostgresDb Class
 * by @SeinopSys | https://github.com/SeinopSys/PHP-PostgreSQL-Database-Class
 * Heavily based on MysqliDB version 2.4 as made by
 *   Jeffery Way <jeffrey@jeffrey-way.com>
 *   Josh Campbell <jcampbell@ajillion.com>
 *   Alexander V. Butenko <a.butenka@gmail.com>
 * and licensed under GNU Public License v3
 * (http://opensource.org/licenses/gpl-3.0.html)
 * http://github.com/joshcam/PHP-MySQLi-Database-Class
 **/
class PostgresDb
{
    /**
     * PDO connection
     *
     * @var \PDO
     */
    protected $connection;
    /**
     * The SQL query to be prepared and executed
     *
     * @var string
     */
    protected $query;
    /**
     * The previously executed SQL query
     *
     * @var string
     */
    protected $lastQuery;
    /**
     * An array that holds where joins
     *
     * @var array
     */
    protected $join = [];
    /**
     * An array that holds where conditions 'fieldName' => 'value'
     *
     * @var array
     */
    protected $where = [];
    /**
     * Dynamic type list for order by condition value
     */
    protected $orderBy = [];
    /**
     * Dynamic type list for group by condition value
     */
    protected $groupBy = [];
    /**
     * Dynamic array that holds a combination of where condition/table data value types and parameter references
     *
     * @var array|null
     */
    protected $bindParams;
    /**
     * Variable which holds last statement error
     *
     * @var string
     */
    protected $stmtError;
    /**
     * Allows the use of the tableNameToClassName method
     *
     * @var bool
     */
    protected $autoClassEnabled = true;
    /**
     * Name of table we're performing the action on
     *
     * @var string|null
     */
    protected $tableName;
    /**
     * Type of fetch to perform
     *
     * @var int
     */
    protected $fetchType = \PDO::FETCH_ASSOC;
    /**
     * Fetch argument
     *
     * @var string
     */
    protected $fetchArg;
    /**
     * Error mode for the connection
     * Defaults to
     *
     * @var int
     */
    protected $errorMode = \PDO::ERRMODE_WARNING;
    /**
     * List of keywords used for escaping column names, automatically populated on connection
     *
     * @var string[]
     */
    protected $sqlKeywords = [];
    /**
     * List of columns to be returned after insert/delete
     *
     * @var string[]|null
     */
    protected $returning;


    /**
     * Variable which holds an amount of returned rows during queries
     *
     * @var int
     */
    public $count = 0;


    /**
     * Used for connecting to the database
     *
     * @var string
     */
    private $connectionString;

    const ORDERBY_RAND = 'rand()';

    /**
     * PostgresDb constructor
     *
     * @param string $db
     * @param string $host
     * @param string $user
     * @param string $pass
     */
    public function __construct($db = '', $host = '', $user = '', $pass = '')
    {
        $this->connectionString = <<<CONNSTR
pgsql:host=$host user=$user password=$pass dbname=$db options='--client_encoding=UTF8'
CONNSTR;
    }

    /**
     * Initiate a database connection using the data passed in the constructor
     *
     * @throws \PDOException
     * @throws \RuntimeException
     */
    protected function connect()
    {
        $this->setConnection(new \PDO($this->connectionString));
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, $this->errorMode);
    }

    /**
     * @return \PDO
     * @throws \RuntimeException
     * @throws \PDOException
     */
    public function getConnection()
    {
        if (!$this->connection) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Allows passing any PDO object to the class, e.g. one initiated by a different library
     *
     * @param \PDO $PDO
     *
     * @throws \PDOException
     * @throws \RuntimeException
     */
    public function setConnection(\PDO $PDO)
    {
        $this->connection = $PDO;
        $keywords = $this->query('SELECT word FROM pg_get_keywords()');
        foreach ($keywords as $key) {
            $this->sqlKeywords[strtolower($key['word'])] = true;
        }
    }

    /**
     * Set the error mode of the PDO instance
     * Expects a PDO::ERRMODE_* constant
     *
     * @param int
     *
     * @return self
     */
    public function setPDOErrmode($errmode)
    {
        $this->errorMode = $errmode;
        if ($this->connection) {
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, $this->errorMode);
        }

        return $this;
    }

    /**
     * Returns the error mode of the PDO instance
     *
     * @return int
     */
    public function getPDOErrmode()
    {
        return $this->errorMode;
    }

    /**
     * Method attempts to prepare the SQL query
     * and throws an error if there was a problem.
     *
     * @return \PDOStatement
     * @throws \RuntimeException
     */
    protected function prepareQuery()
    {
        try {
            $stmt = $this->getConnection()->prepare($this->query);
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                "Problem preparing query ($this->query): " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        if (is_bool($stmt)) {
            throw new \RuntimeException("Problem preparing query ($this->query). Check logs/stderr for any warnings.");
        }

        return $stmt;
    }

    /**
     * Function to replace query placeholders with bound variables
     *
     * @param string $query
     * @param array  $bindParams
     *
     * @return string
     */
    public static function replacePlaceHolders($query, $bindParams)
    {
        $namedParams = [];
        foreach ($bindParams as $key => $value) {
            if (!is_int($key)) {
                unset($bindParams[$key]);
                $namedParams[ltrim($key, ':')] = $value;
                continue;
            }
        }
        ksort($bindParams);

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $query = preg_replace_callback(
            '/:([a-z]+)/',
            function ($matches) use ($namedParams) {
                return array_key_exists(
                    $matches[1],
                    $namedParams
                ) ? self::bindValue($namedParams[$matches[1]]) : $matches[1];
            },
            $query
        );

        foreach ($bindParams as $param) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $query = preg_replace('/\?/', self::bindValue($param), $query, 1);
        }

        return $query;
    }

    /**
     * Convert a bound value to a readable string
     *
     * @param mixed $val
     *
     * @return string
     */
    private static function bindValue($val)
    {
        switch (gettype($val)) {
            case 'NULL':
                $val = 'NULL';
                break;
            case 'string':
                $val = "'" . preg_replace('/(^|[^\'])\'/', "''", $val) . "'";
                break;
            case 'boolean':
                $val = $val ? 'true' : 'false';
                break;
            default:
                $val = (string)$val;
        }

        return $val;
    }

    /**
     * Helper function to add variables into bind parameters array
     *
     * @param mixed $value Variable value
     * @param string|null $key Variable key
     */
    protected function bindParam($value, $key = null)
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        if ($key === null || is_numeric($key)) {
            $this->bindParams[] = $value;
        } else {
            $this->bindParams[$key] = $value;
        }
    }

    /**
     * Helper function to add variables into bind parameters array in bulk
     *
     * @param array $values Variable with values
     * @param bool $ignoreKey Whether array keys should be ignored when binding
     */
    protected function bindParams($values, $ignoreKey = false)
    {
        foreach ($values as $key => $value) {
            $this->bindParam($value, $ignoreKey ? null : $key);
        }
    }

    /**
     * Helper function to add variables into bind parameters array and will return
     * its SQL part of the query according to operator in ' $operator ?'
     *
     * @param string $operator
     * @param        $value
     *
     * @return string
     */
    protected function buildPair($operator, $value)
    {
        $this->bindParam($value);

        return " $operator ? ";
    }

    /**
     * Abstraction method that will build an JOIN part of the query
     */
    protected function buildJoin()
    {
        if (empty($this->join)) {
            return;
        }

        foreach ($this->join as $join) {
            list($joinType, $joinTable, $joinCondition) = $join;
            $quotedTableName = $this->quoteTableName($joinTable);
            $this->query = rtrim($this->query) . " $joinType JOIN $quotedTableName ON $joinCondition";
        }
    }

    protected static function escapeApostrophe($str)
    {
        return preg_replace('~(^|[^\'])\'~', '$1\'\'', $str);
    }

    /**
     * Abstraction method that will build the part of the WHERE conditions
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function buildWhere()
    {
        if (empty($this->where)) {
            return;
        }

        //Prepare the where portion of the query
        $this->query .= ' WHERE';

        // $cond, $whereProp, $operator, $whereValue
        foreach ($this->where as $where) {
            list($cond, $whereProp, $operator, $whereValue) = $where;
            if ($whereValue !== self::DBNULL) {
                $whereProp = $this->quoteColumnName($whereProp);
            }

            $this->query = rtrim($this->query) . ' ' . trim("$cond $whereProp");

            if ($whereValue === self::DBNULL) {
                continue;
            }

            if (is_array($whereValue)) {
                switch ($operator) {
                    case '!=':
                        $operator = 'NOT IN';
                        break;
                    case '=':
                        $operator = 'IN';
                        break;
                }
            }

            switch ($operator) {
                case 'NOT IN':
                case 'IN':
                    if (!is_array($whereValue)) {
                        throw new \InvalidArgumentException(
                            __METHOD__ . ' expects $whereValue to be an array when using IN/NOT IN'
                        );
                    }

                    /** @var $whereValue array */
                    foreach ($whereValue as $v) {
                        $this->bindParam($v);
                    }
                    $this->query .= " $operator (" . implode(', ', array_fill(0, count($whereValue), '?')) . ') ';
                    break;
                case 'NOT BETWEEN':
                case 'BETWEEN':
                    $this->query .= " $operator ? AND ? ";
                    $this->bindParams($whereValue, true);
                    break;
                case 'NOT EXISTS':
                case 'EXISTS':
                    $this->query .= $operator . $this->buildPair('', $whereValue);
                    break;
                default:
                    if (is_array($whereValue)) {
                        $this->bindParams($whereValue);
                    } elseif ($whereValue === null) {
                        switch ($operator) {
                            case '!=':
                                $operator = 'IS NOT';
                                break;
                            case '=':
                                $operator = 'IS';
                                break;
                        }
                        $this->query .= " $operator NULL ";
                    } elseif ($whereValue !== self::DBNULL || $whereValue === 0 || $whereValue === '0') {
                        $this->query .= $this->buildPair($operator, $whereValue);
                    }
            }
        }
        $this->query = rtrim($this->query);
    }

    /**
     * Abstraction method that will build the RETURNING clause
     *
     * @param string|string[]|null $returning What column(s) to return
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function buildReturning($returning)
    {
        if ($returning === null) {
            return;
        }

        if (!is_array($returning)) {
            $returning = array_map('trim', explode(',', $returning));
        }
        $this->returning = $returning;
        $columns = [];
        foreach ($returning as $column) {
            $columns[] = $this->quoteColumnName($column, true);
        }
        $this->query .= ' RETURNING ' . implode(', ', $columns);
    }

    /**
     * @param mixed[] $tableData
     * @param string[] $tableColumns
     * @param bool $isInsert
     *
     * @throws \RuntimeException
     */
    public function buildDataPairs($tableData, $tableColumns, $isInsert)
    {
        foreach ($tableColumns as $column) {
            $value = $tableData[$column];
            if (!$isInsert) {
                $this->query .= "\"$column\" = ";
            }

            // Simple value
            if (!is_array($value)) {
                $this->bindParam($value);
                $this->query .= '?, ';
                continue;
            }

            if ($isInsert) {
                throw new \RuntimeException("Array passed as insert value for column $column");
            }

            $this->query .= '';
            $in = [];
            foreach ($value as $k => $v) {
                if (is_int($k)) {
                    $this->bindParam($value);
                    $in[] = '?';
                } else {
                    $this->bindParams[$k] = $value;
                    $in[] = ":$k";
                }
            }
            $this->query = 'IN (' . implode(', ', $in) . ')';
        }
        $this->query = rtrim($this->query, ', ');
    }

    /**
     * Abstraction method that will build an INSERT or UPDATE part of the query
     *
     * @param array $tableData
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function buildInsertQuery($tableData)
    {
        if (!is_array($tableData)) {
            return;
        }

        $isInsert = stripos($this->query, 'INSERT') === 0;
        $dataColumns = array_keys($tableData);
        if ($isInsert) {
            $this->query .= ' (' . implode(', ', $this->quoteColumnNames($dataColumns)) . ') VALUES (';
        } else {
            $this->query .= ' SET ';
        }

        $this->buildDataPairs($tableData, $dataColumns, $isInsert);

        if ($isInsert) {
            $this->query .= ')';
        }
    }

    /**
     * Abstraction method that will build the GROUP BY part of the WHERE statement
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function buildGroupBy()
    {
        if (empty($this->groupBy)) {
            return;
        }

        $this->query .= ' GROUP BY ' . implode(', ', $this->quoteColumnNames($this->groupBy));
    }

    /**
     * Abstraction method that will build the LIMIT part of the WHERE statement
     *
     * @throws \InvalidArgumentException
     */
    protected function buildOrderBy()
    {
        if (empty($this->orderBy)) {
            return;
        }

        $this->query .= ' ORDER BY ';
        $order = [];
        foreach ($this->orderBy as $column => $dir) {
            $order[] = "$column $dir";
        }

        $this->query .= implode(', ', $order);
    }

    /**
     * Abstraction method that will build the LIMIT part of the WHERE statement
     *
     * @param int|int[] $numRows An array to define SQL limit in format [$limit,$offset] or just $limit
     */
    protected function buildLimit($numRows)
    {
        if ($numRows === null) {
            return;
        }

        $this->query .= ' LIMIT ' . (
            is_array($numRows)
                ? (int)$numRows[1] . ' OFFSET ' . (int)$numRows[0]
                : (int)$numRows
            );
    }

    /**
     * Abstraction method that will compile the WHERE statement,
     * any passed update data, and the desired rows.
     * It then builds the SQL query.
     *
     * @param int|int[] $numRows Array to define SQL limit in format [$limit,$offset] or just $limit
     * @param array $tableData Should contain an array of data for updating the database.
     * @param string|string[]|null $returning What column(s) to return after inserting
     *
     * @return \PDOStatement|bool Returns the $stmt object.
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function buildQuery($numRows = null, $tableData = null, $returning = null)
    {
        $this->buildJoin();
        $this->buildInsertQuery($tableData);
        $this->buildWhere();
        $this->buildReturning($returning);
        $this->buildGroupBy();
        $this->buildOrderBy();
        $this->buildLimit($numRows);
        $this->alterQuery();

        $this->lastQuery = self::replacePlaceHolders($this->query, $this->bindParams);

        return $this->prepareQuery();
    }

    /**
     * Execute raw SQL query.
     *
     * @param string $query User-provided query to execute.
     * @param array $bindParams Variables array to bind to the SQL statement.
     *
     * @return array|false Array containing the returned rows from the query or false on failure
     * @throws \RuntimeException
     * @throws \PDOException
     */
    public function query($query, $bindParams = null)
    {
        $this->query = $query;
        $this->alterQuery();

        $stmt = $this->prepareQuery();
        if (empty($bindParams)) {
            $this->bindParams = null;
        } elseif (!is_array($bindParams)) {
            throw new \RuntimeException('$bindParams must be an array');
        } else {
            $this->bindParams($bindParams);
        }

        return $this->execStatement($stmt);
    }

    /**
     * Execute a query with specified parameters and return a single row only
     *
     * @param string $query User-provided query to execute.
     * @param array $bindParams Variables array to bind to the SQL statement.
     *
     * @return array
     * @throws \RuntimeException
     * @throws \PDOException
     */
    public function querySingle($query, $bindParams = null)
    {
        return $this->singleRow($this->query($query, $bindParams));
    }

    /**
     * Get number of rows in database table
     *
     * @param string $table Name of table
     *
     * @return int
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \RuntimeException
     */
    public function count($table)
    {
        return $this->disableAutoClass()->getOne($table, 'COUNT(*) as cnt')['cnt'];
    }

    const DBNULL = '__DBNULL__';

    /**
     * This method allows you to specify multiple (method chaining optional) AND WHERE statements for SQL queries.
     *
     * @uses $db->where('id', 7)->where('title', 'MyTitle');
     *
     * @param string $whereProp The name of the database field.
     * @param mixed $whereValue The value of the database field.
     * @param string $operator
     * @param string $cond
     *
     * @return self
     */
    public function where($whereProp, $whereValue = self::DBNULL, $operator = '=', $cond = 'AND')
    {
        if (count($this->where) === 0) {
            $cond = '';
        }
        $this->where[] = [$cond, $whereProp, strtoupper($operator), $whereValue];

        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) OR WHERE statements for SQL queries.
     *
     * @uses $db->orWhere('id', 7)->orWhere('title', 'MyTitle');
     *
     * @param string $whereProp The name of the database field.
     * @param mixed $whereValue The value of the database field.
     * @param string $operator
     *
     * @return self
     */
    public function orWhere($whereProp, $whereValue = self::DBNULL, $operator = '=')
    {
        return $this->where($whereProp, $whereValue, $operator, 'OR');
    }

    public function groupBy($groupByField)
    {
        $groupByField = preg_replace('/[^-a-z0-9\.\(\),_"\*]+/i', '', $groupByField);
        $this->groupBy[] = $groupByField;

        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) ORDER BY statements for SQL queries.
     *
     * @param string $orderByColumn
     * @param string $orderByDirection
     *
     * @return self
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function orderBy($orderByColumn, $orderByDirection = 'ASC')
    {
        $orderByDirection = strtoupper(trim($orderByDirection));
        $orderByColumn = $this->quoteColumnName($orderByColumn);

        if (!is_string($orderByDirection) || !preg_match('~^(ASC|DESC)~', $orderByDirection)) {
            throw new \RuntimeException('Wrong order direction ' . $orderByDirection . ' on field ' . $orderByColumn);
        }

        $this->orderBy[$orderByColumn] = $orderByDirection;

        return $this;
    }

    /**
     * Replacement for the orderBy method, which would screw up complex order statements
     *
     * @param string $order Raw ordering sting
     * @param string $direction Order direction
     *
     * @return self
     */
    public function orderByLiteral($order, $direction = 'ASC')
    {
        $this->orderBy[$order] = $direction;

        return $this;
    }

    /**
     * A convenient SELECT * function.
     *
     * @param string $tableName The name of the database table to work with.
     * @param int|int[] $numRows Array to define SQL limit in format [$limit,$offset] or just $limit
     * @param string|array $columns
     *
     * @return array|false Contains the returned rows from the select query or false on failure
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \RuntimeException
     */
    public function get($tableName, $numRows = null, $columns = null)
    {
        if (empty($columns)) {
            $columns = '*';
        } else {
            if (!is_array($columns)) {
                $columns = explode(',', $columns);
            }
            $columns = implode(', ', $this->quoteColumnNames($columns, true));
        }

        $table = $this->quoteTableName($tableName);
        $this->query = "SELECT $columns FROM $table";
        $stmt = $this->buildQuery($numRows);

        if ($this->autoClassEnabled) {
            $this->setTableName($tableName);
        }

        return $this->execStatement($stmt);
    }

    /**
     * A convenient SELECT * function to get one record.
     *
     * @param string $tableName The name of the database table to work with.
     * @param string $columns
     *
     * @return mixed|null
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \RuntimeException
     */
    public function getOne($tableName, $columns = '*')
    {
        $res = $this->get($tableName, 1, $columns);

        if (is_array($res) && isset($res[0])) {
            return $res[0];
        }

        if ($res) {
            return $res;
        }

        return null;
    }

    /**
     * A convenient function that returns TRUE if exists at least an element that
     * satisfy the where condition specified calling the "where" method before this one.
     *
     * @param string $tableName The name of the database table to work with.
     *
     * @return bool
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \RuntimeException
     */
    public function has($tableName)
    {
        return $this->count($tableName) >= 1;
    }

    /**
     * Update query. Be sure to first call the "where" method.
     *
     * @param string $tableName The name of the database table to work with.
     * @param array $tableData Array of data to update the desired row.
     *
     * @return bool
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \RuntimeException
     */
    public function update($tableName, $tableData)
    {
        $tableName = $this->quoteTableName($tableName);
        $this->query = "UPDATE $tableName";
        $stmt = $this->buildQuery(null, $tableData);

        $res = $this->execStatement($stmt);

        return (bool)$res;
    }

    /**
     * Insert method to add a new row
     *
     * @param string $tableName The name of the table.
     * @param array $insertData Data containing information for inserting into the DB.
     * @param string|string[]|null $returnColumns Which columns to return
     *
     * @return mixed Boolean if $returnColumns is not specified, the returned columns' values otherwise
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \RuntimeException
     */
    public function insert($tableName, $insertData, $returnColumns = null)
    {
        $this->disableAutoClass();

        if ($this->autoClassEnabled) {
            $this->setTableName($tableName);
        }
        $table = $this->quoteTableName($tableName);
        $this->query = "INSERT INTO $table";

        $stmt = $this->buildQuery(null, $insertData, $returnColumns);
        $res = $this->execStatement($stmt, false);
        $return = $this->returnWithReturning($res);
        $this->reset();
        return $return;
    }

    /**
     * Delete query. Unless you want to "truncate" the table you should first @see where
     *
     * @param string $tableName The name of the database table to work with.
     * @param string|string[]|null $returnColumns Which columns to return
     *
     * @return mixed Boolean if $returnColumns is not specified, the returned columns' values otherwise
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \RuntimeException
     */
    public function delete($tableName, $returnColumns = null)
    {
        if (!empty($this->join) || !empty($this->orderBy) || !empty($this->groupBy)) {
            throw new \RuntimeException(__METHOD__ . ' cannot be used with JOIN, ORDER BY or GROUP BY');
        }
        $this->disableAutoClass();

        $table = $this->quoteTableName($tableName);
        $this->query = "DELETE FROM $table";

        $stmt = $this->buildQuery(null, null, $returnColumns);
        $res = $this->execStatement($stmt, false);
        $return = $this->returnWithReturning($res);
        $this->reset();
        return $return;
    }

    /**
     * This method allows you to concatenate joins for the final SQL statement.
     *
     * @uses $db->join('table1', 'field1 <> field2', 'LEFT')
     *
     * @param string $joinTable The name of the table.
     * @param string $joinCondition the condition.
     * @param string $joinType 'LEFT', 'INNER' etc.
     * @param bool $disableAutoClass Disable automatic result conversion to class
     *                               (since result may contain data from other tables)
     *
     * @return self
     * @throws \RuntimeException
     */
    public function join($joinTable, $joinCondition, $joinType = '', $disableAutoClass = true)
    {
        $allowedTypes = ['LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER'];
        $joinType = strtoupper(trim($joinType));

        if ($joinType && !in_array($joinType, $allowedTypes, true)) {
            throw new \RuntimeException(__METHOD__ . ' expects argument 3 to be a valid join type');
        }

        $joinTable = $this->quoteTableName($joinTable);
        $this->join[] = [$joinType, $joinTable, $joinCondition];

        if ($disableAutoClass) {
            $this->disableAutoClass();
        }

        return $this;
    }

    /**
     * Method to check if a table exists
     *
     * @param string $table Table name to check
     *
     * @return boolean True if table exists
     * @throws \PDOException
     * @throws \RuntimeException
     */
    public function tableExists($table)
    {
        $res = $this->querySingle("SELECT to_regclass('public.$table') IS NOT NULL as exists");

        return $res['exists'];
    }

    /**
     * Sets a class to be used as the PDO::fetchAll argument
     *
     * @param string $class
     * @param int $type
     *
     * @return self
     */
    public function setClass($class, $type = \PDO::FETCH_CLASS)
    {
        $this->fetchType = $type;
        $this->fetchArg = $class;

        return $this;
    }

    /**
     * Disabled the tableNameToClassName method
     *
     * @return self
     */
    public function disableAutoClass()
    {
        $this->autoClassEnabled = false;

        return $this;
    }

    /**
     * @param \PDOStatement $stmt Statement to execute
     * @param boolean $reset Whether the object should be reset (must be done manually if set to false)
     *
     * @return array|false
     * @throws \PDOException
     */
    protected function execStatement($stmt, $reset = true)
    {
        $this->lastQuery = $this->bindParams !== null
            ? self::replacePlaceHolders($this->query, $this->bindParams)
            : $this->query;

        try {
            $success = $stmt->execute($this->bindParams);
        } catch (\PDOException $e) {
            $this->stmtError = $e->getMessage();
            $this->reset();
            throw $e;
        }

        if ($success !== true) {
            $this->count = 0;
            $errInfo = $stmt->errorInfo();
            $this->stmtError = "PDO Error #{$errInfo[1]}: {$errInfo[2]}";
            $result = false;
        } else {
            $this->count = $stmt->rowCount();
            $this->stmtError = null;
            $result = $this->fetchArg !== null
                ? $stmt->fetchAll($this->fetchType, $this->fetchArg)
                : $stmt->fetchAll($this->fetchType);
        }

        if ($reset) {
            $this->reset();
        }

        return $result;
    }

    public function reset()
    {
        $this->autoClassEnabled = true;
        $this->tableName = null;
        $this->fetchType = \PDO::FETCH_ASSOC;
        $this->fetchArg = null;
        $this->where = [];
        $this->join = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->bindParams = [];
        $this->query = null;
        $this->returning = null;
    }

    /**
     * @param mixed $name
     * @throws \RuntimeException
     */
    private function setTableName($name)
    {
        if (!is_string($name)) {
            throw new \RuntimeException('Argument $table_name must be string, ' . gettype($name) . ' given');
        }
        $this->tableName = $name;
    }

    /**
     * Returns a boolean value if no data needs to be returned, otherwise returns the requested data
     *
     * @param mixed $res Result of an executed statement
     * @return bool|mixed
     */
    protected function returnWithReturning($res)
    {
        if ($res === false || $this->count < 1) {
            return false;
        }

        if ($this->returning !== null) {
            if (!is_array($res)) {
                return false;
            }

            // If we got a single column to return then just return it
            if (count($this->returning) === 1) {
                return array_values($res[0])[0];
            }

            // If we got multiple, return the entire array
            return $res[0];
        }

        return true;
    }

    /**
     * Get the first entry in a query if it exists, otherwise, return null
     *
     * @param array|false $query Array containing the query results or false
     *
     * @return array|null
     */
    protected function singleRow($query)
    {
        return $query === false || empty($query[0]) ? null : $query[0];
    }

    /**
     * Adds quotes around table name for use in queries
     *
     * @param string $tableName
     *
     * @return string
     */
    protected function quoteTableName($tableName)
    {
        return preg_replace('~^"?([a-zA-Z\d_\-]+)"?(?:\s*(\s[a-zA-Z\d]+))?$~', '"$1"$2', trim($tableName));
    }

    /**
     * Adds quotes around column name for use in queries
     *
     * @param string $columnName
     * @param bool $allowAs Controls whether "column as alias" can be used
     *
     * @return string
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function quoteColumnName($columnName, $allowAs = false)
    {
        $columnName = trim($columnName);
        $hasAs = preg_match('~\S\s+AS\s+\S~i', $columnName);
        if ($allowAs && $hasAs) {
            $columnName = implode(' AS ', $this->quoteColumnNames(preg_split('~\s+AS\s+~i', $columnName)));
        } elseif (!$allowAs && $hasAs) {
            throw new \InvalidArgumentException(
                __METHOD__ . ": Column name ($columnName) contains disallowed AS keyword"
            );
        }

        // JSON(B) access
        if (strpos($columnName, '->>') !== false && preg_match(
            '~^"?([a-z_\-\d]+)"?->>\'?([\w\-]+)\'?"?$~',
            $columnName,
            $match
        )) {
            return "\"$match[1]\"" . (!empty($match[2]) ? "->>'" . self::escapeApostrophe($match[2]) . "'" : '');
        }
        // Let's not mess with TOO complex column names (containing || or ')
        if (strpos($columnName, '||') !== false || preg_match('~\'(?<!\\\\\')~', $columnName)) {
            return $columnName;
        }

        if (strpos($columnName, '.') !== false && preg_match($dotTest = '~\.(?<!\\\\\.)~', $columnName)) {
            $split = preg_split($dotTest, $columnName);
            if (count($split) > 2) {
                throw new \RuntimeException("Column $columnName contains more than one table separation dot");
            }

            return $this->quoteTableName($split[0]) . '.' . $this->quoteColumnName($split[1]);
        }
        $functionCallOrAsterisk = preg_match('~(^\w+\(|^\s*\*\s*$)~', $columnName);
        $validColumnName = preg_match('~^(?=[a-z_])([a-z\d_]+)$~', $columnName);
        $isSqlKeyword = isset($this->sqlKeywords[strtolower($columnName)]);
        if (!$functionCallOrAsterisk && (!$validColumnName || $isSqlKeyword)) {
            return '"' . trim($columnName, '"') . '"';
        }

        return $columnName;
    }

    /**
     * Adds quotes around column name for use in queries
     *
     * @param string[] $columnNames
     * @param bool $allowAs
     *
     * @return string[]
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function quoteColumnNames($columnNames, $allowAs = false)
    {
        foreach ($columnNames as $i => $columnName) {
            $columnNames[$i] = $this->quoteColumnName($columnName, $allowAs);
        }

        return $columnNames;
    }

    /**
     * Replaces some custom shortcuts to make the query valid
     */
    protected function alterQuery()
    {
        $this->query = preg_replace('~(\s+)&&(\s+)~', '$1AND$2', $this->query);
    }

    /**
     * Method returns last executed query
     *
     * @return string
     */
    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    /**
     * Return last error message
     *
     * @return string
     */
    public function getLastError()
    {
        if (!$this->connection) {
            return 'No connection has been made yet';
        }

        return trim($this->stmtError);
    }

    /**
     * Returns the class name expected for the table name
     * This is a utility function for use in case you want to make your own
     * automatic table<->class bindings using a wrapper class
     *
     * @param bool $hasNamespace
     *
     * @return string|null
     */
    public function tableNameToClassName($hasNamespace = false)
    {
        $className = $this->tableName;

        if (is_string($className)) {
            $className = preg_replace(
                '/s(_|$)/',
                '$1',
                preg_replace('/ies([-_]|$)/', 'y$1', preg_replace_callback('/(?:^|-)([a-z])/', function ($match) {
                    return strtoupper($match[1]);
                }, $className))
            );
            $append = $hasNamespace ? '\\' : '';
            $className = preg_replace_callback('/__?([a-z])/', function ($match) use ($append) {
                return $append . strtoupper($match[1]);
            }, $className);
        }

        return $className;
    }

    /**
     * Close connection
     */
    public function __destruct()
    {
        if ($this->connection) {
            $this->connection = null;
        }
    }
}
