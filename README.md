PHP: Mysql Dumper
=========


Mysql Dumper is the only library that supports:
* Output binary blobs as hex.
* Resolves view dependencies (using Stand-In tables).
* Dumps stored procedures.
* Dumps events.
* Does extended-insert and/or complete-insert.
* Supports virtual columns from MySQL 5.7.

## Requirements

- PHP 7.0.0 or newer
- MySQL 5.4.0 or newer
- [PDO](http://php.net/pdo)

## Installing

Using [Composer](http://getcomposer.org):

```
$ composer require niko9911/mysqldump-php:1.*

```

Or via json file:

```
"require": {
        "niko9911/mysqldump-php":"1.*"
}
```

Using [Curl](http://curl.haxx.se) to always download and decompress the latest release:

```
$ curl --silent --location https://api.github.com/repos/niko9911/mysqldump-php/releases | grep -i tarball_url | head -n 1 | cut -d '"' -f 4 | xargs curl --location --silent | tar xvz
```

## Getting started

With [Autoloader](http://www.php-fig.org/psr/psr-4/)/[Composer](http://getcomposer.org):

```php
<?php

use Niko9911\MysqlDump\Dump;

try {
    $dump = new Dump('mysql:host=localhost;dbname=testdb', 'username', 'password');
    $dump->start('storage/work/dump.sql');
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

Refer to the [wiki](https://github.com/ifsnop/mysqldump-php/wiki/full-example) for some examples and a comparision between mysqldump and mysqldump-php dumps.

## Constructor and default parameters
```php
/**
 * Constructor of Mysqldump. Note that in the case of an SQLite database
 * connection, the filename must be in the $db parameter.
 *
 * @param string $dsn        PDO DSN connection string
 * @param string $user       SQL account username
 * @param string $pass       SQL account password
 * @param array  $dumpSettings SQL database settings
 * @param array  $pdoSettings  PDO configured attributes
 */
public function __construct(
    $dsn = '',
    $user = '',
    $pass = '',
    $dumpSettings = array(),
    $pdoSettings = array()
)

$dumpSettingsDefault = array(
    'include-tables' => array(),
    'exclude-tables' => array(),
    'compress' => Mysqldump::NONE,
    'init_commands' => array(),
    'no-data' => array(),
    'reset-auto-increment' => false,
    'add-drop-database' => false,
    'add-drop-table' => false,
    'add-drop-trigger' => true,
    'add-locks' => true,
    'complete-insert' => false,
    'databases' => false,
    'default-character-set' => Mysqldump::UTF8,
    'disable-keys' => true,
    'extended-insert' => true,
    'events' => false,
    'hex-blob' => true, /* faster than escaped content */
    'net_buffer_length' => self::MAXLINESIZE,
    'no-autocommit' => true,
    'no-create-info' => false,
    'lock-tables' => true,
    'routines' => false,
    'single-transaction' => true,
    'skip-triggers' => false,
    'skip-tz-utc' => false,
    'skip-comments' => false,
    'skip-dump-date' => false,
    'skip-definer' => false,
    'where' => '',
    /* deprecated */
    'disable-foreign-keys-check' => true
);

$pdoSettingsDefaults = array(
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
);

// missing settings in constructor will be replaced by default options
$this->_pdoSettings = self::array_replace_recursive($pdoSettingsDefault, $pdoSettings);
$this->_dumpSettings = self::array_replace_recursive($dumpSettingsDefault, $dumpSettings);
```

## Dump Settings

- **include-tables**
  - Only include these tables (array of table names), include all if empty
- **exclude-tables**
  - Exclude these tables (array of table names), include all if empty, supports regexps
- **compress**
  - Gzip, Bzip2, None.
  - Could be specified using the declared consts: IMysqldump\Mysqldump::GZIP, IMysqldump\Mysqldump::BZIP2 or IMysqldump\Mysqldump::NONE
- **reset-auto-increment**
  - Removes the AUTO_INCREMENT option from the database definition
  - Useful when used with no-data, so when db is recreated, it will start from 1 instead of using an old value
- **add-drop-database**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_add-drop-database
- **add-drop-table**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_add-drop-table
- **add-drop-triggers**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_add-drop-trigger
- **add-locks**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_add-locks
- **complete-insert**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_complete-insert
- **databases**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_databases
- **default-character-set**
  - utf8 (default, compatible option), utf8mb4 (for full utf8 compliance)
  - Could be specified using the declared consts: IMysqldump\Mysqldump::UTF8 or IMysqldump\Mysqldump::UTF8MB4BZIP2
  - http://dev.mysql.com/doc/refman/5.5/en/charset-unicode-utf8mb4.html
  - https://mathiasbynens.be/notes/mysql-utf8mb4
- **disable-keys**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_disable-keys
- **events**
  - https://dev.mysql.com/doc/refman/5.7/en/mysqldump.html#option_mysqldump_events
- **extended-insert**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_extended-insert
- **hex-blob**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_hex-blob
- **lock-tables**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_lock-tables
- **net_buffer_length**
  - http://dev.mysql.com/doc/refman/5.7/en/mysqldump.html#option_mysqldump_net_buffer_length
- **no-autocommit**
  - Option to disable autocommit (faster inserts, no problems with index keys)
  - http://dev.mysql.com/doc/refman/4.1/en/commit.html
- **no-create-info**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_no-create-info
- **no-data**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_no-data
  - Do not dump data for these tables (array of table names), support regexps, `true` to ignore all tables
- **routines**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_routines
- **single-transaction**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_single-transaction
- **skip-comments**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_comments
- **skip-dump-date**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_dump-date
- **skip-triggers**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_triggers
- **skip-tz-utc**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_tz-utc
- **skip-definer**
  - https://dev.mysql.com/doc/refman/5.7/en/mysqlpump.html#option_mysqlpump_skip-definer
- **where**
  - http://dev.mysql.com/doc/refman/5.1/en/mysqldump.html#option_mysqldump_where

The following options are now enabled by default, and there is no way to disable them since
they should always be used.

- **disable-foreign-keys-check**
  - http://dev.mysql.com/doc/refman/5.5/en/optimizing-innodb-bulk-data-loading.html

## PDO Settings

- **PDO::ATTR_PERSISTENT**
- **PDO::ATTR_ERRMODE**
- **PDO::MYSQL_ATTR_INIT_COMMAND**
- **PDO::MYSQL_ATTR_USE_BUFFERED_QUERY**
  - http://www.php.net/manual/en/ref.pdo-mysql.php
  - http://stackoverflow.com/questions/13728106/unexpectedly-hitting-php-memory-limit-with-a-single-pdo-query/13729745#13729745
  - http://www.php.net/manual/en/mysqlinfo.concepts.buffering.php

## Errors

To dump a database, you need the following privileges :

- **SELECT**
  - In order to dump table structures and data.
- **SHOW VIEW**
  - If any databases has views, else you will get an error.
- **TRIGGER**
  - If any table has one or more triggers.
- **LOCK TABLES**
  - If "lock tables" option was enabled.

Use **SHOW GRANTS FOR user@host;** to know what privileges user has. See the following link for more information:

[Which are the minimum privileges required to get a backup of a MySQL database schema?](http://dba.stackexchange.com/questions/55546/which-are-the-minimum-privileges-required-to-get-a-backup-of-a-mysql-database-sc/55572#55572)

## TODO

GitLab CI & Automatic Deployment to Composer

## Contributing

Format all code to PHP-FIG standards.
http://www.php-fig.org/

## License

This project is open-sourced software licensed under the [GPL license](http://www.gnu.org/copyleft/gpl.html)
For commercial licenses, please contact niko-at-ironlions-dot-fi.

## Credits

After more than 8 years, there is barely anything left from the original source code, but:

Originally based on James Elliott's script from 2009.
http://code.google.com/p/db-mysqldump/

Adapted and extended by Michael J. Calkins.
https://github.com/clouddueling

Rewrite based on from Diego Torres.
https://github.com/ifsnop

Currently maintained, developed and improved by Niko Granö. 
https://github.com/niko9911