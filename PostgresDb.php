<?php

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
    protected
        /**
         * PDO connection
         *
         * @var PDO
         */
        $_conn,
        /**
         * The SQL query to be prepared and executed
         *
         * @var string
         */
        $_query,
        /**
         * The previously executed SQL query
         *
         * @var string
         */
        $_lastQuery,
        /**
         * An array that holds where joins
         *
         * @var array
         */
        $_join = [],
        /**
         * An array that holds where conditions 'fieldName' => 'value'
         *
         * @var array
         */
        $_where = [],
        /**
         * Dynamic type list for order by condition value
         */
        $_orderBy = [],
        /**
         * Dynamic type list for group by condition value
         */
        $_groupBy = [],
        /**
         * Dynamic array that holds a combination of where condition/table data value types and parameter references
         *
         * @var array|null
         */
        $_bindParams,
        /**
         * Name of the auto increment column
         */
        $_lastInsertId,
        /**
         * Variable which holds last statement error
         *
         * @var string
         */
        $_stmtError,
        /**
         * Allows the use of the tableNameToClassName method
         *
         * @var string
         */
        $_autoClassEnabled = true,
        /**
         * Name of table we're performing the action on
         *
         * @var string
         */
        $_tableName,
        /**
         * Type of fetch to perform
         *
         * @var string
         */
        $_fetchType = PDO::FETCH_ASSOC,
        /**
         * Fetch argument
         *
         * @var string
         */
        $_fetchArg,
        /**
         * Error mode for the connection
         * Defaults to
         *
         * @var int
         */
        $_errorMode = PDO::ERRMODE_WARNING,
        /**
         * List of keywords used for escaping column names, automatically populated on connection
         *
         * @var string[]
         */
        $_sqlKeywords = [],
        /**
         * List of columns to be returned after insert/delete
         */
        $_returning = null;

    public
        /**
         * Variable which holds an amount of returned rows during queries
         *
         * @var int
         */
        $count = 0;

    private
        /**
         * Used for connecting to the database
         *
         * @var string
         */
        $_connectionString;

    const ORDERBY_RAND = 'rand()';

    public function __construct($db, $host = DB_HOST, $user = DB_USER, $pass = DB_PASS)
    {
        $this->_connectionString = "pgsql:host=$host user=$user password=$pass dbname=$db options='--client_encoding=UTF8'";
    }

    /**
     * Initiate a database connection using the data passed in the constructor
     *
     * @throws PDOException
     * @throws RuntimeException
     */
    protected function _connect()
    {
        $this->setConnection(new PDO($this->_connectionString));
        $this->_conn->setAttribute(PDO::ATTR_ERRMODE, $this->_errorMode);
    }

    /**
     * @return PDO
     * @throws RuntimeException
     * @throws PDOException
     */
    public function pdo()
    {
        if (!$this->_conn) {
            $this->_connect();
        }

        return $this->_conn;
    }

    /**
     * Allows passing any PDO object to the class, e.g. one initiated by a different library
     * Only use a different connection with this class if you know what you're doing
     *
     * @param PDO $PDO
     *
     * @throws PDOException
     * @throws RuntimeException
     */
    public function setConnection(PDO $PDO)
    {
        $this->_conn = $PDO;
        $keywords = $this->query('SELECT word FROM pg_get_keywords()');
        foreach ($keywords as $key) {
            $this->_sqlKeywords[strtolower($key['word'])] = true;
        }
    }

    /**
     * Alias of $this->pdo
     *
     * @return PDO
     * @throws PDOException
     * @throws RuntimeException
     */
    public function getConnection()
    {
        return $this->pdo();
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
        $this->_errorMode = $errmode;
        if ($this->_conn) {
            $this->_conn->setAttribute(PDO::ATTR_ERRMODE, $this->_errorMode);
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
        return $this->_errorMode;
    }

    /**
     * Method attempts to prepare the SQL query
     * and throws an error if there was a problem.
     *
     * @return PDOStatement
     * @throws RuntimeException
     */
    protected function _prepareQuery()
    {
        try {
            $stmt = $this->pdo()->prepare($this->_query);
        } catch (PDOException $e) {
            throw new RuntimeException("Problem preparing query ($this->_query) " . $e->getMessage(), $e);
        }

        return $stmt;
    }

    /**
     * Function to replace query placeholders with bound variables
     *
     * @param string $query
     * @param array $bindParams
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
        $query = preg_replace_callback('/:([a-z]+)/', function ($matches) use ($namedParams) {
            return array_key_exists($matches[1],
                $namedParams) ? self::_bindValue($namedParams[$matches[1]]) : $matches[1];
        }, $query);

        foreach ($bindParams as $param) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $query = preg_replace('/\?/', self::_bindValue($param), $query, 1);
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
    private static function _bindValue($val)
    {
        switch (gettype($val)) {
            case 'NULL':
                $val = 'NULL';
                break;
            case 'string':
                $val = "'" . preg_replace('/(^|[^\'])\'/', "''", $val) . "'";
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
    protected function _bindParam($value, $key = null)
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        if ($key === null || is_numeric($key)) {
            $this->_bindParams[] = $value;
        } else {
            $this->_bindParams[$key] = $value;
        }
    }

    /**
     * Helper function to add variables into bind parameters array in bulk
     *
     * @param array $values Variable with values
     * @param bool $ignoreKey Whether array keys should be ignored when binding
     */
    protected function _bindParams($values, $ignoreKey = false)
    {
        foreach ($values as $key => $value) {
            $this->_bindParam($value, $ignoreKey ? null : $key);
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
    protected function _buildPair($operator, $value)
    {
        $this->_bindParam($value);

        return " $operator ? ";
    }

    /**
     * Abstraction method that will build an JOIN part of the query
     */
    protected function _buildJoin()
    {
        if (empty ($this->_join)) {
            return;
        }

        foreach ($this->_join as $join) {
            list($joinType, $joinTable, $joinCondition) = $join;
            $this->_query = rtrim($this->_query) . " $joinType JOIN " . $this->_quoteTableName($joinTable) . " ON $joinCondition";
        }
    }

    protected static function _escapeApostrophe($str)
    {
        return preg_replace('~(^|[^\'])\'~', '$1\'\'', $str);
    }

    /**
     * Abstraction method that will build the part of the WHERE conditions
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function _buildWhere()
    {
        if (empty ($this->_where)) {
            return;
        }

        //Prepare the where portion of the query
        $this->_query .= ' WHERE';

        // $cond, $whereProp, $operator, $whereValue
        foreach ($this->_where as $where) {
            list($cond, $whereProp, $operator, $whereValue) = $where;
            if ($whereValue !== self::DBNULL) {
                $whereProp = $this->_quoteColumnName($whereProp);
            }

            $this->_query = rtrim($this->_query) . ' ' . trim("$cond $whereProp");

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
                        throw new \InvalidArgumentException(__METHOD__ . ' expects $whereValue to be an array when using IN/NOT IN');
                    }

                    /** @var $whereValue array */
                    foreach ($whereValue as $v) {
                        $this->_bindParam($v);
                    }
                    $this->_query .= " $operator (" . implode(', ', array_fill(0, count($whereValue), '?')) . ') ';
                    break;
                case 'NOT BETWEEN':
                case 'BETWEEN':
                    $this->_query .= " $operator ? AND ? ";
                    $this->_bindParams($whereValue, true);
                    break;
                case 'NOT EXISTS':
                case 'EXISTS':
                    $this->_query .= $operator . $this->_buildPair('', $whereValue);
                    break;
                default:
                    if (is_array($whereValue)) {
                        $this->_bindParams($whereValue);
                    } elseif ($whereValue === null) {
                        switch ($operator) {
                            case '!=':
                                $operator = 'IS NOT';
                                break;
                            case '=':
                                $operator = 'IS';
                                break;
                        }
                        $this->_query .= " $operator NULL ";
                    } elseif ($whereValue !== self::DBNULL || $whereValue === 0 || $whereValue === '0') {
                        $this->_query .= $this->_buildPair($operator, $whereValue);
                    }
            }
        }
        $this->_query = rtrim($this->_query);
    }

    /**
     * Abstraction method that will build the RETURNING clause
     *
     * @param string|string[]|null $returning What column(s) to return
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function _buildReturning($returning)
    {
        if ($returning === null) {
            return;
        }

        if (!is_array($returning)) {
            $returning = explode(',', $returning);
        }
        $this->_returning = $returning;
        $columns = [];
        foreach ($returning as $column) {
            $columns[] = $this->_quoteColumnName($column, true);
        }
        $this->_query .= ' RETURNING ' . implode(', ', $columns);
    }

    /**
     * @param mixed[] $tableData
     * @param string[] $tableColumns
     * @param bool $isInsert
     *
     * @throws RuntimeException
     */
    public function _buildDataPairs($tableData, $tableColumns, $isInsert)
    {
        foreach ($tableColumns as $column) {
            $value = $tableData[$column];
            if (!$isInsert) {
                $this->_query .= "\"$column\" = ";
            }

            // Simple value
            if (!is_array($value)) {
                $this->_bindParam($value);
                $this->_query .= '?, ';
                continue;
            }

            if ($isInsert) {
                throw new RuntimeException("Array passed as insert value for column $column");
            }

            $this->_query .= '';
            $in = [];
            foreach ($value as $k => $v) {
                if (is_int($k)) {
                    $this->_bindParam($value);
                    $in[] = '?';
                } else {
                    $this->_bindParams[$k] = $value;
                    $in[] = ":$k";
                }
            }
            $this->_query = 'IN (' . implode(', ', $in) . ')';
        }
        $this->_query = rtrim($this->_query, ', ');
    }

    /**
     * Abstraction method that will build an INSERT or UPDATE part of the query
     *
     * @param array $tableData
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    protected function _buildInsertQuery($tableData)
    {
        if (!is_array($tableData)) {
            return;
        }

        $isInsert = stripos($this->_query, 'INSERT') === 0;
        $dataColumns = array_keys($tableData);
        if ($isInsert) {
            $this->_query .= ' (' . implode(', ', $this->_quoteColumnNames($dataColumns)) . ') VALUES (';
        } else {
            $this->_query .= ' SET ';
        }

        $this->_buildDataPairs($tableData, $dataColumns, $isInsert);

        if ($isInsert) {
            $this->_query .= ')';
        }
    }

    /**
     * Abstraction method that will build the GROUP BY part of the WHERE statement
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function _buildGroupBy()
    {
        if (empty ($this->_groupBy)) {
            return;
        }

        $this->_query .= ' GROUP BY ' . implode(', ', $this->_quoteColumnNames($this->_groupBy));
    }

    /**
     * Abstraction method that will build the LIMIT part of the WHERE statement
     *
     * @throws InvalidArgumentException
     */
    protected function _buildOrderBy()
    {
        if (empty($this->_orderBy)) {
            return;
        }

        $this->_query .= ' ORDER BY ';
        $order = [];
        foreach ($this->_orderBy as $column => $dir) {
            $order[] = "$column $dir";
        }

        $this->_query .= implode(', ', $order);
    }

    /**
     * Internal function to build and execute INSERT/REPLACE calls
     *
     * @param string $tableName The name of the table.
     * @param array $insertData Data containing information for inserting into the DB.
     * @param string $operation
     * @param string|null $returnColumn What column to return after insert
     *
     * @return boolean|mixed Boolean indicating whether the insert query was completed successfully.
     *                       If $returnColumn is true then returns the matching column from the data if it exists.
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     */
    private function _buildInsert($tableName, $insertData, $operation, $returnColumn = null)
    {
        if ($this->_autoClassEnabled) {
            $this->_tableName = $tableName;
        }
        $tableName = $this->_quoteTableName($tableName);
        $this->_query = "$operation INTO $tableName";
        if ($returnColumn !== null) {
            $returnColumn = trim($returnColumn);
        }
        $stmt = $this->_buildQuery(null, $insertData, $returnColumn);

        $res = $this->_execStatement($stmt);

        if ($res === false || $this->count < 1) {
            return false;
        }

        if (is_array($res) && !empty($res[0][$returnColumn])) {
            return $res[0][$returnColumn];
        }

        return true;
    }

    /**
     * Abstraction method that will build the LIMIT part of the WHERE statement
     *
     * @param int|int[] $numRows An array to define SQL limit in format [$limit,$offset] or just $limit
     */
    protected function _buildLimit($numRows)
    {
        if ($numRows === null) {
            return;
        }

        $this->_query .= ' LIMIT ' . (
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
     * @return PDOStatement Returns the $stmt object.
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function _buildQuery($numRows = null, $tableData = null, $returning = null)
    {
        $this->_buildJoin();
        $this->_buildInsertQuery($tableData);
        $this->_buildWhere();
        $this->_buildReturning($returning);
        $this->_buildGroupBy();
        $this->_buildOrderBy();
        $this->_buildLimit($numRows);
        $this->_alterQuery();

        $this->_lastQuery = self::replacePlaceHolders($this->_query, $this->_bindParams);

        return $this->_prepareQuery();
    }

    /**
     * Execute raw SQL query.
     *
     * @param string $query User-provided query to execute.
     * @param array $bindParams Variables array to bind to the SQL statement.
     *
     * @return array Contains the returned rows from the query.
     * @throws RuntimeException
     * @throws PDOException
     */
    public function query($query, $bindParams = null)
    {
        $params = [null]; // Create the empty 0 index
        $this->_query = $query;
        $this->_alterQuery();

        $stmt = $this->_prepareQuery();
        if (empty($bindParams)) {
            $this->_bindParams = null;
        } elseif (!is_array($bindParams)) {
            throw new RuntimeException('$bindParams must be an array');
        } else {
            $this->_bindParams($bindParams);
        }

        return $this->_execStatement($stmt);
    }

    /**
     * Execute a query with specified parameters and return a single row only
     *
     * @param string $query User-provided query to execute.
     * @param array $bindParams Variables array to bind to the SQL statement.
     *
     * @return array
     * @throws RuntimeException
     * @throws PDOException
     */
    public function querySingle($query, $bindParams = null)
    {
        return $this->_singleRow($this->query($query, $bindParams));
    }

    /**
     * Get number of rows in database table
     *
     * @param string $table Name of table
     *
     * @return int
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
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
        if (count($this->_where) === 0) {
            $cond = '';
        }
        $this->_where[] = [$cond, $whereProp, strtoupper($operator), $whereValue];

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
        $this->_groupBy[] = $groupByField;

        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) ORDER BY statements for SQL queries.
     *
     * @param string $orderByColumn
     * @param string $orderByDirection
     *
     * @return self
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function orderBy($orderByColumn, $orderByDirection = 'ASC')
    {
        $orderByDirection = strtoupper(trim($orderByDirection));
        $orderByColumn = $this->_quoteColumnName($orderByColumn);

        if (!is_string($orderByDirection) || !preg_match('~^(ASC|DESC)~', $orderByDirection)) {
            throw new RuntimeException('Wrong order direction ' . $orderByDirection . ' on field ');
        }

        $this->_orderBy[$orderByColumn] = $orderByDirection;

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
        $this->_orderBy[$order] = $direction;

        return $this;
    }

    /**
     * A convenient SELECT * function.
     *
     * @param string $tableName The name of the database table to work with.
     * @param int|int[] $numRows Array to define SQL limit in format [$limit,$offset] or just $limit
     * @param string|array $columns
     *
     * @return array Contains the returned rows from the select query.
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     */
    public function get($tableName, $numRows = null, $columns = null)
    {
        if (empty($columns)) {
            $columns = '*';
        } else {
            if (!is_array($columns)) {
                $columns = explode(',', $columns);
            }
            $columns = implode(', ', $this->_quoteColumnNames($columns, true));
        }

        $table = $this->_quoteTableName($tableName);
        $this->_query = "SELECT $columns FROM $table";
        $stmt = $this->_buildQuery($numRows);

        if ($this->_autoClassEnabled) {
            $this->_tableName = $tableName;
        }

        return $this->_execStatement($stmt);
    }

    /**
     * A convenient SELECT * function to get one record.
     *
     * @param string $tableName The name of the database table to work with.
     * @param string $columns
     *
     * @return mixed|null
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
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
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
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
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     */
    public function update($tableName, $tableData)
    {
        $tableName = $this->_quoteTableName($tableName);
        $this->_query = "UPDATE $tableName";
        $stmt = $this->_buildQuery(null, $tableData);

        $res = $this->_execStatement($stmt);

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
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     */
    public function insert($tableName, $insertData, $returnColumns = null)
    {
        $this->disableAutoClass();

        return $this->_buildInsert($tableName, $insertData, 'INSERT', $returnColumns);
    }

    /**
     * Delete query. Unless you want to "truncate" the table you should first @see where
     *
     * @param string $tableName The name of the database table to work with.
     * @param string|string[]|null $returnColumns Which columns to return
     *
     * @return mixed Boolean if $returnColumns is not specified, the returned columns' values otherwise
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     */
    public function delete($tableName, $returnColumns = null)
    {
        if (!empty($this->_join) || !empty($this->_orderBy) || !empty($this->_groupBy)) {
            throw new RuntimeException(__METHOD__ . ' cannot be used with JOIN, ORDER BY or GROUP BY');
        }
        $this->disableAutoClass();

        $table = $this->_quoteTableName($tableName);
        $this->_query = "DELETE FROM $table";

        $stmt = $this->_buildQuery(null, null, $returnColumns);

        $res = $this->_execStatement($stmt);

        if ($res === false || $this->count < 1) {
            return false;
        }

        if ($this->_returning !== null) {
            if (!is_array($res)) {
                return false;
            }

            // If we got a single column to return then just return it
            if (count($this->_returning) === 1) {
                return $res[0][$this->_returning[0]];
            }

            // If we got multiple, return the entire array
            return $res[0];
        }

        return true;
    }

    /**
     * This method allows you to concatenate joins for the final SQL statement.
     *
     * @uses $db->join('table1', 'field1 <> field2', 'LEFT')
     *
     * @param string $joinTable The name of the table.
     * @param string $joinCondition the condition.
     * @param string $joinType 'LEFT', 'INNER' etc.
     * @param bool $disableAutoClass Disable automatic result conversion to class (since result may contain data from other tables)
     *
     * @return self
     * @throws RuntimeException
     */
    public function join($joinTable, $joinCondition, $joinType = '', $disableAutoClass = true)
    {
        $allowedTypes = ['LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER'];
        $joinType = strtoupper(trim($joinType));

        if ($joinType && !in_array($joinType, $allowedTypes, true)) {
            throw new RuntimeException(__METHOD__ . ' expects argument 3 to be a valid join type');
        }

        $joinTable = $this->_quoteTableName($joinTable);
        $this->_join[] = [$joinType, $joinTable, $joinCondition];

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
     * @returns boolean True if table exists
     * @throws PDOException
     * @throws RuntimeException
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
    public function setClass($class, $type = PDO::FETCH_CLASS)
    {
        $this->_fetchType = $type;
        $this->_fetchArg = $class;

        return $this;
    }

    /**
     * Disabled the tableNameToClassName method
     *
     * @return self
     */
    public function disableAutoClass()
    {
        $this->_autoClassEnabled = false;

        return $this;
    }

    /**
     * @param PDOStatement $stmt Statement to execute
     *
     * @return bool|array|mixed
     * @throws PDOException
     */
    protected function _execStatement($stmt)
    {
        $this->_lastQuery = $this->_bindParams !== null
            ? self::replacePlaceHolders($this->_query, $this->_bindParams)
            : $this->_query;

        try {
            $success = $stmt->execute($this->_bindParams);
        } catch (PDOException $e) {
            $this->_stmtError = $e->getMessage();
            $this->reset();
            throw $e;
        }

        if ($success !== true) {
            $this->count = 0;
            $errInfo = $stmt->errorInfo();
            $this->_stmtError = "PDO Error #{$errInfo[1]}: {$errInfo[2]}";
            $result = false;
        } else {
            $this->count = $stmt->rowCount();
            $this->_stmtError = null;
            $result = $this->_fetchArg !== null
                ? $stmt->fetchAll($this->_fetchType, $this->_fetchArg)
                : $stmt->fetchAll($this->_fetchType);
        }
        $this->reset();

        return $result;
    }

    public function reset()
    {
        $this->_autoClassEnabled = true;
        $this->_tableName = null;
        $this->_fetchType = PDO::FETCH_ASSOC;
        $this->_fetchArg = null;
        $this->_where = [];
        $this->_join = [];
        $this->_orderBy = [];
        $this->_groupBy = [];
        $this->_bindParams = [];
        $this->_query = null;
        $this->_lastInsertId = null;
    }

    /**
     * Get the first entry in a query if it exists, otherwise, return null
     *
     * @param array $query Array containing the query results
     *
     * @return array|null
     */
    protected function _singleRow($query)
    {
        return empty($query[0]) ? null : $query[0];
    }

    /**
     * Adds quotes around table name for use in queries
     *
     * @param string $tableName
     *
     * @return string
     */
    protected function _quoteTableName($tableName)
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
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function _quoteColumnName($columnName, $allowAs = false)
    {
        $columnName = trim($columnName);
        $hasAs = preg_match('~\S\s+AS\s+\S~i', $columnName);
        if ($allowAs && $hasAs) {
            $columnName = implode(' AS ', $this->_quoteColumnNames(preg_split('~\s+AS\s+~i', $columnName)));
        } elseif (!$allowAs && $hasAs) {
            throw new InvalidArgumentException(__METHOD__ . ": Column name ($columnName) contains disallowed AS keyword");
        }

        // JSON(B) access
        if (strpos($columnName, '->>') !== false && preg_match('~^"?([a-z_\-\d]+)"?->>\'?([\w\-]+)\'?"?$~', $columnName,
                $match)) {
            return "\"$match[1]\"" . (!empty($match[2]) ? "->>'" . self::_escapeApostrophe($match[2]) . "'" : '');
        }
        // Let's not mess with TOO complex column names (containing || or ')
        if (strpos($columnName, '||') !== false || preg_match('~\'(?<!\\\\\')~', $columnName)) {
            return $columnName;
        }

        if (strpos($columnName, '.') !== false && preg_match($dotTest = '~\.(?<!\\\\\.)~', $columnName)) {
            $split = preg_split($dotTest, $columnName);
            if (count($split) > 2) {
                throw new RuntimeException("Column $columnName contains more than one table separation dot");
            }

            return $this->_quoteTableName($split[0]) . '.' . $this->_quoteColumnName($split[1]);
        }
        if (!preg_match('~(^\w+\(|^\s*\*\s*$)~', $columnName) && (!preg_match('~^(?=[a-z_])([a-z\d_]+)$~',
                    $columnName) || isset($this->_sqlKeywords[strtolower($columnName)]))
        ) {
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
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function _quoteColumnNames($columnNames, $allowAs = false)
    {
        foreach ($columnNames as $i => $columnName) {
            $columnNames[$i] = $this->_quoteColumnName($columnName, $allowAs);
        }

        return $columnNames;
    }

    /**
     * Replaces some custom shortcuts to make the query valid
     */
    protected function _alterQuery()
    {
        $this->_query = preg_replace('~(\s+)&&(\s+)~', '$1AND$2', $this->_query);
    }

    /**
     * Method returns last executed query
     *
     * @return string
     */
    public function getLastQuery()
    {
        return $this->_lastQuery;
    }

    /**
     * Return last error message
     *
     * @return string
     */
    public function getLastError()
    {
        if (!$this->_conn) {
            return 'No connection has been made yet';
        }

        return trim($this->_stmtError);
    }

    /**
     * Returns the class name expected for the table name
     * This is a utility function for use in case you want to make your own automatic table<->class bindings using a wrapper class
     *
     * @param bool $hasNamespace
     *
     * @return string|null
     */
    public function tableNameToClassName($hasNamespace = false)
    {
        $className = $this->_tableName;
        if (is_string($className)) {
            $className = preg_replace('/s(_|$)/', '$1',
                preg_replace('/ies([-_]|$)/', 'y$1', preg_replace_callback('/(?:^|-)([a-z])/', function ($match) {
                    return strtoupper($match[1]);
                }, $className)));
            $append = $hasNamespace ? '\\' : '';
            $className = preg_replace_callback('/__?([a-z])/', function ($match) use ($append) {
                return $append . strtoupper($match[1]);
            }, $className);
        }

        return $className;
    }

    /**
     * @deprecated Renamed to query as of v2.0
     *
     * @param string $query
     * @param array $bindParams
     *
     * @return array
     * @throws RuntimeException
     * @throws PDOException
     */
    public function rawQuery($query, $bindParams = null)
    {
        trigger_error(__METHOD__ . ' has been renamed to query, please update your code accordingly',
            E_USER_DEPRECATED);

        return $this->query($query, $bindParams);
    }

    /**
     * @deprecated Renamed to query as of v2.0
     *
     * @param string $query
     * @param array $bindParams
     *
     * @return array
     * @throws RuntimeException
     * @throws PDOException
     */
    public function rawQuerySingle($query, $bindParams = null)
    {
        trigger_error(__METHOD__ . ' has been renamed to querySingle, please update your code accordingly',
            E_USER_DEPRECATED);

        return $this->querySingle($query, $bindParams);
    }

    /**
     * Close connection
     */
    public function __destruct()
    {
        if ($this->_conn) {
            $this->_conn = null;
        }
    }
}
