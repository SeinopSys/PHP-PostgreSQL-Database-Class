<?php

$_ = [
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
    'INSERT_RETURN_WRONG_DATA_TYPE_ARRAY' => 0x305,

    'GETONE_QUERY_MISMATCH' => 0x400,
    'GETONE_RETURNING_WRONG_STRUCTURE' => 0x401,
    'GETONE_RETURNING_WRONG_DATA' => 0x402,
    'GETONE_QUERY_COLUMN_MISMATCH' => 0x403,
    'GETONE_RETURNING_WRONG_DATA_TYPE_INT' => 0x402,
    'GETONE_RETURNING_WRONG_DATA_TYPE_STRING' => 0x402,
    'GETONE_RETURNING_COLUMN_WRONG_DATA' => 0x403,

    'WHERE_QUERY_MISMATCH' => 0x500,
    'WHERE_RETURNING_WRONG_DATA' => 0x501,
    'WHERE_QUERY_LITERAL_MISMATCH' => 0x502,
    'WHERE_QUERY_ARRAY_MISMATCH' => 0x503,
    'WHERE_RETURNING_WRONG_DATA_TYPE_INT' => 0x504,
    'WHERE_RETURNING_WRONG_DATA_TYPE_STRING' => 0x505,
    'WHERE_QUERY_BETWEEN_ARRAY_MISMATCH' => 0x506,
    'WHERE_QUERY_NULL_MISMATCH' => 0x507,

    'ORDERBY_QUERY_MISMATCH' => 0x600,
    'ORDERBY_RETURNING_WRONG_DATA' => 0x601,
    'ORDERBY_RETURNING_WRONG_DATA_TYPE_INT' => 0x602,
    'ORDERBY_RETURNING_WRONG_DATA_TYPE_STRING' => 0x603,

    'QUERY_QUERY_MISMATCH' => 0x700,
    'QUERY_RETURNING_WRONG_DATA' => 0x701,
    'QUERY_ARRAY_QUERY_MISMATCH' => 0x702,
    'QUERY_ARRAY_RETURNING_WRONG_DATA' => 0x703,

    'COUNT_QUERY_MISMATCH' => 0x800,
    'COUNT_RETURNING_WRONG_DATA_TYPE' => 0x801,
    'COUNT_RETURNING_WRONG_DATA' => 0x802,

    'HAS_RETURNING_WRONG_DATA_TYPE' => 0x900,
    'HAS_RETURNING_WRONG_DATA' => 0x901,

    'DELETE_WHERE_NOT_DELETING' => 0xA00,
    'DELETE_WHERE_DELETING_WRONG_ROWS' => 0xA01,
    'DELETE_NOT_DELETING' => 0xA02,
    'DELETE_QUERY_MISMATCH' => 0xA03,
    'DELETE_RETURNING_WRONG_DATA' => 0xA04,
    'DELETE_RETURNING_WRONG_DATA_TYPE_STRING' => 0xA05,

    'JOIN_QUERY_MISMATCH' => 0xB00,
    'JOIN_QUERY_ALIAS_MISMATCH' => 0xB01,

    'PDO_ERRMODE_UNCHANGED_PRECONN' => 0xC00,
    'PDO_ERRMODE_UNCHANGED_POSTCONN' => 0xC01,

    'GROUPBY_QUERY_MISMATCH' => 0xD00,
    'GROUPBY_RETURNING_WRONG_DATA' => 0xD01,

    'STRING_MISMATCH' => 0xFFF,
];

function fail($exit_key)
{
    global $_;
    if (!isset($_[$exit_key])) {
        throw new RuntimeException("FAILURE: $exit_key (invalid exit code)");
    }

    $RawExitCode = $_[$exit_key];
    $ExitCode = '0x' . strtoupper(dechex($RawExitCode));
    throw new RuntimeException("FAILURE: $exit_key ($ExitCode)\n");
}

function expect($generated, $expect, $exit_key = 'STRING_MISMATCH')
{
    if ($expect !== $generated) {
        echo "Mismatched string\nExpected: ", var_export($expect, true), "\n     Got: ", var_export($generated, true), "\n";
        fail($exit_key);
    }
}

require __DIR__ . '/src/PostgresDb.php';

use \SeinopSys\PostgresDb;

# replacePlaceHolders() checks
expect(
    PostgresDb::replacePlaceHolders('SELECT * FROM users WHERE id = ? AND name = ?', [1, 'John']),
    "SELECT * FROM users WHERE id = 1 AND name = 'John'"
);
expect(
    PostgresDb::replacePlaceHolders('SELECT * FROM users WHERE id = :id AND name = :un', [':id' => 1, ':un' => 'John']),
    "SELECT * FROM users WHERE id = 1 AND name = 'John'"
);
expect(
    PostgresDb::replacePlaceHolders('SELECT * FROM users WHERE id = :id AND name = ?', [':id' => 1, 'John']),
    "SELECT * FROM users WHERE id = 1 AND name = 'John'"
);
expect(
    PostgresDb::replacePlaceHolders('SELECT * FROM users WHERE id = :id AND name = ? AND role = ? AND password IS :pw', [':id' => 1, 'John', ':pw' => null, 'admin']),
    "SELECT * FROM users WHERE id = 1 AND name = 'John' AND role = 'admin' AND password IS NULL"
);
expect(
    PostgresDb::replacePlaceHolders('SELECT * FROM users WHERE id = :id AND name = ? AND role = ? AND password IS :pw', ['id' => 1, 'John', 'pw' => null, 'admin']),
    "SELECT * FROM users WHERE id = 1 AND name = 'John' AND role = 'admin' AND password IS NULL"
);

$db = new PostgresDb('test', 'localhost', 'postgres', '');
function checkQuery($expect, $exit_key)
{
    global $db;

    if ($db === null) {
        return false;
    }

    expect($db->getLastQuery(), $expect, $exit_key);
}

// Check tableExists & query
try {
    $db->getConnection();
} catch (Throwable $e) {
    var_dump($e);
    fail('TESTDB_CONNECTION_ERROR');
}
if ($db->tableExists('users') !== false) {
    fail('TABLEEXISTS_NOT_FALSE');
}
$db->query('CREATE TABLE "users" (id SERIAL NOT NULL, name CHARACTER VARYING(10), gender CHARACTER(1) NOT NULL)');
if ($db->tableExists('users') !== true) {
    fail('TABLEEXISTS_NOT_TRUE');
}
// Add PRIMARY KEY constraint
$db->query('ALTER TABLE "users" ADD CONSTRAINT "users_id" PRIMARY KEY ("id")');

# get() Checks
// Regular call
$users = $db->get('users');
checkQuery('SELECT * FROM "users"', 'GET_QUERY_MISMATCH');
if (!is_array($users)) {
    var_dump($users);
    fail('GET_RETURNING_WRONG_DATA');
}
// Check get with limit
$users = $db->get('users', 1);
checkQuery('SELECT * FROM "users" LIMIT 1', 'GET_QUERY_LIMIT_MISMATCH');
// Check get with array limit
$users = $db->get('users', [10, 2]);
checkQuery('SELECT * FROM "users" LIMIT 2 OFFSET 10', 'GET_QUERY_ARRAY_LIMIT_MISMATCH');
// Check get with column(s)
$users = $db->get('users', null, 'id');
checkQuery('SELECT id FROM "users"', 'GET_QUERY_COLUMNS_MISMATCH');
// Check get with complex column(s)
$users = $db->get('users', null, "'ayy-'||id as happy_id");
checkQuery("SELECT 'ayy-'||id AS happy_id FROM \"users\"", 'GET_QUERY_COLUMNS_MISMATCH');

# Check PDO error mode setting
$db->setPDOErrmode(PDO::ERRMODE_EXCEPTION);
$caught = false;
try {
    // There's no email column so we should get an exception
    $db->getOne('users', 'email');
} catch (PDOException $e) {
    $caught = true;
}
if (!$caught) {
    fail('PDO_ERRMODE_UNCHANGED_POSTCONN');
}

# count() Checks
// Call
$count = $db->count('users');
checkQuery('SELECT COUNT(*) AS cnt FROM "users" LIMIT 1', 'COUNT_QUERY_MISMATCH');
if (!is_int($count)) {
    fail('COUNT_RETURNING_WRONG_DATA_TYPE');
}
if ($count !== 0) {
    fail('COUNT_RETURNING_WRONG_DATA');
}

# has() Checks
// Call
$has = $db->has('users');
if (!is_bool($has)) {
    fail('HAS_RETURNING_WRONG_DATA_TYPE');
}
if ($has !== false) {
    fail('HAS_RETURNING_WRONG_DATA');
}

# insert() Checks
$return = $db->insert('users', ['name' => 'David', 'gender' => 'm']);
checkQuery('INSERT INTO "users" ("name", gender) VALUES (\'David\', \'m\')', 'INSERT_QUERY_MISMATCH');
if ($return !== true) {
    fail('INSERT_RETURN_WRONG_DATA');
}


# get() format checks
$users = $db->get('users');
if (!is_array($users)) {
    fail('GET_RETURNING_WRONG_DATA');
}
if (!isset($users[0])) {
    fail('GET_RETURN_MISSING_RESULT');
}
if (isset($users[1])) {
    fail('GET_RETURN_TOO_MANY_RESULT');
}
if (!is_string($users[0]['name'])) {
    fail('GET_RETURN_WRONG_RESULT_TYPE_STRING');
}
if (!is_int($users[0]['id'])) {
    fail('GET_RETURN_WRONG_RESULT_TYPE_INT');
}

// Check insert with returning integer
$id = $db->insert('users', ['name' => 'Jon', 'gender' => 'm'], 'id');
checkQuery('INSERT INTO "users" ("name", gender) VALUES (\'Jon\', \'m\') RETURNING id', 'INSERT_QUERY_MISMATCH');
if (!is_int($id)) {
    fail('INSERT_RETURN_WRONG_DATA_TYPE_INT');
}
if ($id !== 2) {
    fail('INSERT_RETURN_WRONG_DATA');
}
// Check insert with returning string
$name = $db->insert('users', ['name' => 'Anna', 'gender' => 'f'], 'name');
checkQuery('INSERT INTO "users" ("name", gender) VALUES (\'Anna\', \'f\') RETURNING "name"', 'INSERT_QUERY_MISMATCH');
if (!is_string($name)) {
    fail('INSERT_RETURN_WRONG_DATA_TYPE_STRING');
}
if ($name !== 'Anna') {
    fail('INSERT_RETURN_WRONG_DATA');
}
// Check insert with returning multiple columns
$return = $db->insert('users', ['name' => 'Jason', 'gender' => 'm'], 'name, gender');
checkQuery('INSERT INTO "users" ("name", gender) VALUES (\'Jason\', \'m\') RETURNING "name", gender', 'INSERT_QUERY_MISMATCH');
if (!is_array($return)) {
    fail('INSERT_RETURN_WRONG_DATA_TYPE_ARRAY');
}
if ($return['name'] !== 'Jason' || $return['gender'] !== 'm') {
    fail('INSERT_RETURN_WRONG_DATA');
}

# where() Checks
// Generic use
$id1 = $db->where('id', 1)->get('users');
checkQuery('SELECT * FROM "users" WHERE id = 1', 'WHERE_QUERY_MISMATCH');
if (empty($id1) || !isset($id1[0]['id'])) {
    fail('WHERE_RETURNING_WRONG_DATA');
}
if ($id1[0]['id'] !== 1) {
    fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');
}
if ($id1[0]['name'] !== 'David') {
    fail('WHERE_RETURNING_WRONG_DATA_TYPE_STRING');
}
// Literal string check
$id1 = $db->where('"id" = 1')->get('users');
checkQuery('SELECT * FROM "users" WHERE "id" = 1', 'WHERE_QUERY_LITERAL_MISMATCH');
if (empty($id1) || !isset($id1[0]['id']) || $id1[0]['id'] != 1) {
    fail('WHERE_RETURNING_WRONG_DATA');
}
if (!is_int($id1[0]['id'])) {
    fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');
}
// Null equality check
$db->where('id', null)->get('users');
checkQuery('SELECT * FROM "users" WHERE id IS NULL', 'WHERE_QUERY_NULL_MISMATCH');
// Array check
$id1 = $db->where('id', [1, 2])->orderBy('id')->get('users');
checkQuery('SELECT * FROM "users" WHERE id IN (1, 2) ORDER BY id ASC', 'WHERE_QUERY_ARRAY_MISMATCH');
if (empty($id1) || !isset($id1[0]['id'], $id1[1]['id']) || $id1[0]['id'] != 1 || $id1[1]['id'] != 2) {
    fail('WHERE_RETURNING_WRONG_DATA');
}
if (!is_int($id1[0]['id']) || !is_int($id1[1]['id'])) {
    fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');
}
$db->where('id', [1, 2], '!=')->orderBy('id')->get('users');
checkQuery('SELECT * FROM "users" WHERE id NOT IN (1, 2) ORDER BY id ASC', 'WHERE_QUERY_ARRAY_MISMATCH');
// Between array check
$id1 = $db->where('id', [1, 2], 'BETWEEN')->orderBy('id')->get('users');
checkQuery('SELECT * FROM "users" WHERE id BETWEEN 1 AND 2 ORDER BY id ASC', 'WHERE_QUERY_BETWEEN_ARRAY_MISMATCH');
if (empty($id1) || !isset($id1[0]['id'], $id1[1]['id']) || $id1[0]['id'] != 1 || $id1[1]['id'] != 2) {
    fail('WHERE_RETURNING_WRONG_DATA');
}
if (!is_int($id1[0]['id']) || !is_int($id1[1]['id'])) {
    fail('WHERE_RETURNING_WRONG_DATA_TYPE_INT');
}

# getOne() Checks
// Generic call
$first_user = $db->where('id', 1)->getOne('users');
checkQuery('SELECT * FROM "users" WHERE id = 1 LIMIT 1', 'GETONE_QUERY_MISMATCH');
if (isset($first_user[0]) && is_array($first_user[0])) {
    fail('GETONE_RETURNING_WRONG_STRUCTURE');
}
if ($first_user['id'] != 1) {
    fail('GETONE_RETURNING_COLUMN_WRONG_DATA');
}
if (!is_int($first_user['id'])) {
    fail('GETONE_RETURNING_WRONG_DATA_TYPE_INT');
}
if (!is_string($first_user['name'])) {
    fail('GETONE_RETURNING_WRONG_DATA_TYPE_STRING');
}
// Columns
$first_user = $db->where('id', 1)->getOne('users', 'id, name');
checkQuery('SELECT id, "name" FROM "users" WHERE id = 1 LIMIT 1', 'GETONE_QUERY_COLUMN_MISMATCH');
if ($first_user['id'] != 1) {
    fail('GETONE_RETURNING_COLUMN_WRONG_DATA');
}
if (!is_int($first_user['id'])) {
    fail('GETONE_RETURNING_WRONG_DATA_TYPE_INT');
}
if (!is_string($first_user['name'])) {
    fail('GETONE_RETURNING_WRONG_DATA_TYPE_STRING');
}

# orderBy() Checks
// Generic call
$last_user = $db->orderBy('id', 'DESC')->getOne('users');
checkQuery('SELECT * FROM "users" ORDER BY id DESC LIMIT 1', 'ORDERBY_QUERY_MISMATCH');
if (!isset($last_user['id'])) {
    fail('ORDERBY_RETURNING_WRONG_DATA');
}
if ($last_user['id'] != 4) {
    fail('ORDERBY_RETURNING_WRONG_DATA');
}
if (!is_int($last_user['id'])) {
    fail('ORDERBY_RETURNING_WRONG_DATA_TYPE_INT');
}
if (!is_string($last_user['name'])) {
    fail('ORDERBY_RETURNING_WRONG_DATA_TYPE_STRING');
}

# groupBy() Checks
// Generic call
$gender_count = $db->groupBy('gender')->orderBy('cnt', 'DESC')->get('users', null, 'gender, COUNT(*) as cnt');
checkQuery('SELECT gender, COUNT(*) AS cnt FROM "users" GROUP BY gender ORDER BY cnt DESC', 'GROUPBY_QUERY_MISMATCH');
if (!isset($gender_count[0]['cnt'], $gender_count[1]['cnt'], $gender_count[0]['gender'], $gender_count[1]['gender'])) {
    fail('GROUPBY_RETURNING_WRONG_DATA');
}
if ($gender_count[0]['cnt'] !== 3 || $gender_count[1]['cnt'] !== 1 || $gender_count[0]['gender'] !== 'm' || $gender_count[1]['gender'] !== 'f') {
    fail('GROUPBY_RETURNING_WRONG_DATA');
}

# query() Checks
// No bound parameters
$first_two_users = $db->query('SELECT * FROM "users" WHERE id <= 2');
checkQuery('SELECT * FROM "users" WHERE id <= 2', 'QUERY_QUERY_MISMATCH');
if (!is_array($first_two_users)) {
    fail('QUERY_RETURNING_WRONG_DATA');
}
if (count($first_two_users) !== 2) {
    fail('QUERY_RETURNING_WRONG_DATA');
}
// Bound parameters
$first_two_users = $db->query('SELECT * FROM "users" WHERE id <= ?', [2]);
checkQuery('SELECT * FROM "users" WHERE id <= 2', 'QUERY_QUERY_MISMATCH');
if (!is_array($first_two_users)) {
    fail('QUERY_RETURNING_WRONG_DATA');
}
if (count($first_two_users) !== 2) {
    fail('QUERY_RETURNING_WRONG_DATA');
}

# getLastError Check
$caught = false;
try {
    // An entry with an id of 1 already exists, we should get an exception
    $Insert = @$db->insert('users', ['id' => 1, 'name' => 'Sam', 'gender' => 'm']);
} catch (PDOException $e) {
    $caught = true;
}
if (!$caught) {
    fail('INSERT_DUPE_PRIMARY_KEY_NOT_RECOGNIZED');
}
if (strpos($db->getLastError(), 'duplicate key value violates unique constraint') === false) {
    fail('INSERT_DUPE_PRIMARY_KEY_WRONG_ERROR_MSG');
}

# count() Re-check
// Call
$count = $db->count('users');
if ($count !== 4) {
    fail('COUNT_RETURNING_WRONG_DATA');
}

# has() Checks
// Call
$has = $db->has('users');
if (!is_bool($has)) {
    fail('HAS_RETURNING_WRONG_DATA_TYPE');
}
if ($has !== true) {
    fail('HAS_RETURNING_WRONG_DATA');
}

# delete() Checks
// Call with where()
$db->where('id', 3)->delete('users');
checkQuery('DELETE FROM "users" WHERE id = 3', 'DELETE_QUERY_MISMATCH');
if ($db->where('id', 3)->has('users')) {
    fail('DELETE_WHERE_NOT_DELETING');
}
if ($db->where('id', 3, '<')->count('users') !== 2) {
    fail('DELETE_WHERE_DELETING_WRONG_ROWS');
}
// Standalone call
$db->delete('users');
checkQuery('DELETE FROM "users"', 'DELETE_QUERY_MISMATCH');
if ($db->has('users')) {
    fail('DELETE_NOT_DELETING');
}
// Array
$db->where('id', [3, 4])->delete('users');
checkQuery('DELETE FROM "users" WHERE id IN (3, 4)', 'DELETE_QUERY_MISMATCH');
// Returning data
$db->insert('users', ['id' => 10, 'name' => 'Ada', 'gender' => 'f']);
$return = $db->where('id', 10)->delete('users', ['name','gender']);
checkQuery('DELETE FROM "users" WHERE id = 10 RETURNING "name", gender', 'DELETE_QUERY_MISMATCH');
if (!is_array($return)) {
    fail('DELETE_RETURNING_WRONG_DATA');
}
if (!isset($return['name'], $return['gender']) || $return['name'] !== 'Ada' || $return['gender'] !== 'f') {
    fail('DELETE_RETURNING_WRONG_DATA_TYPE_STRING');
}


# join() Checks
$db->query('CREATE TABLE "userdata" (id SERIAL NOT NULL, somevalue INTEGER)');
$db->insert('userdata', ['somevalue' => 1]);
$db->join('userdata', 'userdata.id = users.id', 'LEFT')->where('users.id', 1)->get('users', null, 'users.*');
checkQuery('SELECT "users".* FROM "users" LEFT JOIN "userdata" ON userdata.id = users.id WHERE "users".id = 1', 'JOIN_QUERY_MISMATCH');
$db->join('userdata ud', 'ud.id = u.id', 'LEFT')->where('u.id', 1)->get('users u', null, 'u.*');
checkQuery('SELECT "u".* FROM "users" u LEFT JOIN "userdata" ud ON ud.id = u.id WHERE "u".id = 1', 'JOIN_QUERY_ALIAS_MISMATCH');
