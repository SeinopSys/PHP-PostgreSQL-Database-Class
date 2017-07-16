<?php

	$_ = array(
		'TESTDB_CONNECTION_ERROR' => 0xFFF,
		
		'TABLEEXISTS_NOT_FALSE' => 0x100,
		'TABLEEXISTS_NOT_TRUE' => 0x101,
		
		'GET_QUERY_MISMATCH' => 0x200,
		'GET_RETURNING_WRONG_DATA' => 0x201,
		'GET_QUERY_LIMIT_MISMATCH' => 0x202,
		'GET_QUERY_ARRAY_LIMIT_MISMATCH' => 0x203,
		'GET_RETURN_MISSING_RESULT' => 0x204,
		'GET_RETURN_TOO_MANY_RESULT' => 0x205,
		'GET_RETURN_WRONG_RESULT_TYPE_STRING' => 0x206,
		'GET_RETURN_WRONG_RESULT_TYPE_INT' => 0x207,
		
		'INSERT_QUERY_MISMATCH' => 0x300,
		'INSERT_RETURN_WRONG_DATA' => 0x301,
		'INSERT_RETURN_WRONG_DATA_TYPE_INT' => 0x302,
		'INSERT_RETURN_WRONG_DATA_TYPE_STRING' => 0x303,
		'INSERT_DUPE_PRIMARY_KEY_WRONG_ERROR_MSG' => 0x304,

		'GETONE_QUERY_MISMATCH' => 0x400,
		'GETONE_RETURNING_WRONG_STRUCTURE' => 0x401,
		'GETONE_RETURNING_WRONG_DATA' => 0x402,
		'GETONE_QUERY_COLUMN_MISMATCH' => 0x403,
		'GETONE_RETURNING_WRONG_DATA_TYPE_INT' => 0x402,
		'GETONE_RETURNING_WRONG_DATA_TYPE_STRING' => 0x402,
		'GETONE_RETURNING_COLUMN_WRONG_DATA' => 0x403,
		
		'WHERE_QUERY_MISMATCH' => 0x500,
		'WHERE_RETURNING_WRONG_DATA' => 0x501,
		'WHERE_QUERY_STRING_MISMATCH' => 0x502,
		'WHERE_QUERY_ARRAY_MISMATCH' => 0x503,
		'WHERE_RETURNING_WRONG_DATA_TYPE_INT' => 0x504,
		'WHERE_RETURNING_WRONG_DATA_TYPE_STRING' => 0x505,
		'WHERE_QUERY_BETWEEN_ARRAY_MISMATCH' => 0x506,

		'ORDERBY_QUERY_MISMATCH' => 0x600,
		'ORDERBY_RETURNING_WRONG_DATA' => 0x601,
		'ORDERBY_RETURNING_WRONG_DATA_TYPE_INT' => 0x602,
		'ORDERBY_RETURNING_WRONG_DATA_TYPE_STRING' => 0x603,
		
		'RAWQUERY_QUERY_MISMATCH' => 0x700,
		'RAWQUERY_RETURNING_WRONG_DATA' => 0x701,
		'RAWQUERY_ARRAY_QUERY_MISMATCH' => 0x702,
		'RAWQUERY_ARRAY_RETURNING_WRONG_DATA' => 0x703,
		
		'COUNT_QUERY_MISMATCH' => 0x800,
		'COUNT_RETURNING_WRONG_DATA_TYPE' => 0x801,
		'COUNT_RETURNING_WRONG_DATA' => 0x802,
		
		'HAS_RETURNING_WRONG_DATA_TYPE' => 0x900,
		'HAS_RETURNING_WRONG_DATA' => 0x901,

		'DELETE_WHERE_NOT_DELETING' => 0xA00,
		'DELETE_WHERE_DELETING_WRONG_ROWS' => 0xA01,
		'DELETE_NOT_DELETING' => 0xA02,

		'JOIN_QUERY_MISMATCH' => 0xB00,

		'PDO_ERRMODE_UNCHANGED_PRECONN' => 0xC00,
		'PDO_ERRMODE_UNCHANGED_POSTCONN' => 0xC01,

		'GROUPBY_QUERY_MISMATCH' => 0xD00,
		'GROUPBY_RETURNING_WRONG_DATA' => 0xD01,
	);

	function fail($exitkey){
		global $_;
		if (!isset($_[$exitkey]))
			throw new RuntimeException("FAILURE: $exitkey (invalid exit code)");

		$RawExitCode = $_[$exitkey];
		$ExitCode = '0x'.strtoupper(dechex($RawExitCode));
		throw new RuntimeException("FAILURE: $exitkey ($ExitCode)\n");
	}

	require __DIR__.'/PostgresDb.php';
	$Database = new PostgresDb('test','localhost','postgres','');
	function checkQuery($expect, $exitkey){
		global $Database;

		if (empty($Database))
			return false;

		$LastQuery = $Database->getLastQuery();
		if ($expect !== $LastQuery){
			echo "Mismatched query string\nExpected: ".var_export($expect, true)."\n     Got: ".var_export($LastQuery, true)."\n";
			fail($exitkey);
		}
	}

	// Check tableExists & rawQuery
	try {
		$Database->pdo();
	}
	catch (Throwable $e){
		var_dump($e);
		fail('TESTDB_CONNECTION_ERROR');
	}
	if ($Database->tableExists('users') !== false)
		fail('TABLEEXISTS_NOT_FALSE');
	$Database->query('CREATE TABLE "users" (id serial NOT NULL, name character varying(10), gender character(1) NOT NULL)');
	if ($Database->tableExists('users') !== true)
		fail('TABLEEXISTS_NOT_TRUE');
	// Add PRIMARY KEY constraint
	$Database->query('ALTER TABLE "users" ADD CONSTRAINT "users_id" PRIMARY KEY ("id")');

	# get() Checks
	// Regular call
	$Users = $Database->get('users');
	checkQuery('SELECT * FROM "users"', 'GET_QUERY_MISMATCH');
	if (!is_array($Users)){
		var_dump($Users);
		fail('GET_RETURNING_WRONG_DATA');
	}
	// Check get with limit
	$Users = $Database->get('users',1);
	checkQuery('SELECT * FROM "users" LIMIT 1', 'GET_QUERY_LIMIT_MISMATCH');
	// Check get with array limit
	$Users = $Database->get('users',array(10,2));
	checkQuery('SELECT * FROM "users" LIMIT 2 OFFSET 10', 'GET_QUERY_ARRAY_LIMIT_MISMATCH');
	// Check get with column(s)
	$Users = $Database->get('users',null,'id');
	checkQuery('SELECT id FROM "users"', 'GET_QUERY_COLUMNS_MISMATCH');

	# check PDO errormode setting
	$Database->setPDOErrmode(PDO::ERRMODE_EXCEPTION);
	$caught = false;
	try {
		// There's no email column so we should get an exception
		$Database->getOne('users','email');
	}
	catch (PDOException $e){
		$caught = true;
	}
	if (!$caught)
		fail('PDO_ERRMODE_UNCHANGED_POSTCONN');

	# count() Checks
	// Call
	$Count = $Database->count('users');
	checkQuery('SELECT COUNT(*) AS cnt FROM "users" LIMIT 1', 'COUNT_QUERY_MISMATCH');
	if (!is_int($Count))
		fail('COUNT_RETURNING_WRONG_DATA_TYPE');
	if ($Count !== 0)
		fail('COUNT_RETURNING_WRONG_DATA');

	# has() Checks
	// Call
	$Has = $Database->has('users');
	if (!is_bool($Has))
		fail('HAS_RETURNING_WRONG_DATA_TYPE');
	if ($Has !== false)
		fail('HAS_RETURNING_WRONG_DATA');

	# insert() Checks
	$Database->insert('users',array('name' => 'David', 'gender' => 'm'));
	checkQuery('INSERT INTO "users" ("name", gender) VALUES (\'David\', \'m\')', 'INSERT_QUERY_MISMATCH');

	# get() format checks
	$Users = $Database->get('users');
	if (!is_array($Users))
		fail('GET_RETURNING_WRONG_DATA');
	if (!isset($Users[0]))
		fail('GET_RETURN_MISSING_RESULT');
	if (isset($Users[1]))
		fail('GET_RETURN_TOO_MANY_RESULT');
	if (!is_string($Users[0]['name']))
		fail('GET_RETURN_WRONG_RESULT_TYPE_STRING');
	if (!is_int($Users[0]['id']))
		fail('GET_RETURN_WRONG_RESULT_TYPE_INT');

	// Check insert with returning integer
	$id = $Database->insert('users',array('name' => 'Jon', 'gender' => 'm'), 'id');
	checkQuery('INSERT INTO "users" ("name", gender) VALUES (\'Jon\', \'m\') RETURNING id', 'INSERT_QUERY_MISMATCH');
	if (!is_int($id))
		fail('INSERT_RETURN_WRONG_DATA_TYPE_INT');
	if ($id !== 2)
		fail('INSERT_RETURN_WRONG_DATA');
	// Check insert with returning string
	$name = $Database->insert('users',array('name' => 'Anna', 'gender' => 'f'), 'name');
	checkQuery('INSERT INTO "users" ("name", gender) VALUES (\'Anna\', \'f\') RETURNING "name"', 'INSERT_QUERY_MISMATCH');
	if (!is_string($name))
		fail('INSERT_RETURN_WRONG_DATA_TYPE_STRING');
	if ($name !== 'Anna')
		fail('INSERT_RETURN_WRONG_DATA');

	# where() Checks
	// Generic use
	$Id1 = $Database->where('id', 1)->get('users');
	checkQuery('SELECT * FROM "users" WHERE id = 1', 'WHERE_QUERY_MISMATCH');
	if (empty($Id1) || !isset($Id1[0]['id']))
		fail('WHERE_RETURNING_WRONG_DATA');
	if ($Id1[0]['id'] !== 1)
		fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');
	if ($Id1[0]['name'] !== 'David')
		fail('WHERE_RETURNING_WRONG_DATA_TYPE_STRING');
	// String check
	$Id1 = $Database->where('"id" = 1')->get('users');
	checkQuery('SELECT * FROM "users" WHERE "id" = 1', 'WHERE_QUERY_STRING_MISMATCH');
	if (empty($Id1) || !isset($Id1[0]['id']) || $Id1[0]['id'] != 1)
		fail('WHERE_RETURNING_WRONG_DATA');
	if (!is_int($Id1[0]['id']))
		fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');
	// Array check
	$Id1 = $Database->where('id',array(1, 2))->orderBy('id')->get('users');
	checkQuery('SELECT * FROM "users" WHERE id IN (1, 2) ORDER BY "id" ASC', 'WHERE_QUERY_ARRAY_MISMATCH');
	if (empty($Id1) || !isset($Id1[0]['id']) || $Id1[0]['id'] != 1 || !isset($Id1[1]['id']) || $Id1[1]['id'] != 2)
		fail('WHERE_RETURNING_WRONG_DATA');
	if (!is_int($Id1[0]['id']) || !is_int($Id1[1]['id']))
		fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');
	// Between array check
	$Id1 = $Database->where('id',array(1, 2),'BETWEEN')->orderBy('id')->get('users');
	checkQuery('SELECT * FROM "users" WHERE id BETWEEN 1 AND 2 ORDER BY "id" ASC', 'WHERE_QUERY_BETWEEN_ARRAY_MISMATCH');
	if (empty($Id1) || !isset($Id1[0]['id']) || $Id1[0]['id'] != 1 || !isset($Id1[1]['id']) || $Id1[1]['id'] != 2)
		fail('WHERE_RETURNING_WRONG_DATA');
	if (!is_int($Id1[0]['id']) || !is_int($Id1[1]['id']))
		fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');

	# getOne() Checks
	// Generic call
	$FirstUser = $Database->where('id', 1)->getOne('users');
	checkQuery('SELECT * FROM "users" WHERE id = 1 LIMIT 1', 'GETONE_QUERY_MISMATCH');
	if (isset($FirstUser[0]) && is_array($FirstUser[0]))
		fail('GETONE_RETURNING_WRONG_STRUCTURE');
	if ($FirstUser['id'] != 1)
		fail('GETONE_RETURNING_COLUMN_WRONG_DATA');
	if (!is_int($FirstUser['id']))
		fail('GETONE_RETURNING_WRONG_DATA_TYPE_INT');
	if (!is_string($FirstUser['name']))
		fail('GETONE_RETURNING_WRONG_DATA_TYPE_STRING');
	// Columns
	$FirstUser = $Database->where('id', 1)->getOne('users','id, name');
	checkQuery('SELECT id, name FROM "users" WHERE id = 1 LIMIT 1', 'GETONE_QUERY_COLUMN_MISMATCH');
	if ($FirstUser['id'] != 1)
		fail('GETONE_RETURNING_COLUMN_WRONG_DATA');
	if (!is_int($FirstUser['id']))
		fail('GETONE_RETURNING_WRONG_DATA_TYPE_INT');
	if (!is_string($FirstUser['name']))
		fail('GETONE_RETURNING_WRONG_DATA_TYPE_STRING');

	# orderBy() Checks
	// Generic call
	$LastUser = $Database->orderBy('id','DESC')->getOne('users');
	checkQuery('SELECT * FROM "users" ORDER BY id DESC LIMIT 1','ORDERBY_QUERY_MISMATCH');
	if (!isset($LastUser['id']))
		fail('ORDERBY_RETURNING_WRONG_DATA');
	if ($LastUser['id'] != 3)
		fail('ORDERBY_RETURNING_WRONG_DATA');
	if (!is_int($LastUser['id']))
		fail('ORDERBY_RETURNING_WRONG_DATA_TYPE_INT');
	if (!is_string($LastUser['name']))
		fail('ORDERBY_RETURNING_WRONG_DATA_TYPE_STRING');

	# groupBy() Checks
	// Generic call
	$GenderCount = $Database->groupBy('gender')->orderBy('cnt','DESC')->get('users',null,'gender, COUNT(*) as cnt');
	checkQuery('SELECT gender, COUNT(*) AS cnt FROM "users" GROUP BY "gender" ORDER BY cnt DESC','GROUPBY_QUERY_MISMATCH');
	if (!isset($GenderCount[0]['cnt']) || !isset($GenderCount[1]['cnt']) || !isset($GenderCount[0]['gender']) || !isset($GenderCount[1]['gender']))
		fail('GROUPBY_RETURNING_WRONG_DATA');
	if ($GenderCount[0]['cnt'] !== 2 || $GenderCount[1]['cnt'] !== 1 || $GenderCount[0]['gender'] !== 'm' || $GenderCount[1]['gender'] !== 'f')
		fail('GROUPBY_RETURNING_WRONG_DATA');

	# rawQuery() Checks
	// No bound parameteres
	$FirstTwoUsers = $Database->query('SELECT * FROM "users" WHERE id <= 2');
	checkQuery('SELECT * FROM "users" WHERE id <= 2','RAWQUERY_QUERY_MISMATCH');
	if (!is_array($FirstTwoUsers))
		fail('RAWQUERY_RETURNING_WRONG_DATA');
	if (count($FirstTwoUsers) !== 2)
		fail('RAWQUERY_RETURNING_WRONG_DATA');
	// Bound parameteres
	$FirstTwoUsers = $Database->query('SELECT * FROM "users" WHERE id <= ?', array(2));
	checkQuery('SELECT * FROM "users" WHERE id <= 2','RAWQUERY_QUERY_MISMATCH');
	if (!is_array($FirstTwoUsers))
		fail('RAWQUERY_RETURNING_WRONG_DATA');
	if (count($FirstTwoUsers) !== 2)
		fail('RAWQUERY_RETURNING_WRONG_DATA');

	# getLastError Check
	$caught = false;
	try {
		// An entry with an id of 1 already exists, we should get an excpetion
		$Insert = @$Database->insert('users',array('id' => 1, 'name' => 'Sam', 'gender' => 'm'));
	}
	catch (PDOException $e){
		$caught = true;
	}
	if (!$caught)
		fail('INSERT_DUPE_PRIMARY_KEY_NOT_RECOGNIZED');
	if (strpos($Database->getLastError(), 'duplicate key value violates unique constraint') === false)
		fail('INSERT_DUPE_PRIMARY_KEY_WRONG_ERROR_MSG');

	# count() Re-check
	// Call
	$Count = $Database->count('users');
	if ($Count !== 3)
		fail('COUNT_RETURNING_WRONG_DATA');

	# has() Checks
	// Call
	$Has = $Database->has('users');
	if (!is_bool($Has))
		fail('HAS_RETURNING_WRONG_DATA_TYPE');
	if ($Has !== true)
		fail('HAS_RETURNING_WRONG_DATA');

	# delete() Checks
	// Call with where()
	$Database->where('id', 3)->delete('users');
	if ($Database->where('id', 3)->has('users'))
		fail('DELETE_WHERE_NOT_DELETING');
	if ($Database->where('id',array('<' => 3))->count('users') !== 2)
		fail('DELETE_WHERE_DELETING_WRONG_ROWS');
	// Standalone call
	$Database->delete('users');
	if ($Database->has('users'))
		fail('DELETE_NOT_DELETING');

	# join() Checks
	$Database->query('CREATE TABLE "userdata" (id serial NOT NULL, somevalue integer)');
	$Database->insert('userdata',[ 'somevalue' => 1 ]);
	$Database->join('userdata','userdata.id = users.id','LEFT')->where('users.id',1)->get('users',null,'users.*');
	checkQuery('SELECT users.* FROM "users" LEFT JOIN "userdata" ON userdata.id = users.id WHERE users.id = 1','JOIN_QUERY_MISMATCH');
