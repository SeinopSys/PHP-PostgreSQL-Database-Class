## Usage

For the examples below, the following `users` table structure is used:

| id |  name  | gender |
|----|--------|--------|
| 1  | Sam    | m      |
| 2  | Alex   | f      |
| 3  | Daniel | m      |


### Connecting to a database

The class works with Composer's autoloading, so if you install through it you just need to require the `vendor/autoload.php` file somewhere in your project. It's defined in the `SesinopSys` namespace, so if you're using it from within a namespace, refer to it as `\SeinopSys\PostgresDb` or add `use \SeinopSys\PostgresDb;` at the top of the file so you can drop the namespace afterwards.

If you decided to go the simple include route then you can include the `src/PostgresDb.php` file and use it pretty much the same way.

To start using the class you need to create an instance with the following parameters.

```php
use \SeinopSys\PostgresDb;

$db = new PostgresDb($database_name, $host, $username, $password, $port);
```

Due to the way these parameters are mapped to the internal connection call within PHP these values must not contain spaces. If you do not specify the `$port` parameter the PostgreSQL default (5432) will be used.

Initializing the class does not immediately create a connection. To force a connection attempt, call:

```php
$db->getConnection();
```

This will return the internal PDO object (and create it if it doesn't exist yet) used by the class and simultaneously attempts to connect to the database. Initial connection errors can be caught by using `try…catch` but by default the script uses `PDO::ERRMODE_WARNING` for further errors.

If you'd prefer exceptions to be thrown instead, or if you like to live dangerously and want to silence all errors, you can use the chainable `setPDOErrmode()` method by passing any of the `PDO::ERRMODE_*` constants both before and after the connection has been made.

```php
// Before connection
$db->setPDOErrmode(PDO::ERRMODE_EXCEPTION)->getConnection();

// After connection
$db->getConnection();
$db->setPDOErrmode(PDO::ERRMODE_SILENT)->get(…)->setPDOErrmode(PDO::ERRMODE_WARNING);
```

If you need the value later for whatever reason you can read it out using `getPDOErrmode()`.

If you happen to have an existing PDO connection lying around you can also use it with this class, like so:

```php
require "PostgresDb.php";
$db = new PostgresDb();
$db->setConnection($existing_connection);
```

Any future method calls on the `$db` object will act on the existing connection instead of creating a new instance. Only use a different connection with this class if you know what you're doing. Manipulating the same PDO object with different libraries can result in strange behavior, so be careful.

### Selecting [`get()`, `getOne()`]

Returns an array containing each result as an array of its own. Returns `false` if no row is found.

```php
// SELECT * FROM users
$db->get('users');

// SELECT * FROM users LIMIT 1
$db->get('users', 1);

// SELECT id, name FROM users LIMIT 1
$db->get('users', 1, 'id, name');

// SELECT id, name FROM users LIMIT 2 OFFSET 1
$db->get('users', [1,2], 'id, name');
```

Return format:

```php
[
    [
        'id' => 2,
        'name' => 'Alex'
    ],
    [
        'id' => 3,
        'name' => 'Daniel'
    ]
]
```

Alternatively, you can use `getOne` to only select a single row. Returns `false` if no row is found.

```php
// SELECT * FROM users LIMIT 1
$db->getOne('users');

// SELECT id, name FROM users LIMIT 1
$db->getOne('users', 'id, name');
```

Return format:

```php
[
    'id' => 1,
    'name' => 'Sam'
]
```

### Where [`where()`]

```php
// SELECT * FROM users WHERE "id" = 1 LIMIT 1
$db->where('id', 1)->get('users');

// SELECT * FROM users LIMIT 1 WHERE "id" != 1
$db->where('"id" != 1')->getOne('users');

// SELECT * FROM users LIMIT 1 WHERE "id" != 1
$db->where('id', 1, '!=')->getOne('users');

// SELECT * FROM users LIMIT 1 WHERE "id" IN (1, 3)
$db->where('id', [1, 3])->getOne('users');

// SELECT * FROM users LIMIT 1 WHERE "id" NOT IN (1, 3)
$db->where('id', [1, 3], '!=')->getOne('users');
```

### Join [`join()`]

```php
// SELECT * FROM users u LEFT JOIN "messages" m ON m.user = u.id
$db->join('messages m', 'm.user = u.id', 'LEFT')->get('users u');

// SELECT * FROM users u INNER JOIN "messages" m ON u.id = m.user
$db->join('messages m', 'u.id = m.user', 'INNER')->get('users u');
```

### Ordering [`orderBy()`, `orderByLiteral()`]

Complex `ORDER BY` statements can be passed to `orderByLiteral` which will just use the value you provide as it is. you can use the built-in constant to order the results randomly.

```php
// SELECT * FROM users ORDER BY "name" DESC
$db->orderBy('name')->get('users');

// SELECT * FROM users ORDER BY "name" ASC
$db->orderBy('name','ASC')->get('users');

// SELECT * FROM users ORDER BY rand()
$db->orderBy(PostgresDb::ORDERBY_RAND)->get('users');

// SELECT * FROM users ORDER BY CASE WHEN "name" IS NULL THEN 1 ELSE 0 END DESC, "name" ASC
$db
    ->orderByLiteral('CASE WHEN "name" IS NULL THEN 1 ELSE 0 END')
    ->orderBy('name','ASC')
    ->get('users');
```

### Grouping [`groupBy()`]

```php
// SELECT * FROM "users" GROUP BY "gender"
$db->groupBy('gender')->get('users');
// SELECT * FROM "users" GROUP BY "gender", "othercol"
$db->groupBy('gender')->groupBy('othercol')->get('users');
```

### Inserting [`insert()`]

Returns `false` on failure and `true` if successful.

```php
// INSERT INTO users ("name", gender) VALUES ('Joe', 'm')
$db->insert('users', ['name' => 'Joe', 'gender' => 'm']);
```

|  id   |  name   | gender |
|-------|---------|--------|
| 1     | Sam     | m      |
| 2     | Alex    | f      |
| 3     | Daniel  | m      |
| **4** | **Joe** | **m**  |

You can also ask for the values of inserted columns, in which case it'll return those instead of `true` if the insert is successful. This is especially useful when you need the value of an index that's generated on the fly by Postgres.

When returning a single column its value is returned by the method, and when multiple columns are specified the return value is an associative array with the keys being the columns and the values being, well, the values. 

```php
// INSERT INTO users ("name") VALUES ('Joe') RETURNING id
$id = $db->insert('users', ['name' => 'Joe'], 'id');
// $id === 4
```

```php
// INSERT INTO users ("name") VALUES ('Joe') RETURNING id, "name"
$return = $db->insert('users', ['name' => 'Joe'], ['id', 'name']);
//     or $db->insert('users', ['name' => 'Joe'], 'id, name');
// $return === ['id' => 4, 'name' => 'Joe']
```

### Update [`update()`]

Returns `false` on failure and `true` if successful.

```php
// UPDATE users SET "name" = 'Dave' WHERE "id" = 1
$db->where('id', 1)->update('users', ['name' => 'Dave']);
```

| id |   name   | gender |
|----|----------|--------|
| 1  | **Dave** | m      |
| 2  | Alex     | f      |
| 3  | Daniel   | m      |
| 4  | Joe      | m      |

### Delete [`delete()`]

Returns `false` on failure and `true` if successful. Cannot be used with `groupBy()`, `join()` and `orderBy()`.

```php
// DELETE FROM users WHERE "id" = 3
$success = $db->where('id', 3)->delete('users');
// $success === true
```

A second argument can optionally be passed to return columns from the deleted row, similar to `insert()`.

```php
// DELETE FROM users WHERE "id" = 3 RETURNING gender
$return = $db->where('id', 3)->delete('users', 'gender');
// $return === 'm'
```

```php
// DELETE FROM users WHERE "id" = 3 RETURNING "name", gender
$return = $db->where('id', 3)->delete('users', ['name', 'gender']);
//     or $db->where('id', 3)->delete('users', 'name, gender');
// $return === ['name' => 'Daniel', 'gender' => 'm']
```

| id |  name  | gender |
|----|--------|--------|
| 1  | Dave   | m      |
| 2  | Alex   | f      |
| 4  | Joe    | m      |

### Raw SQL queries [`query()`, `querySingle()`]

Executes the query exactly* as passed, for when it is far too complex to use the built-in methods, or when a built-in method does not exist for what you want to achieve. Return value format matches `get()`'s.

```php
// Query string only
$db->query('SELECT * FROM users WHERE id = 4');

// Using prepared statement parameters
$id = 4;
$db->query('SELECT * FROM users WHERE id = ? && name = ?', [4, 'Joe']);
$db->query('SELECT * FROM users WHERE id = :id && name = :who', [':id' => $id, ':who' => 'Joe']);
```

Return format:

```php
[
    [
        'id' => 4,
        'name' => 'Joe'
    ],
]
```

<sup>* The class currently replaces `&&` with `AND` if it's surrounded by whitespace on both sides. PostgreSQL would report a syntax error when using the former instead of the latter without this pre-processing step. See the `_alterQuery()` method for the exact implementation.</sup>

If you want the power of a custom query, but also the convenience of getting the first result as a single array you can use `querySingle()` which wortks similarly to `getOne()`.

```php
$db->querySingle("SELECT * FROM 'users' WHERE id = 4");
// Works the same way for bound parameters
$id = 4;
$db->querySingle("SELECT * FROM 'users' WHERE id = ?", [$id]);
```

Return format:

```php
[
    'id' => 4,
    'name' => 'Joe'
]
```

### Informational methods

**Note:** These will reset the object, so the following will **NOT** work as expected:

```php
$withWhere = $db->where('"id" = 2');
if ($withWhere->has('users')) {
    $db->getOne('users');
}
```

Instead of returning the user with the `id` equal to `2`, it'll return whatever row it finds first since the `where()` call is no longer in effect.

#### Check if a table exists [`tableExists():boolean`]

```php
$db->tableExists('users'); // true
```

#### Get number of matching rows [`count():int`]

```php
$db->count('users'); // 3
$db->where('"id" = 2')->count('users'); // 1
```

#### Check if the query returns any rows [`has():boolean`]

Basically `count() >= 1`, but reads nicer.

```php
$db->has('users'); // true
$db->where('id', 2)->has('users'); // true
$db->where('id', 0)->has('users'); // false
```

### Debugging

#### Get last executed query [`getLastQuery():string`]

This will return the last query executed through the class where the placeholders have been replaced with actual values. While the class does its best to keep the SQL valid you should not rely on the returned value being a valid query. 

```php
$db->insert('users', ['name' => 'Sarah'])
echo $db->getLastQuery(); // INSERT INTO "users" ("name") VALUES ('Sarah')
```

#### Get last error message [`getLastError():string`]

This can be useful after a failed `insert()`/`update()` call, for example.

```php
// Duplicated ID
$db->insert('users', ['id' => '1', 'name' => 'Fred'])
echo $db->getLastError(); // #1062 - Duplicate entry '1' for key 'PRIMARY'
```

#### Number of rows affected [`count:int`]

Returns the number of rows affected by the last SQL statement

```php
$db->get('users');
echo $db->count; // 3
```

### Extending functionality

### Number of executed queries

With a simple wrapper class it's trivial to include a query counter if need be.

```php
class PostgresDbWrapper extends \SeinopSys\PostgresDb
{
    public $query_count = 0;

    /**
     * @param PDOStatement $stmt Statement to execute
     *
     * @return bool|array|object[]
     */
    protected function execStatement($stmt)
    {
        $this->query_count++;
        return parent::execStatement($stmt);
    }
}
```

### Object return values

The class contains two utility methods (`tableNameToClassName` and `setClass`) which allow for the creation of a wrapper that can force returned values into a class instead of an array. This allows for using both the array and class method simultaneously with minimal effort. An example of such a wrapper class is shown below.

This assumes an autoloader is configured within the project which allows classes to be loaded on the fly as needed. This method will cause the autoloader to attempt loading the class within the `Models` namespace when `class_exists` is called. If it fails, a key is set on a private array. This prevents future checks for the existence of the same class to avoid significantly impacting the application's performance.

```php
class PostgresDbWrapper extends \SeinopSys\PostgresDb
{
    private $nonexistantClassCache = [];

    /**
     * @param PDOStatement $stmt Statement to execute
     *
     * @return bool|array|object[]
     */
    protected function execStatement($stmt)
    {
        $className = $this->tableNameToClassName();
        if (isset($className) && empty($this->nonexistantClassCache[$className])) {
            try {
                if (!class_exists("\\Models\\$className")) {
                    throw new Exception();
                }

                $this->setClass("\\Models\\$className");
            }
            catch (Exception $e) {
                $this->nonexistantClassCache[$className] = true;
            }
        }

        return parent::execStatement($stmt);
    }
}
```
