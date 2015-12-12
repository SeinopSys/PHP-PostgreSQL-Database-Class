<?php

	/**
	 * PostgresDb Class
	 * by @DJDavid98 | https://github.com/DJDavid98/PHP-PostgreSQL-Database-Class
	 *
	 * Heavily based on MysqliDB version 2.4 as made by
	 *   Jeffery Way <jeffrey@jeffrey-way.com>
	 *   Josh Campbell <jcampbell@ajillion.com>
	 *   Alexander V. Butenko <a.butenka@gmail.com>
	 * and licensed under GNU Public License v3
	 * (http://opensource.org/licenses/gpl-3.0.html)
	 * http://github.com/joshcam/PHP-MySQLi-Database-Class
	 **/
	class PostgresDb {
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
				$_join = array(),
			/**
			 * An array that holds where conditions 'fieldname' => 'value'
			 *
			 * @var array
			 */
				$_where = array(),
			/**
			 * Dynamic type list for order by condition value
			 */
				$_orderBy = array(),
			/**
			 * Dynamic type list for group by condition value
			 */
				$_groupBy = array(),
			/**
			 * Dynamic array that holds a combination of where condition/table data value types and parameter references
			 *
			 * @var array|null
			 */
				$_bindParams = null,
			/**
			 * Name of the auto increment column
			 *
			 */
				$_lastInsertId = null,
			/**
			 * Variable which holds last statement error
			 *
			 * @var string
			 */
				$_stmtError = null;

		public
			/**
			 * Variable which holds an amount of returned rows during get/getOne/select queries
			 *
			 * @var string
			 */
				$count = 0;

		private
			/**
			 * Used for connecting to the database
			 *
			 * @var string
			 */
				$_connstr;

		public function __construct($db, $host = DB_HOST, $user = DB_USER, $pass = DB_PASS){
			$this->_connstr = "pgsql:host=$host user=$user password=$pass dbname=$db options='--client_encoding=UTF8'";
		}

		protected function _connect(){
			try {
				$this->_conn = new PDO($this->_connstr);
				$this->_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
			}
			catch (PDOException $e){
				throw new Exception("Database connection error: ".$e->getMessage());
			}
		}

		public function pdo(){
			if (!$this->_conn){
				$this->_connect();
			}

			return $this->_conn;
		}

		/**
		 * Method attempts to prepare the SQL query
		 * and throws an error if there was a problem.
		 *
		 * @return mysqli_stmt
		 */
		protected function _prepareQuery(){
			try {
				$stmt = $this->pdo()->prepare($this->_query);
			}
			catch (PDOException $e){
				throw new Exception ("Problem preparing query ($this->_query) ".$e->getMessage());
			}

			return $stmt;
		}

		/**
		 * Function to replace ? with variables from bind variable
		 *
		 * @param string $str
		 * @param Array  $vals
		 *
		 * @return string
		 */
		protected function _replacePlaceHolders($str, $vals){
			$i = 0;
			$valcount = count($vals);

			while (strpos($str, "?") !== false && $i < $valcount){
				$val = $vals[$i++];
				if (is_object($val)){
					$val = '[object]';
				}
				else if ($val === null){
					$val = 'NULL';
				}
				if (is_string($val)){
					$val = "'$val'";
				}

				$str = preg_replace('/\?/', $val, $str, 1);
			}

			return $str;
		}

		/**
		 * Helper function to add variables into bind parameters array
		 *
		 * @param string Variable value
		 */
		protected function _bindParam($value){
			$this->_bindParams[] = $value;
		}

		/**
		 * Helper function to add variables into bind parameters array in bulk
		 *
		 * @param Array Variable with values
		 */
		protected function _bindParams($values){
			foreach ($values as $value){
				$this->_bindParam($value);
			}
		}

		/**
		 * Helper function to add variables into bind parameters array and will return
		 * its SQL part of the query according to operator in ' $operator ?'
		 *
		 * @param array $operator Variable with values
		 * @param       $value
		 *
		 * @return string
		 */
		protected function _buildPair($operator, $value){
			$this->_bindParam($value);

			return ' '.$operator.' ? ';
		}

		/**
		 * Abstraction method that will build an JOIN part of the query
		 */
		protected function _buildJoin(){
			if (empty ($this->_join)){
				return;
			}

			foreach ($this->_join as $data){
				list ($joinType, $joinTable, $joinCondition) = $data;

				$this->_query .= " $joinType JOIN ".$this->_escapeTableName($joinTable)." on $joinCondition";
			}
		}

		/**
		 * Abstraction method that will build the part of the WHERE conditions
		 */
		protected function _buildWhere(){
			if (empty ($this->_where)){
				return;
			}

			//Prepare the where portion of the query
			$this->_query .= ' WHERE';

			foreach ($this->_where as $cond){
				list ($concat, $varName, $operator, $val) = $cond;
				if (preg_match('~^[a-z_\-\d]+$~', $varName)){
					$varName = "\"$varName\"";
				}
				$this->_query .= ' '.trim("$concat $varName");

				switch (strtolower($operator)) {
					case 'not in':
					case 'in':
						$comparison = ' '.$operator.' (';
						if (is_object($val)){
							$comparison .= $this->_buildPair("", $val);
						}
						else {
							foreach ($val as $v){
								$comparison .= ' ?,';
								$this->_bindParam($v);
							}
						}
						$this->_query .= rtrim($comparison, ',').' ) ';
						break;
					case 'not between':
					case 'between':
						$this->_query .= " $operator ? AND ? ";
						$this->_bindParams($val);
						break;
					case 'not exists':
					case 'exists':
						$this->_query .= $operator.$this->_buildPair("", $val);
						break;
					default:
						if (is_array($val)){
							$this->_bindParams($val);
						}
						else if ($val === null){
							$this->_query .= $operator." NULL";
						}
						else if ($val != 'DBNULL' || $val == '0'){
							$this->_query .= $this->_buildPair($operator, $val);
						}
				}
			}
			$this->_query = rtrim($this->_query);
		}

		public function _buildDataPairs($tableData, $tableColumns, $isInsert){
			foreach ($tableColumns as $column){
				$value = $tableData[$column];
				if (!$isInsert){
					$this->_query .= "\"$column\" = ";
				}

				// Simple value
				if (!is_array($value)){
					if (is_bool($value))
						$value = $value ? 'true' : 'false';
					$this->_bindParam($value);
					$this->_query .= '?, ';
					continue;
				}
			}
			$this->_query = rtrim($this->_query, ', ');
		}

		/**
		 * Abstraction method that will build an INSERT or UPDATE part of the query
		 *
		 * @param array $tableData
		 */
		protected function _buildInsertQuery($tableData){
			if (!is_array($tableData)){
				return;
			}

			$isInsert = preg_match('/^[INSERT|REPLACE]/', $this->_query);
			$dataColumns = array_keys($tableData);
			if ($isInsert){
				$this->_query .= ' ("'.implode($dataColumns, '", "').'")  VALUES (';
			}
			else $this->_query .= " SET ";

			$this->_buildDataPairs($tableData, $dataColumns, $isInsert);

			if ($isInsert){
				$this->_query .= ')';
			}
		}

		/**
		 * Abstraction method that will build the GROUP BY part of the WHERE statement
		 *
		 */
		protected function _buildGroupBy(){
			if (empty ($this->_groupBy)){
				return;
			}

			$this->_query .= " GROUP BY ";
			foreach ($this->_groupBy as $value){
				$this->_query .= "$value, ";
			}

			$this->_query = rtrim($this->_query, ', ').' ';
		}

		/**
		 * Abstraction method that will build the LIMIT part of the WHERE statement
		 *
		 */
		protected function _buildOrderBy(){
			if (empty($this->_orderBy)){
				return;
			}

			$this->_query .= " ORDER BY ";
			foreach ($this->_orderBy as $prop => $value){
				if (strtolower(str_replace(' ', '', $prop)) == 'rand()'){
					$this->_query .= 'rand(), ';
				}
				else $this->_query .= "$prop $value, ";
			}

			$this->_query = rtrim($this->_query, ', ');
		}

		/**
		 * Internal function to build and execute INSERT/REPLACE calls
		 *
		 * @param <string $tableName The name of the table.
		 * @param array       $insertData   Data containing information for inserting into the DB.
		 * @param             $operation
		 * @param string|null $returnColumn What column to return after insert
		 *
		 * @return boolean Boolean indicating whether the insert query was completed succesfully.
		 */
		private function _buildInsert($tableName, $insertData, $operation, $returnColumn = null){
			$this->_query = "$operation INTO ".$this->_escapeTableName($tableName);
			if (!empty($returnColumn)){
				$returnColumn = trim($returnColumn);
			}
			$stmt = $this->_buildQuery(null, $insertData, $returnColumn);

			$res = $this->_execStatement($stmt);

			if ($res === false || $this->count < 1){
				return false;
			}

			if (!empty($res[0][$returnColumn])){
				return $res[0][$returnColumn];
			}

			return true;
		}

		/**
		 * Abstraction method that will build the LIMIT part of the WHERE statement
		 *
		 * @param integer|array $numRows Array to define SQL limit in format Array ($count, $offset)
		 *                               or only $count
		 */
		protected function _buildLimit($numRows){
			if (!isset($numRows)){
				return;
			}

			$this->_query .= ' LIMIT '.(
					is_array($numRows)
							? (int) $numRows[1].' OFFSET '.(int) $numRows[0]
							: (int) $numRows
					);
		}

		/**
		 * Abstraction method that will compile the WHERE statement,
		 * any passed update data, and the desired rows.
		 * It then builds the SQL query.
		 *
		 * @param integer|array $numRows   Array to define SQL limit in format Array ($count, $offset)
		 *                                 or only $count
		 * @param array         $tableData Should contain an array of data for updating the database.
		 * @param string|null   $returning What column to return after inserting
		 *
		 * @return mysqli_stmt Returns the $stmt object.
		 */
		protected function _buildQuery($numRows = null, $tableData = null, $returning = null){
			$this->_buildJoin();
			$this->_buildInsertQuery($tableData);
			if (!empty($returning)){
				$this->_query .= " RETURNING \"$returning\"";
			}
			$this->_buildWhere();
			$this->_buildGroupBy();
			$this->_buildOrderBy();
			$this->_buildLimit($numRows);
			$this->_alterQuery();

			$this->_lastQuery = $this->_replacePlaceHolders($this->_query, $this->_bindParams);

			// Prepare query
			$stmt = $this->_prepareQuery();

			return $stmt;
		}

		/**
		 * Execute raw SQL query.
		 *
		 * @param string $query      User-provided query to execute.
		 * @param array  $bindParams Variables array to bind to the SQL statement.
		 *
		 * @return array Contains the returned rows from the query.
		 */
		public function rawQuery($query, $bindParams = null){
			$params = array(null); // Create the empty 0 index
			$this->_query = $query;
			$this->_alterQuery();

			$stmt = $this->_prepareQuery();
			if (empty($bindParams))
				$this->_bindParams = null;
			else if (!is_array($bindParams))
				throw new Exception('$bindParams must be an array');
			else {
				$this->_bindParams = $bindParams;
			}

			$res = $this->_execStatement($stmt);

			return $res;
		}

		/**
		 * Execute rawQuery with specified parameters an return a single row only
		 *
		 * @param array $args Arguments to be forwarded to rawQuery
		 *
		 * @return array
		 */
		public function rawQuerySingle(...$args){
			return $this->_singleRow($this->rawQuery(...$args));
		}

		/**
		 *
		 * @param string        $query   Contains a user-provided select query.
		 * @param integer|array $numRows Array to define SQL limit in format Array ($count, $offset)
		 *
		 * @return array Contains the returned rows from the query.
		 */
		public function query($query, $numRows = null){
			$this->_query = $query;
			$stmt = $this->_buildQuery($numRows);

			$res = $this->_execStatement($stmt);

			return $res;
		}

		/**
		 * Get number of rows in database table
		 *
		 * @param string $table Name of table
		 *
		 * @return int
		 */
		public function count($table){
			return $this->getOne($table, 'COUNT(*)::int as c')['c'];
		}

		/**
		 * This method allows you to specify multiple (method chaining optional) AND WHERE statements for SQL queries.
		 *
		 * @uses $MySqliDb->where('id', 7)->where('title', 'MyTitle');
		 *
		 * @param string $whereProp  The name of the database field.
		 * @param mixed  $whereValue The value of the database field.
		 * @param string $operator
		 * @param string $cond
		 *
		 * @return MysqliDb
		 */
		public function where($whereProp, $whereValue = 'DBNULL', $operator = '=', $cond = 'AND'){
			if (is_array($whereValue) && ($key = key($whereValue)) != "0"){
				$operator = $key;
				$whereValue = $whereValue[$key];
			}
			if (count($this->_where) == 0){
				$cond = '';
			}
			$this->_where[] = array($cond, $whereProp, $operator, $whereValue);

			return $this;
		}

		/**
		 * This method allows you to specify multiple (method chaining optional) OR WHERE statements for SQL queries.
		 *
		 * @uses $MySqliDb->orWhere('id', 7)->orWhere('title', 'MyTitle');
		 *
		 * @param string $whereProp  The name of the database field.
		 * @param mixed  $whereValue The value of the database field.
		 * @param string $operator
		 *
		 * @return MysqliDb
		 */
		public function orWhere($whereProp, $whereValue = 'DBNULL', $operator = '='){
			return $this->where($whereProp, $whereValue, $operator, 'OR');
		}

		/**
		 * This method allows you to specify multiple (method chaining optional) ORDER BY statements for SQL queries.
		 *
		 * @uses $MySqliDb->orderBy('id', 'desc')->orderBy('name', 'desc');
		 *
		 * @param string $orderByField     The name of the database field.
		 * @param string $orderbyDirection Order direction.
		 *
		 * @return MysqliDb
		 */
		public function orderBy($orderByField, $orderbyDirection = "DESC"){
			$allowedDirection = array("ASC", "DESC");
			$orderbyDirection = strtoupper(trim($orderbyDirection));
			$orderByField = preg_replace('/[^-a-z0-9\.\(\),_"\*]+/i', '', $orderByField);

			if (empty($orderbyDirection) || !in_array($orderbyDirection, $allowedDirection)){
				die ('Wrong order direction: '.$orderbyDirection);
			}

			$this->_orderBy[$orderByField] = $orderbyDirection;

			return $this;
		}

		/**
		 * Replacement for the orderBy method, which would screw up complex order statements
		 *
		 * @param string $orderstr  Raw ordering sting
		 * @param string $direction Order direction
		 *
		 * @return dbObject
		 */
		public function orderByLiteral($orderstr, $direction = 'ASC'){
			$this->_orderBy[$orderstr] = $direction;

			return $this;
		}

		/**
		 * A convenient SELECT * function.
		 *
		 * @param string        $tableName The name of the database table to work with.
		 * @param integer|array $numRows   Array to define SQL limit in format Array ($count, $offset)
		 *                                 or only $count
		 * @param string        $columns
		 *
		 * @return array Contains the returned rows from the select query.
		 */
		public function get($tableName, $numRows = null, $columns = null){
			if (empty ($columns)){
				$columns = '*';
			}

			$column = is_array($columns) ? implode(', ', $columns) : $columns;

			$this->_query = "SELECT $column FROM $tableName";
			$stmt = $this->_buildQuery($numRows);

			$res = $this->_execStatement($stmt);

			return $res;
		}

		/**
		 * A convenient SELECT * function to get one record.
		 *
		 * @param string $tableName The name of the database table to work with.
		 * @param string $columns
		 *
		 * @return array Contains the returned rows from the select query.
		 */
		public function getOne($tableName, $columns = '*'){
			$res = $this->get($tableName, 1, $columns);

			if (is_array($res) && isset ($res[0])){
				return $res[0];
			}
			else if ($res){
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
		 * @return array Contains the returned rows from the select query.
		 */
		public function has($tableName){
			return $this->count($tableName) >= 1;
		}

		/**
		 * Update query. Be sure to first call the "where" method.
		 *
		 * @param string $tableName The name of the database table to work with.
		 * @param array  $tableData Array of data to update the desired row.
		 *
		 * @return boolean
		 */
		public function update($tableName, $tableData){
			$this->_query = "UPDATE $tableName";
			$stmt = $this->_buildQuery(null, $tableData);

			$res = $this->_execStatement($stmt);

			return $res;
		}

		/**
		 * Insert method to add new row
		 *
		 * @param <string $tableName The name of the table.
		 * @param array       $insertData   Data containing information for inserting into the DB.
		 * @param string|null $returnColumn Which column to return
		 *
		 * @return boolean Boolean indicating whether the insert query was completed succesfully.
		 */
		public function insert($tableName, $insertData, $returnColumn = null){
			return $this->_buildInsert($tableName, $insertData, 'INSERT', $returnColumn);
		}

		/**
		 * Delete query. Call the "where" method first.
		 *
		 * @param string        $tableName The name of the database table to work with.
		 * @param integer|array $numRows   Array to define SQL limit in format Array ($count, $offset)
		 *                                 or only $count
		 *
		 * @return boolean Indicates success. 0 or 1.
		 */
		public function delete($tableName, $numRows = null){
			$table = $this->_escapeTableName($tableName);
			if (count($this->_join)){
				$this->_query = "DELETE ".preg_replace('~^".*" (.*)$~', '$1', $table)." FROM $table";
			}
			else {
				$this->_query = "DELETE FROM $table";
			}

			$stmt = $this->_buildQuery($numRows);

			$res = $this->_execStatement($stmt);

			return $this->count > 0;
		}

		/**
		 * This method allows you to concatenate joins for the final SQL statement.
		 *
		 * @uses $MySqliDb->join('table1', 'field1 <> field2', 'LEFT')
		 *
		 * @param string $joinTable     The name of the table.
		 * @param string $joinCondition the condition.
		 * @param string $joinType      'LEFT', 'INNER' etc.
		 *
		 * @return MysqliDb
		 */
		public function join($joinTable, $joinCondition, $joinType = ''){
			$allowedTypes = array('LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER');
			$joinType = strtoupper(trim($joinType));

			if ($joinType && !in_array($joinType, $allowedTypes)){
				die("Wrong JOIN type: $joinType");
			}

			$this->_join[] = array($joinType, $joinTable, $joinCondition);

			return $this;
		}

		/**
		 * Method to check if a table exists
		 *
		 * @param array $table Table name to check
		 *
		 * @returns boolean True if table exists
		 */
		public function tableExists($table){
			$res = $this->rawQuerySingle("SELECT to_regclass('public.$table') IS NOT NULL as exists");

			return $res['exists'];
		}

		protected function _execStatement($stmt){
			$success = $stmt->execute($this->_bindParams);

			if ($success !== true){
				$errInfo = $stmt->errorInfo();
				$this->_stmtError = "PDO Error #{$errInfo[1]}: {$errInfo[2]}";
				$result = false;
				$this->count = 0;
			}
			else {
				$this->count = $stmt->rowCount();
				$this->_stmtError = null;
				$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
			}
			$this->_lastQuery = isset($this->_bindParams)
					? $this->_replacePlaceHolders($this->_query, $this->_bindParams)
					: $this->_query;
			$this->_reset();

			return $result;
		}

		protected function _reset(){
			$this->_where = array();
			$this->_join = array();
			$this->_orderBy = array();
			$this->_groupBy = array();
			$this->_bindParams = array();
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
		protected function _singleRow($query){
			return empty($query[0]) ? null : $query[0];
		}

		/**
		 * Escapes table name for use in queries
		 *
		 * @param string $tableName
		 *
		 * @return string
		 */
		protected function _escapeTableName($tableName){
			return preg_replace('~^"?([a-zA-Z\d_\-])"?\s+([a-zA-Z\d]+)$~', '"$1" $2', trim($tableName));
		}

		/**
		 * Replaces some custom shortcuts to make the query valid
		 */
		protected function _alterQuery(){
			$this->_query = preg_replace('~(\s+)&&(\s+)~', '$1AND$2', $this->_query);
		}

		/**
		 * Method returns last executed query
		 *
		 * @return string
		 */
		public function getLastQuery(){
			return $this->_lastQuery;
		}

		/**
		 * Method returns mysql error
		 *
		 * @return string
		 */
		public function getLastError(){
			if (!$this->_conn){
				return "No connection has been made yet";
			}

			return trim($this->_stmtError);
		}

		/**
		 * Close connection
		 */
		public function __destruct(){
			if ($this->_conn){
				$this->_conn = null;
			}
		}
	}
