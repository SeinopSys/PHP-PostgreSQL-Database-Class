<h1>PostgresDb <a href="https://travis-ci.org/SeinopSys/PHP-PostgreSQL-Database-Class"><img alt="Build Status" src="https://travis-ci.org/SeinopSys/PHP-PostgreSQL-Database-Class.svg"></a></h1>

This project is a PostgreSQL version of @joshcam's [MysqliDb Class](https://github.com/joshcam/PHP-MySQLi-Database-Class), that has support for the basic functionality and syntax provided by said class, tailored specifically to PostgreSQL.

## Usage

For the examples below, the following `users` table structure is used:

| id |  name  |
|----|--------|
| 1  | Sam    |
| 2  | Alex   |
| 3  | Daniel |


### Connecting to a database

To prepare a new instance of the class for use, simply construct it:

```php
$Database = new PostgresDb($database_name, $host, $username, $password);
```

By default, initializing the class does not immediately create a connection. To force a connection attempt, call:

```php
$Database->pdo();
```

This will create (and return) the PDO object used by the class and simultaneously connect to the database. Initial connection errors can be caught by using `try…catch` but the script uses `PDO::ERRMODE_WARNING` for further errors. If you'd prefer exceptions to be thrown instead, feel free to change this to `PDO::ERRMODE_EXCEPTION`.

### Selecting [`get()`, `getOne()`]

Returns an array containing each result as an array of its own. Returns `false` if no row is found.

```php
// SELECT * FROM users
$Database->get('users');

// SELECT * FROM users LIMIT 1
$Database->get('users', 1);

// SELECT id, name FROM users LIMIT 1
$Database->get('users', 1, 'id, name');

// SELECT id, name FROM users LIMIT 2 OFFSET 1
$Database->get('users', array(1,2), 'id, name');
```

Return format:

```php
array(
    array(
        'id' => 2,
        'name' => 'Alex'
    ),
    array(
        'id' => 3,
        'name' => 'Daniel'
	)
)
```

Alternatively, you can use `getOne` to only select a single row. Returns `false` if no row is found.

```php
// SELECT * FROM users LIMIT 1
$Database->getOne('users');

// SELECT id, name FROM users LIMIT 1
$Database->getOne('users', 'id, name');
```

Return format:

```php
array(
    'id' => 1,
    'name' => 'Sam'
)
```

### Where [`where()`]

```php
// SELECT * FROM users WHERE "id" = 1 LIMIT 1
$Database->where('id', 1)->getOne('users');

// SELECT * FROM users LIMIT 1 WHERE "id" != 1
$Database->where('"id" != 1')->get('users');

// SELECT * FROM users LIMIT 1 WHERE "id" != 1
$Database->where('id', array('!=' => 1))->get('users');
$Database->where('id', 1, '!=')->get('users');
```

### Join [`join()`]

```php
// SELECT * FROM users u LEFT JOIN "messages" m ON m.user = u.id
$Database->join('messages m', 'm.user = u.id','LEFT')->get('users u');
```

### Ordering [`orderBy()`, `orderByLiteral()`]

Complex `ORDER BY` statements can be passed to `orderByLiteral` which will just use the value you provide as it is.

```php
// SELECT * FROM users ORDER BY "name" DESC
$Database->orderBy('name')->get('users');

// SELECT * FROM users ORDER BY "name" ASC
$Database->orderBy('name','ASC')->get('users');

// SELECT * FROM users ORDER BY CASE WHEN "name" IS NULL THEN 1 ELSE 0 END DESC, "name" ASC
$Database
    ->orderByLiteral('CASE WHEN "name" IS NULL THEN 1 ELSE 0 END')
    ->orderBy('name','ASC')
    ->get('users');
```

### Inserting [`insert()`]

Returns `false` on failure and `true` if successful.

```php
// INSERT INTO users ("name") VALUES ('Joe')
$Database->insert('users', array('name' => 'Joe'));
```

|  id   |  name   |
|-------|---------|
| 1     | Sam     |
| 2     | Alex    |
| 3     | Daniel  |
| **4** | **Joe** |

You  can also ask for the value of a single inserted column, in which case it'll return that instead of `true` if the insert is successful.

```php
// INSERT INTO users ("name") VALUES ('Joe') RETURNING "id"
$id = $Database->insert('users',array('name' => 'Joe'),'id');
echo $id; // 4
```

### Update [`update()`]

Returns `false` on failure and `true` if successful.

```php
// UPDATE users SET "name" = 'Dave' WHERE "id" = 1
$Database->where('id', 1)->update('users',array('name' => 'Dave'));
```

|  id   |   name   |
|-------|----------|
| **1** | **Dave** |
| 2     | Alex     |
| 3     | Daniel   |
| 4     | Joe      |

### Raw SQL queries [`rawQuery()`]

Executes the query exactly* as passed, for when it is far too complex to use the built-in methods. Return values match `get`'s.<br><sub>*`&&` is replaced with `AND` for MySQL compatibility</sub>

```php
// Query string only
$Database->rawQuery("SELECT * FROM 'users' WHERE id = 4");

// Using bound parameters
$id = 4;
$Database->rawQuery("SELECT * FROM 'users' WHERE id = ?", array($id));
```

Return format:

```php
array(
    array(
        'id' => 4,
        'name' => 'Joe'
    ),
)
```

If you want the power of a cuatom query, but also the convenience of getting the result as a single array instead of having to use `$result[0]` all the time (which may even trigger an error if no results are found and the value becomes `null`) you can use `rawQuerySingle` which returns only a single result, similar to how `getOne` works.

```php
$Database->rawQuerySingle("SELECT * FROM 'users' WHERE id = 4");
// Works the same way for bound parameters
$id = 4;
$Database->rawQuerySingle("SELECT * FROM 'users' WHERE id = ?", array($id));
```

Return format:

```php
array(
    'id' => 4,
    'name' => 'Joe'
)
```

### Debugging

#### Check if a table exists [`tableExists()::boolean`]

```php
$Database->tableExists('users'); // true
```

#### Get number of rows [`count()::int`]

```php
$Database->count('users'); // 4
$Database->where('"id" = 2')->count('users'); // 1
```

#### Check if the query returns any rows [`has()::boolean`]

```php
$Database->has('users'); // true
$Database->where('"id" = 2')->has('users'); // true
```

**Note:** This will reset the object, so the following **will not** work as expeced:

```php
$withWhere = $Database->where('"id" = 2');
if ($withWhere->has('users'))
	$Database->getOne('users');
```

Instead of returning the user with the `id` equal to `2`, it'll return whatever row it finds first since the `where` call is no longer in effect.

#### Get last executed query [`getLastQuery()::string`]

This will return the last query executed through the class with the bound value placeholders replaced with actual values. Executing this query will most likely fail because quotation marks aren't added around strings when replacing.

```php
$Database->insert('users',array('name' => 'Sarah'))
echo $Database->getLastQuery(); // INSERT INTO "users" ("name") VALUES ('Sarah')
```

#### Get last error message [`getLastError()::string`]

This can be useful after a failed `insert`/`update` call, for example.

```php
// Duplicated ID
$Database->insert('users',array('id' => '1', 'name' => 'Fred'))
echo $Database->getLastError(); // #1062 - Duplicate entry '1' for key 'PRIMARY'
```
