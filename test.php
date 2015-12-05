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
		
		'ORDERBY_QUERY_MISMATCH' => 0x600,
		'ORDERBY_RETURNING_WRONG_DATA' => 0x601,
		'ORDERBY_RETURNING_WRONG_DATA_TYPE_INT' => 0x602,
		'ORDERBY_RETURNING_WRONG_DATA_TYPE_STRING' => 0x603,
		
		'RAWQUERY_QUERY_MISMATCH' => 0x700,
		'RAWQUERY_RETURNING_WRONG_DATA' => 0x701,
		
		'COUNT_QUERY_MISMATCH' => 0x800,
		'COUNT_RETURNING_WRONG_DATA_TYPE' => 0x801,
		'COUNT_RETURNING_WRONG_DATA' => 0x802,
		
		'HAS_RETURNING_WRONG_DATA_TYPE' => 0x900,
		'HAS_RETURNING_WRONG_DATA' => 0x901,

		'DELETE_WHERE_NOT_DELETING' => 0xA00,
		'DELETE_WHERE_DELETING_WRONG_ROWS' => 0xA01,
		'DELETE_NOT_DELETING' => 0xA02,
	);

	function fail($exitkey){
		global $_;
		if (!isset($_[$exitkey]))
			throw new Exception("FAILURE: $exitkey (invalid exit code)");

		$RawExitCode = $_[$exitkey];
		$ExitCode = '0x'.strtoupper(dechex($RawExitCode));
		throw new Exception("FAILURE: $exitkey ($ExitCode)\n");
	}

	require "PostgresDb.php";
	$Database = new PostgresDb('test','localhost','postgres','');
	function checkQuery($expect, $exitkey){
		global $Database;

		if (empty($Database))
			return false;

		$LastQuery = $Database->getLastQuery();
		if ($expect !== $LastQuery){
			echo "Mismatched query string:\n";
			var_dump($LastQuery);
			fail($exitkey);
		}
	}

	// Check tableExists & rawQuery
	try {
		if ($Database->tableExists('users') !== false)
			exit($_['TABLEEXISTS_NOT_FALSE']);
	}
	catch (Exception $e){
		fail('TESTDB_CONNECTION_ERROR');
	}
	$Database->rawQuery('CREATE TABLE "users" (id serial NOT NULL, name character varying(10))');
	if ($Database->tableExists('users') !== true)
		fail('TABLEEXISTS_NOT_TRUE');
	// Add PRIMARY KEY constraint
	$Database->rawQuery('ALTER TABLE "users" ADD CONSTRAINT "users_id" PRIMARY KEY ("id")');

	# get() Checks
	// Regular call
	$Users = $Database->get('users');
	checkQuery('SELECT * FROM users', 'GET_QUERY_MISMATCH');
	if (!is_array($Users)){
		var_dump($Users);
		fail('GET_RETURNING_WRONG_DATA');
	}
	// Check get with limit
	$Users = $Database->get('users',1);
	checkQuery('SELECT * FROM users LIMIT 1', 'GET_QUERY_LIMIT_MISMATCH');
	// Check get with array limit
	$Users = $Database->get('users',array(10,2));
	checkQuery('SELECT * FROM users LIMIT 2 OFFSET 10', 'GET_QUERY_ARRAY_LIMIT_MISMATCH');
	// Check get with column(s)
	$Users = $Database->get('users',null,'id');
	checkQuery('SELECT id FROM users', 'GET_QUERY_COLUMNS_MISMATCH');

	# count() Checks
	// Call
	$Count = $Database->count('users');
	checkQuery('SELECT COUNT(*)::int as c FROM users LIMIT 1', 'COUNT_QUERY_MISMATCH');
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
	$Database->insert('users',array('name' => 'David'));
	checkQuery('INSERT INTO users ("name")  VALUES (\'David\')', 'INSERT_QUERY_MISMATCH');

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
	$id = $Database->insert('users',array('name' => 'Jon'), 'id');
	checkQuery('INSERT INTO users ("name")  VALUES (\'Jon\') RETURNING "id"', 'INSERT_QUERY_MISMATCH');
	if (!is_int($id))
		fail('INSERT_RETURN_WRONG_DATA_TYPE_INT');
	if ($id !== 2)
		fail('INSERT_RETURN_WRONG_DATA');
	// Check insert with returning string
	$name = $Database->insert('users',array('name' => 'Anna'), 'name');
	checkQuery('INSERT INTO users ("name")  VALUES (\'Anna\') RETURNING "name"', 'INSERT_QUERY_MISMATCH');
	if (!is_string($name))
		fail('INSERT_RETURN_WRONG_DATA_TYPE_STRING');
	if ($name !== 'Anna')
		fail('INSERT_RETURN_WRONG_DATA');

	# where() Checks
	// Generic use
	$Id1 = $Database->where('id', 1)->get('users');
	checkQuery('SELECT * FROM users WHERE "id" = 1', 'WHERE_QUERY_MISMATCH');
	if (empty($Id1) || !isset($Id1[0]['id']))
		fail('WHERE_RETURNING_WRONG_DATA');
	if ($Id1[0]['id'] !== 1)
		fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');
	if ($Id1[0]['name'] !== 'David')
		fail('WHERE_RETURNING_WRONG_DATA_TYPE_STRING');
	// String check
	$Id1 = $Database->where('"id" = 1')->get('users');
	checkQuery('SELECT * FROM users WHERE "id" = 1', 'WHERE_QUERY_STRING_MISMATCH');
	if (empty($Id1) || !isset($Id1[0]['id']) || $Id1[0]['id'] != 1)
		fail('WHERE_RETURNING_WRONG_DATA');
	if (!is_int($Id1[0]['id']))
		fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');
	// Array check
	$Id1 = $Database->where('id',array('=' => 1))->get('users');
	checkQuery('SELECT * FROM users WHERE "id" = 1', 'WHERE_QUERY_ARRAY_MISMATCH');
	if (empty($Id1) || !isset($Id1[0]['id']) || $Id1[0]['id'] != 1)
		fail('WHERE_RETURNING_WRONG_DATA');
	if (!is_int($Id1[0]['id']))
		fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');

	# getOne() Checks
	// Generic call
	$FirstUser = $Database->where('id', 1)->getOne('users');
	checkQuery('SELECT * FROM users WHERE "id" = 1 LIMIT 1', 'GETONE_QUERY_MISMATCH');
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
	checkQuery('SELECT id, name FROM users WHERE "id" = 1 LIMIT 1', 'GETONE_QUERY_COLUMN_MISMATCH');
	if ($FirstUser['id'] != 1)
		fail('GETONE_RETURNING_COLUMN_WRONG_DATA');
	if (!is_int($FirstUser['id']))
		fail('GETONE_RETURNING_WRONG_DATA_TYPE_INT');
	if (!is_string($FirstUser['name']))
		fail('GETONE_RETURNING_WRONG_DATA_TYPE_STRING');

	# orderBy() Checks
	// Generic call
	$LastUser = $Database->orderBy('id','DESC')->getOne('users');
	checkQuery('SELECT * FROM users ORDER BY id DESC LIMIT 1','ORDERBY_QUERY_MISMATCH');
	if (!isset($LastUser['id']))
		fail('ORDERBY_RETURNING_WRONG_DATA');
	if ($LastUser['id'] != 3)
		fail('ORDERBY_RETURNING_WRONG_DATA');
	if (!is_int($LastUser['id']))
		fail('ORDERBY_RETURNING_WRONG_DATA_TYPE_INT');
	if (!is_string($LastUser['name']))
		fail('ORDERBY_RETURNING_WRONG_DATA_TYPE_STRING');

	# rawQuery() Checks
	// No bound parameteres
	$FirstTwoUsers = $Database->rawQuery('SELECT * FROM users WHERE id <= 2');
	checkQuery('SELECT * FROM users WHERE id <= 2','RAWQUERY_QUERY_MISMATCH');
	if (!is_array($FirstTwoUsers))
		fail('RAWQUERY_RETURNING_WRONG_DATA');
	if (count($FirstTwoUsers) !== 2)
		fail('RAWQUERY_RETURNING_WRONG_DATA');
	// Bound parameteres
	$FirstTwoUsers = $Database->rawQuery('SELECT * FROM users WHERE id <= ?', array(2));
	checkQuery('SELECT * FROM users WHERE id <= 2','RAWQUERY_QUERY_MISMATCH');
	if (!is_array($FirstTwoUsers))
		fail('RAWQUERY_RETURNING_WRONG_DATA');
	if (count($FirstTwoUsers) !== 2)
		fail('RAWQUERY_RETURNING_WRONG_DATA');

	# getLastError Check
	$Insert = @$Database->insert('users',array('id' => '1', 'name' => 'Sam'));
	if ($Insert !== false)
		fail('INSERT_DUPE_PRIMARY_KEY_NOT_RECOGNIZED');
	if (strpos($Database->getLastError(), 'duplicate key value violates unique constraint') === false)
		fail('INSERT_DUPE_PRIMARY_KEY_WRONG_ERROR_MSG');

	# count() Checks
	// Call
	$Count = $Database->count('users');
	if (!is_int($Count))
		fail('COUNT_RETURNING_WRONG_DATA_TYPE');
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
