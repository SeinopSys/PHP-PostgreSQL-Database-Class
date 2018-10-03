# PostgresDb [![Build Status](https://travis-ci.org/SeinopSys/PHP-PostgreSQL-Database-Class.svg)](https://travis-ci.org/SeinopSys/PHP-PostgreSQL-Database-Class) [![Latest Stable Version](https://poser.pugx.org/seinopsys/postgresql-database-class/v/stable)](https://packagist.org/packages/seinopsys/postgresql-database-class) [![Total Downloads](https://poser.pugx.org/seinopsys/postgresql-database-class/downloads)](https://packagist.org/packages/seinopsys/postgresql-database-class) [![License](https://poser.pugx.org/seinopsys/postgresql-database-class/license)](https://packagist.org/packages/seinopsys/postgresql-database-class)

This project is a PostgreSQL version of [ThingEngineer](https://github.com/ThingEngineer)'s [MysqliDb Class](https://github.com/ThingEngineer/PHP-MySQLi-Database-Class), that supports the basic functionality and syntax provided by said class, tailored specifically to PostgreSQL.

## Installation

This class requires PHP 5.4+ or 7+ to work. You can either place the `src/PostgresDb.php` in your project and require/include it, or use [Composer](https://getcomposer.org) (strongly recommended)

    composer require seinopsys/postgresql-database-class:^3.0

## Usage

```php
$db = new \SeinopSys\PostgresDb($database_name, $host, $username, $password);
```

For a more in-depth guide see [USAGE.md](USAGE.md)

## Upgrading from `2.x`

 1. **Removed deprecated methods**
  
    These methods were deprecated in version `2.x` and have been removed in `3.x`. Use the renamed variants as indicated below:
     
     | `2.x` | `3.x` |
     |-------|-------|
     |`$db->rawQuery(…);`|`$db->query(…);`|
     |`$db->rawQuerySingle(…);`|`$db->querySingle(…);`|
     |`$db->pdo();`|`$db->getConnection();`|
    
 2. **Namespace change**
  
    As of `3.x` - to comply fully with the PSR-2 coding standard - the class now resides in the `SeinopSys` namespace. Here's a handy table to show what you need to change and how:
     
     | `2.x` | `3.x` |
     |-------|-------|
     |`$db = new PostgresDb(…);`|`$db = new \SeinopSys\PostgresDb(…);`|
     |`$db = new \PostgresDb(…);`|`$db = new \SeinopSys\PostgresDb(…);`|
     |<pre>use \PostgresDb;<br><br>$db = new PostgresDb(…);</pre>|<pre>use \SeinopSys\PostgresDb;<br><br>$db = new PostgresDb(…);</pre>|
