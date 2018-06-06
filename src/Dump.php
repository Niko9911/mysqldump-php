<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE
 * This source file is released under GPL V3 License
 *
 * @copyright Copyright (c) Niko Granö & Contributors
 * @author Niko Granö <niko@ironlions.fi>
 */

namespace Niko9911\MysqlDump;

use Niko9911\MysqlDump\Compress\ManagerFactory;
use Niko9911\MysqlDump\TypeAdapter\Factory;
use Niko9911\MysqlDump\TypeAdapter\Type;
use Exception;
use PDO;
use PDOException;
use RuntimeException;

final class Dump
{
    public const MAX_LINE_SIZE = 1000000;

    // Available compression methods as constants
    public const GZIP = 'Gzip';
    public const BZIP2 = 'Bzip2';
    public const NONE = 'None';

    // Available connection strings
    public const UTF8 = 'utf8';
    public const UTF8MB4 = 'utf8mb4';

    /**
     * Database username.
     *
     * @var string
     */
    public $user;

    /**
     * Database password.
     *
     * @var string
     */
    public $pass;

    /**
     * Connection string for PDO.
     *
     * @var string
     */
    public $dsn;

    /**
     * Destination filename, defaults to stdout.
     *
     * @var string
     */
    public $fileName = 'php://output';

    /** @var Type */
    private $typeAdapter;

    /** @var array */
    private $tables = [];

    /** @var array */
    private $views = [];

    /** @var array */
    private $triggers = [];

    /** @var array */
    private $procedures = [];

    /** @var array */
    private $events = [];

    /** @var array */
    private $tableColumnTypes = [];

    /** @var \PDO */
    private $dbHandler;

    /** @var string */
    private $dbType = '';

    private $compressManager;
    private $dumpSettings;
    private $pdoSettings;
    private $version;

    /**
     * Description: Database name, parsed from DSN.
     *
     * @var string
     */
    private $dbName;

    /**
     * Description: Hostname, parsed from DNS.
     *
     * @var string
     */
    private $host;

    /**
     * Description: DSN string parsed as an array.
     *
     * @var array
     */
    private $dsnArray = [];

    /**
     * Dump constructor.
     *
     * Note that in the case of an SQLite database
     * connection, the filename must be in the $db parameter.
     *
     * @param string $dsn
     * @param string $user
     * @param string $pass
     * @param array  $dumpSettings
     * @param array  $pdoSettings
     */
    public function __construct(
        $dsn = '',
        $user = '',
        $pass = '',
        array $dumpSettings = [],
        array $pdoSettings = []
    ) {
        $dumpSettingsDefault = [
            'include-tables' => [],
            'exclude-tables' => [],
            'compress' => self::NONE,
            'init_commands' => [],
            'no-data' => [],
            'reset-auto-increment' => false,
            'add-drop-database' => false,
            'add-drop-table' => false,
            'add-drop-trigger' => true,
            'add-locks' => true,
            'complete-insert' => false,
            'databases' => false,
            'default-character-set' => self::UTF8,
            'disable-keys' => true,
            'extended-insert' => true,
            'events' => false,
            'hex-blob' => true, /* faster than escaped content */
            'net_buffer_length' => self::MAX_LINE_SIZE,
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
            'disable-foreign-keys-check' => true,
        ];

        $pdoSettingsDefault = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];

        $this->user = $user;
        $this->pass = $pass;
        $this->parseDsn($dsn);

        // this drops MYSQL dependency, only use the constant if it's defined
        if ('mysql' === $this->dbType) {
            $pdoSettingsDefault[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
        }

        $this->pdoSettings = self::arrayReplaceRecursive($pdoSettingsDefault, $pdoSettings);
        $this->dumpSettings = self::arrayReplaceRecursive($dumpSettingsDefault, $dumpSettings);
        $this->dumpSettings['init_commands'][] = 'SET NAMES '.$this->dumpSettings['default-character-set'];

        if (false === $this->dumpSettings['skip-tz-utc']) {
            $this->dumpSettings['init_commands'][] = "SET TIME_ZONE='+00:00'";
        }

        $diff = \array_diff(\array_keys($this->dumpSettings), \array_keys($dumpSettingsDefault));
        if (\count($diff) > 0) {
            throw new RuntimeException('Unexpected value in dumpSettings: ('.\implode(',', $diff).')');
        }

        if (!\is_array($this->dumpSettings['include-tables']) ||
            !\is_array($this->dumpSettings['exclude-tables'])) {
            throw new RuntimeException('Include-tables and exclude-tables should be arrays');
        }

        // Dump the same views as tables, mimic MysqlDump behaviour
        $this->dumpSettings['include-views'] = $this->dumpSettings['include-tables'];

        // Create a new compressManager to manage compressed output
        $this->compressManager = ManagerFactory::create($this->dumpSettings['compress']);
    }

    /**
     * Destructor of MysqlDump. Unset dbHandlers and database objects.
     */
    public function __destruct()
    {
        $this->dbHandler = null;
    }

    /**
     * Custom array_replace_recursive to be used if PHP < 5.3
     * Replaces elements from passed arrays into the first array recursively.
     *
     * @param array $array1 The array in which elements are replaced
     * @param array $array2 The array from which elements will be extracted
     *
     * @return array returns an array, or NULL if an error occurs
     */
    public static function arrayReplaceRecursive($array1, $array2): array
    {
        if (\function_exists('array_replace_recursive')) {
            return \array_replace_recursive($array1, $array2);
        }

        foreach ($array2 as $key => $value) {
            if (\is_array($value)) {
                $array1[$key] = self::arrayReplaceRecursive($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }

    /**
     * Parse DSN string and extract dbname value
     * Several examples of a DSN string
     *   mysql:host=localhost;dbname=testdb
     *   mysql:host=localhost;port=3307;dbname=testdb
     *   mysql:unix_socket=/tmp/mysql.sock;dbname=testdb.
     *
     * @param string $dsn dsn string to parse
     *
     * @throws RuntimeException
     *
     * @return bool
     */
    private function parseDsn($dsn): bool
    {
        if (empty($dsn) || (false === ($pos = \mb_strpos($dsn, ':')))) {
            throw new RuntimeException('Empty DSN string');
        }

        $this->dsn = $dsn;
        $this->dbType = \mb_strtolower(\mb_substr($dsn, 0, $pos));

        if (empty($this->dbType)) {
            throw new RuntimeException('Missing database type from DSN string');
        }

        $dsn = \mb_substr($dsn, $pos + 1);

        foreach (\explode(';', $dsn) as $kvp) {
            $kvpArr = \explode('=', $kvp);
            $this->dsnArray[\mb_strtolower($kvpArr[0])] = $kvpArr[1];
        }

        if (empty($this->dsnArray['host']) &&
            empty($this->dsnArray['unix_socket'])) {
            throw new RuntimeException('Missing host from DSN string');
        }
        $this->host = !empty($this->dsnArray['host']) ?
            $this->dsnArray['host'] : $this->dsnArray['unix_socket'];

        if (empty($this->dsnArray['dbname'])) {
            throw new RuntimeException('Missing database name from DSN string');
        }

        $this->dbName = $this->dsnArray['dbname'];

        // safety check
        if (!\is_string($this->dbType)) {
            throw new RuntimeException('Invalid database type definition in DSN string');
        }

        return true;
    }

    /**
     * Connect with PDO.
     */
    private function connect(): void
    {
        // Connecting with PDO
        try {
            switch ($this->dbType) {
                case 'sqlite':
                    $this->dbHandler = @new PDO('sqlite:'.$this->dbName, null, null, $this->pdoSettings);
                    break;
                case 'mysql':
                case 'pgsql':
                case 'dblib':
                    $this->dbHandler = @new PDO(
                        $this->dsn,
                        $this->user,
                        $this->pass,
                        $this->pdoSettings
                    );
                    // Execute init commands once connected
                    foreach ($this->dumpSettings['init_commands'] as $stmt) {
                        $this->dbHandler->exec($stmt);
                    }
                    // Store server version
                    $this->version = $this->dbHandler->getAttribute(PDO::ATTR_SERVER_VERSION);
                    break;
                default:
                    throw new RuntimeException('Unsupported database type ('.$this->dbType.')');
            }
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Connection to '.$this->dbType.' failed with message: '.
                $e->getMessage()
            );
        }

        if (null === $this->dbHandler) {
            throw new RuntimeException('Connection to '.$this->dbType.'failed');
        }

        $this->dbHandler->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
        $this->typeAdapter = Factory::create($this->dbType, $this->dbHandler, $this->dumpSettings);
    }

    /**
     * Main call.
     *
     * @param string $filename name of file to write sql dump to
     *
     * @throws Exception
     */
    public function start($filename = ''): void
    {
        // Output file can be redefined here
        if (!empty($filename)) {
            $this->fileName = $filename;
        }

        // Connect to database
        $this->connect();

        // Create output file
        $this->compressManager->open($this->fileName);

        // Write some basic info to output file
        $this->compressManager->write($this->getDumpFileHeader());

        // Store server settings and use scanner defaults to dump
        $this->compressManager->write(
            $this->typeAdapter->backupParameters()
        );

        if ($this->dumpSettings['databases']) {
            $this->compressManager->write(
                $this->typeAdapter->getDatabaseHeader($this->dbName)
            );
            if ($this->dumpSettings['add-drop-database']) {
                $this->compressManager->write(
                    $this->typeAdapter->addDropDatabase($this->dbName)
                );
            }
        }

        // Get table, view and trigger structures from database
        $this->getDatabaseStructure();

        if ($this->dumpSettings['databases']) {
            $this->compressManager->write(
                $this->typeAdapter->databases($this->dbName)
            );
        }

        // If there still are some tables/views in include-tables array,
        // that means that some tables or views weren't found.
        // Give proper error and exit.
        // This check will be removed once include-tables supports regexps
        if (0 < \count($this->dumpSettings['include-tables'])) {
            $name = \implode(',', $this->dumpSettings['include-tables']);
            throw new RuntimeException('Table ('.$name.') not found in database');
        }

        $this->exportTables();
        $this->exportTriggers();
        $this->exportViews();
        $this->exportProcedures();
        $this->exportEvents();

        // Restore saved parameters
        $this->compressManager->write(
            $this->typeAdapter->restoreParameters()
        );
        // Write some stats to output file
        $this->compressManager->write($this->getDumpFileFooter());
        // Close output file
        $this->compressManager->close();
    }

    /**
     * Returns header for dump file.
     *
     * @return string
     */
    private function getDumpFileHeader(): string
    {
        $header = '';
        if (!$this->dumpSettings['skip-comments']) {
            // Some info about software, source and time
            $header =
                '-- Niko Granö <niko@ironlions.fi>'.PHP_EOL.
                '-- Created with PHP MysqlDump'.PHP_EOL.
                '-- Provided under GPL V3 License'.PHP_EOL.
                '-- https://ironlions.fi'.PHP_EOL.
                '-- https://packagist.org/packages/niko9911/mysqldump'.PHP_EOL.
                '-- ------------------------------------------------------'.PHP_EOL.
                "-- Host: {$this->host}\tDatabase: {$this->dbName}".PHP_EOL;

            if (!empty($this->version)) {
                $header .= "-- Server version \t".$this->version.PHP_EOL;
            }

            if (!$this->dumpSettings['skip-dump-date']) {
                $header .= '-- Date: '.\date('r').PHP_EOL;
            }
            $header .= '-- ------------------------------------------------------'.PHP_EOL.PHP_EOL;
        }

        return $header;
    }

    /**
     * Returns footer for dump file.
     *
     * @return string
     */
    private function getDumpFileFooter(): string
    {
        $footer = '';
        if (!$this->dumpSettings['skip-comments']) {
            $footer .= '-- Dump completed';
            if (!$this->dumpSettings['skip-dump-date']) {
                $footer .= ' on: '.\date('r');
            }
            $footer .= PHP_EOL;
        }

        return $footer;
    }

    /**
     * Reads table and views names from database.
     * Fills $this->tables array so they will be dumped later.
     */
    private function getDatabaseStructure(): void
    {
        // Listing all tables from database
        if (empty($this->dumpSettings['include-tables'])) {
            // include all tables for now, blacklisting happens later
            foreach ($this->dbHandler->query($this->typeAdapter->showTables($this->dbName)) as $row) {
                $this->tables[] = \current($row);
            }
        } else {
            // include only the tables mentioned in include-tables
            foreach ($this->dbHandler->query($this->typeAdapter->showTables($this->dbName)) as $row) {
                if (\in_array(\current($row), $this->dumpSettings['include-tables'], true)) {
                    $this->tables[] = \current($row);
                    $elem = \array_search(\current($row), $this->dumpSettings['include-tables'], true);
                    unset($this->dumpSettings['include-tables'][$elem]);
                }
            }
        }

        // Listing all views from database
        if (empty($this->dumpSettings['include-views'])) {
            // include all views for now, blacklisting happens later
            foreach ($this->dbHandler->query($this->typeAdapter->showViews($this->dbName)) as $row) {
                $this->views[] = \current($row);
            }
        } else {
            // include only the tables mentioned in include-tables
            foreach ($this->dbHandler->query($this->typeAdapter->showViews($this->dbName)) as $row) {
                if (\in_array(\current($row), $this->dumpSettings['include-views'], true)) {
                    $this->views[] = \current($row);
                    $elem = \array_search(\current($row), $this->dumpSettings['include-views'], true);
                    unset($this->dumpSettings['include-views'][$elem]);
                }
            }
        }

        // Listing all triggers from database
        if (false === $this->dumpSettings['skip-triggers']) {
            foreach ($this->dbHandler->query($this->typeAdapter->showTriggers($this->dbName)) as $row) {
                $this->triggers[] = $row['Trigger'];
            }
        }

        // Listing all procedures from database
        if ($this->dumpSettings['routines']) {
            foreach ($this->dbHandler->query($this->typeAdapter->showProcedures($this->dbName)) as $row) {
                $this->procedures[] = $row['procedure_name'];
            }
        }

        // Listing all events from database
        if ($this->dumpSettings['events']) {
            foreach ($this->dbHandler->query($this->typeAdapter->showEvents($this->dbName)) as $row) {
                $this->events[] = $row['event_name'];
            }
        }
    }

    /**
     * Compare if $table name matches with a definition inside $arr.
     *
     * @param $table string
     * @param $arr array with strings or patterns
     *
     * @return bool
     */
    private function matches($table, $arr): bool
    {
        $match = false;

        foreach ($arr as $pattern) {
            if ('/' !== $pattern[0]) {
                continue;
            }
            if (1 === \preg_match($pattern, $table)) {
                $match = true;
            }
        }

        return \in_array($table, $arr, true) || $match;
    }

    /**
     * Exports all the tables selected from database.
     */
    private function exportTables(): void
    {
        // Exporting tables one by one
        foreach ($this->tables as $table) {
            if ($this->matches($table, $this->dumpSettings['exclude-tables'])) {
                continue;
            }
            $this->getTableStructure($table);
            if (false === $this->dumpSettings['no-data']) {
                // don't break compatibility with old trigger
                $this->listValues($table);
            } elseif (true === $this->dumpSettings['no-data']
                 || $this->matches($table, $this->dumpSettings['no-data'])) {
                return;
            } else {
                $this->listValues($table);
            }
        }
    }

    /**
     * Exports all the views found in database.
     */
    private function exportViews(): void
    {
        if (false === $this->dumpSettings['no-create-info']) {
            // Exporting views one by one
            foreach ($this->views as $view) {
                if ($this->matches($view, $this->dumpSettings['exclude-tables'])) {
                    continue;
                }
                $this->tableColumnTypes[$view] = $this->getTableColumnTypes($view);
                $this->getViewStructureTable($view);
            }
            foreach ($this->views as $view) {
                if ($this->matches($view, $this->dumpSettings['exclude-tables'])) {
                    continue;
                }
                $this->getViewStructureView($view);
            }
        }
    }

    /**
     * Exports all the triggers found in database.
     */
    private function exportTriggers(): void
    {
        // Exporting triggers one by one
        foreach ($this->triggers as $trigger) {
            $this->getTriggerStructure($trigger);
        }
    }

    /**
     * Exports all the procedures found in database.
     */
    private function exportProcedures(): void
    {
        // Exporting triggers one by one
        foreach ($this->procedures as $procedure) {
            $this->getProcedureStructure($procedure);
        }
    }

    /**
     * Exports all the events found in database.
     */
    private function exportEvents(): void
    {
        // Exporting triggers one by one
        foreach ($this->events as $event) {
            $this->getEventStructure($event);
        }
    }

    /**
     * Table structure extractor.
     *
     * @todo move specific mysql code to typeAdapter
     *
     * @param string $tableName Name of table to export
     */
    private function getTableStructure($tableName): void
    {
        if (!$this->dumpSettings['no-create-info']) {
            $ret = '';
            if (!$this->dumpSettings['skip-comments']) {
                $ret = '--'.PHP_EOL.
                    "-- Table structure for table `$tableName`".PHP_EOL.
                    '--'.PHP_EOL.PHP_EOL;
            }
            $stmt = $this->typeAdapter->showCreateTable($tableName);
            foreach ($this->dbHandler->query($stmt) as $r) {
                $this->compressManager->write($ret);
                if ($this->dumpSettings['add-drop-table']) {
                    $this->compressManager->write(
                        $this->typeAdapter->dropTable($tableName)
                    );
                }
                $this->compressManager->write(
                    $this->typeAdapter->createTable($r)
                );
                break;
            }
        }
        $this->tableColumnTypes[$tableName] = $this->getTableColumnTypes($tableName);
    }

    /**
     * Store column types to create data dumps and for Stand-In tables.
     *
     * @param string $tableName  Name of table to export
     *
     * @return array type column types detailed
     */
    private function getTableColumnTypes($tableName): array
    {
        $columnTypes = [];
        $columns = $this->dbHandler->query(
            $this->typeAdapter->showColumns($tableName)
        );
        $columns->setFetchMode(PDO::FETCH_ASSOC);

        foreach ($columns as $key => $col) {
            $types = $this->typeAdapter->parseColumnType($col);
            $columnTypes[$col['Field']] = [
                'is_numeric' => $types['is_numeric'],
                'is_blob' => $types['is_blob'],
                'type' => $types['type'],
                'type_sql' => $col['Type'],
                'is_virtual' => $types['is_virtual'],
            ];
        }

        return $columnTypes;
    }

    /**
     * View structure extractor, create table (avoids cyclic references).
     *
     * @todo move mysql specific code to typeAdapter
     *
     * @param string $viewName Name of view to export
     */
    private function getViewStructureTable($viewName): void
    {
        if (!$this->dumpSettings['skip-comments']) {
            $ret = '--'.PHP_EOL.
                "-- Stand-In structure for view `${viewName}`".PHP_EOL.
                '--'.PHP_EOL.PHP_EOL;
            $this->compressManager->write($ret);
        }
        $stmt = $this->typeAdapter->showCreateView($viewName);

        // create views as tables, to resolve dependencies
        foreach ($this->dbHandler->query($stmt) as $r) {
            if ($this->dumpSettings['add-drop-table']) {
                $this->compressManager->write(
                    $this->typeAdapter->dropView($viewName)
                );
            }

            $this->compressManager->write(
                $this->createStandInTable($viewName)
            );
            break;
        }
    }

    /**
     * Write a create table statement for the table Stand-In, show create
     * table would return a create algorithm when used on a view.
     *
     * @param string $viewName  Name of view to export
     *
     * @return string create statement
     */
    public function createStandInTable($viewName): string
    {
        $ret = [];
        foreach ($this->tableColumnTypes[$viewName] as $k => $v) {
            $ret[] = "`${k}` ${v['type_sql']}";
        }
        $ret = \implode(PHP_EOL.',', $ret);

        /* @noinspection SqlDialectInspection, SqlNoDataSourceInspection */
        $ret = "CREATE TABLE IF NOT EXISTS `$viewName` (".
            PHP_EOL.$ret.PHP_EOL.');'.PHP_EOL;

        return $ret;
    }

    /**
     * View structure extractor, create view.
     *
     * @TODO: Move mysql specific code to typeAdapter.
     *
     * @param string $viewName Name of view to export
     */
    private function getViewStructureView($viewName): void
    {
        if (!$this->dumpSettings['skip-comments']) {
            $ret = '--'.PHP_EOL.
                "-- View structure for view `${viewName}`".PHP_EOL.
                '--'.PHP_EOL.PHP_EOL;
            $this->compressManager->write($ret);
        }
        $stmt = $this->typeAdapter->showCreateView($viewName);

        // create views, to resolve dependencies
        // replacing tables with views
        foreach ($this->dbHandler->query($stmt) as $r) {
            // because we must replace table with view, we should delete it
            /* @noinspection DisconnectedForeachInstructionInspection */
            $this->compressManager->write($this->typeAdapter->dropView($viewName));
            $this->compressManager->write(
                $this->typeAdapter->createView($r)
            );
            break;
        }
    }

    /**
     * Trigger structure extractor.
     *
     * @param string $triggerName Name of trigger to export
     */
    private function getTriggerStructure($triggerName): void
    {
        $stmt = $this->typeAdapter->showCreateTrigger($triggerName);
        foreach ($this->dbHandler->query($stmt) as $r) {
            if ($this->dumpSettings['add-drop-trigger']) {
                $this->compressManager->write(
                    $this->typeAdapter->addDropTrigger($triggerName)
                );
            }
            $this->compressManager->write(
                $this->typeAdapter->createTrigger($r)
            );

            return;
        }
    }

    /**
     * Procedure structure extractor.
     *
     * @param string $procedureName Name of procedure to export
     */
    private function getProcedureStructure($procedureName): void
    {
        if (!$this->dumpSettings['skip-comments']) {
            $ret = '--'.PHP_EOL.
                "-- Dumping routines for database '".$this->dbName."'".PHP_EOL.
                '--'.PHP_EOL.PHP_EOL;
            $this->compressManager->write($ret);
        }
        $stmt = $this->typeAdapter->showCreateProcedure($procedureName);
        foreach ($this->dbHandler->query($stmt) as $r) {
            $this->compressManager->write(
                $this->typeAdapter->createProcedure($r)
            );

            return;
        }
    }

    /**
     * Event structure extractor.
     *
     * @param string $eventName Name of event to export
     */
    private function getEventStructure($eventName): void
    {
        if (!$this->dumpSettings['skip-comments']) {
            $ret = '--'.PHP_EOL.
                "-- Dumping events for database '".$this->dbName."'".PHP_EOL.
                '--'.PHP_EOL.PHP_EOL;
            $this->compressManager->write($ret);
        }
        $stmt = $this->typeAdapter->showCreateEvent($eventName);
        foreach ($this->dbHandler->query($stmt) as $r) {
            $this->compressManager->write(
                $this->typeAdapter->createEvent($r)
            );

            return;
        }
    }

    /**
     * Prepare values for output.
     *
     * @param string $tableName Name of table which contains rows
     * @param array $row Associative array of column names and values to be
     *   quoted
     *
     * @return array
     */
    private function prepareColumnValues($tableName, $row): array
    {
        $ret = [];
        $columnTypes = $this->tableColumnTypes[$tableName];
        foreach ($row as $colName => $colValue) {
            $ret[] = $this->escape($colValue, $columnTypes[$colName]);
        }

        return $ret;
    }

    /**
     * Escape values with quotes when needed.
     *
     * @param $colValue
     * @param $colType
     *
     * @return string
     */
    private function escape($colValue, $colType): string
    {
        if (null === $colValue) {
            return 'NULL';
        }
        if ($this->dumpSettings['hex-blob'] && $colType['is_blob']) {
            if (!empty($colValue) || 'bit' === $colType['type']) {
                return "0x${colValue}";
            }

            return "''";
        }

        if ($colType['is_numeric']) {
            return $colValue;
        }

        return $this->dbHandler->quote($colValue);
    }

    /**
     * Table rows extractor.
     *
     * @param string $tableName Name of table to export
     */
    private function listValues($tableName): void
    {
        $this->prepareListValues($tableName);

        $onlyOnce = true;
        $lineSize = 0;

        $colStmt = $this->getColumnStmt($tableName);
        $stmt = 'SELECT '.\implode(',', $colStmt)." FROM `$tableName`";

        if ($this->dumpSettings['where']) {
            $stmt .= " WHERE {$this->dumpSettings['where']}";
        }
        $resultSet = $this->dbHandler->query($stmt);
        $resultSet->setFetchMode(PDO::FETCH_ASSOC);

        foreach ($resultSet as $row) {
            $vals = $this->prepareColumnValues($tableName, $row);
            if ($onlyOnce || !$this->dumpSettings['extended-insert']) {
                if ($this->dumpSettings['complete-insert']) {
                    /* @noinspection SqlDialectInspection, SqlNoDataSourceInspection */
                    $lineSize += $this->compressManager->write(
                        "INSERT INTO `$tableName` (".
                        \implode(', ', $colStmt).
                        ') VALUES ('.\implode(',', $vals).')'
                    );
                } else {
                    /* @noinspection SqlDialectInspection, SqlNoDataSourceInspection */
                    $lineSize += $this->compressManager->write(
                        "INSERT INTO `$tableName` VALUES (".\implode(',', $vals).')'
                    );
                }
                $onlyOnce = false;
            } else {
                $lineSize += $this->compressManager->write(',('.\implode(',', $vals).')');
            }
            if (($lineSize > $this->dumpSettings['net_buffer_length']) ||
                    !$this->dumpSettings['extended-insert']) {
                $onlyOnce = true;
                $lineSize = $this->compressManager->write(';'.PHP_EOL);
            }
        }
        $resultSet->closeCursor();

        if (!$onlyOnce) {
            $this->compressManager->write(';'.PHP_EOL);
        }

        $this->endListValues($tableName);
    }

    /**
     * Table rows extractor, append information prior to dump.
     *
     * @param string $tableName Name of table to export
     */
    public function prepareListValues($tableName): void
    {
        if (!$this->dumpSettings['skip-comments']) {
            $this->compressManager->write(
                '--'.PHP_EOL.
                "-- Dumping data for table `$tableName`".PHP_EOL.
                '--'.PHP_EOL.PHP_EOL
            );
        }

        if ($this->dumpSettings['single-transaction']) {
            $this->dbHandler->exec($this->typeAdapter->setupTransaction());
            $this->dbHandler->exec($this->typeAdapter->startTransaction());
        }

        if ($this->dumpSettings['lock-tables']) {
            $this->typeAdapter->lockTable($tableName);
        }

        if ($this->dumpSettings['add-locks']) {
            $this->compressManager->write(
                $this->typeAdapter->startAddLockTable($tableName)
            );
        }

        if ($this->dumpSettings['disable-keys']) {
            $this->compressManager->write(
                $this->typeAdapter->startAddDisableKeys($tableName)
            );
        }

        // Disable autocommit for faster reload
        if ($this->dumpSettings['no-autocommit']) {
            $this->compressManager->write(
                $this->typeAdapter->startDisableAutocommit()
            );
        }
    }

    /**
     * Table rows extractor, close locks and commits after dump.
     *
     * @param string $tableName Name of table to export
     */
    public function endListValues($tableName): void
    {
        if ($this->dumpSettings['disable-keys']) {
            $this->compressManager->write(
                $this->typeAdapter->endAddDisableKeys($tableName)
            );
        }

        if ($this->dumpSettings['add-locks']) {
            $this->compressManager->write(
                $this->typeAdapter->endAddLockTable($tableName)
            );
        }

        if ($this->dumpSettings['single-transaction']) {
            $this->dbHandler->exec($this->typeAdapter->commitTransaction());
        }

        if ($this->dumpSettings['lock-tables']) {
            $this->typeAdapter->unlockTable($tableName);
        }

        // Commit to enable autocommit
        if ($this->dumpSettings['no-autocommit']) {
            $this->compressManager->write(
                $this->typeAdapter->endDisableAutocommit()
            );
        }
        $this->compressManager->write(PHP_EOL);
    }

    /**
     * Build SQL List of all columns on current table.
     *
     * @param string $tableName  Name of table to get columns
     *
     * @return array SQL sentence with columns
     */
    public function getColumnStmt($tableName): array
    {
        $colStmt = [];
        foreach ($this->tableColumnTypes[$tableName] as $colName => $colType) {
            if ('bit' === $colType['type'] && $this->dumpSettings['hex-blob']) {
                $colStmt[] = "LPAD(HEX(`${colName}`),2,'0') AS `${colName}`";
            } elseif ($colType['is_blob'] && $this->dumpSettings['hex-blob']) {
                $colStmt[] = "HEX(`${colName}`) AS `${colName}`";
            } elseif ($colType['is_virtual']) {
                $this->dumpSettings['complete-insert'] = true;
                continue;
            } else {
                $colStmt[] = "`${colName}`";
            }
        }

        return $colStmt;
    }
}
