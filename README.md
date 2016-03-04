<h1>PostgresDb <a href="https://travis-ci.org/SeinopSys/PHP-PostgreSQL-Database-Class"><img alt="Build Status" src="https://travis-ci.org/SeinopSys/PHP-PostgreSQL-Database-Class.svg"></a></h1>

This project is a PostgreSQL version of @joshcam's [MysqliDb Class](https://github.com/joshcam/PHP-MySQLi-Database-Class), that has support for the basic functionality and syntax provided by said class, tailored specifically to PostgreSQL.

## Usage

### Set up

```php
$Database = new PostgresDb($database_name, $host, $username, $password);
```

### Selecting

```php
// SELECT * FROM $table
$Database->get($table);

// SELECT * FROM $table LIMIT 1
$Database->get($table, 1);

// SELECT id, name FROM $table LIMIT 1
$Database->get($table, 1, 'id, name');

// SELECT id, name FROM $table LIMIT 2 OFFSET 10
$Database->get($table, array(10,2), 'id, name');
// Returns
array(
    array(
        'id' => 11,
        'name' => 'Alex'
    ),
    array(
        'id' => 12,
        'name' => 'Daniel'
    )
)
```

Alternatively, use `getOne` to only select a single row:

```php
// SELECT * FROM $table LIMIT 1
$Database->getOne($table);

// SELECT id, name FROM $table LIMIT 1
$Database->getOne($table, 'id, name');
// Returns
array(
    'id' => 1,
    'name' => 'David'
)
```

### Where

```php
// SELECT * FROM $table WHERE "id" = 1 LIMIT 1
$Database->where('id', 1)->getOne($table);

// SELECT * FROM $table LIMIT 1 WHERE "id" != 1
$Database->where('"id" != 1')->get($table);

// SELECT * FROM $table LIMIT 1 WHERE "id" != 1
$Database->where('id', array('!=' => 1))->get($table);
```

### Join

```php
// SELECT * FROM users u LEFT JOIN "messages" m ON m.user = u.id
$Database->join('messages m', 'm.user = u.id','LEFT')->get('users u');
```

### Ordering

```php
// SELECT * FROM $table ORDER BY "name" DESC
$Database->orderBy('name')->get($table);

// SELECT * FROM $table ORDER BY "name" ASC
$Database->orderBy('name','ASC')->get($table);

// SELECT * FROM $table ORDER BY CASE WHEN "name" IS NULL THEN 1 ELSE 0 END DESC, "name" ASC
$Database
    ->orderByLiteral('CASE WHEN "name" IS NULL THEN 1 ELSE 0 END')
    ->orderBy('name','ASC')
    ->get($table);
```

### Inserting

```php
// INSERT INTO $table ("name") VALUES ('Joe')
$Database->insert($table,array('name' => 'Joe'));
```
Ask for values of inserted columns:

```php
// INSERT INTO $table ("name") VALUES ('Joe') RETURNING "id"
$id = $Database->insert($table,array('name' => 'Joe'),'id');
echo $id;
```

>15

### Update

```php
// UPDATE $table SET "name" = 'Dave' WHERE "id" = 1
$Database->where('id', 1)->update($table,array('name' => 'Dave'));
```

### Debugging

 - Check if a table exists: (`tableExists()::bool`)

	```php
	$Database->tableExists('table_name');
	```

 - Get # of rows: (`count()::int`)

	```php
	$Database->count('table_name');
	$Database->where('"id" = 2')->count('table_name');
	```

 - Check if query returns any rows: (`has()::bool`)

	```php
	$Database->has('table_name');
	$Database->where('"id" = 2')->has('table_name');
	```

 - Last executed query: (`getLastQuery()::string`)

	```php
	$Database->insert('table_name',array('name' => 'Sarah'))
	echo $Database->getLastQuery();
	```

    > INSERT INTO "table_name" ("name") VALUES ('Sarah')

 - Last error:

	```php
	// Duplicated ID
	$Database->insert('table_name',array('id' => '1', 'name' => 'Fred'))
	echo $Database->getLastError();
	```
