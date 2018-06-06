<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE
 * This source file is released under GPL V3 License
 *
 * @copyright Copyright (c) Niko Granö & Contributors
 * @author Niko Granö <niko@ironlions.fi>
 */

namespace Niko9911\MysqlDump\TypeAdapter;

interface Type
{
    /**
     * Type constructor.
     *
     * @param \PDO $dbHandler
     * @param array       $dumpSettings
     */
    public function __construct(\PDO $dbHandler, array $dumpSettings = []);

    /**
     * Description:.
     *
     * @param string $type Type of database factory to create (Mysql, Sqlite,...)
     * @param \PDO   $dbHandler
     * @param array  $dumpSettings
     *
     * @return mixed
     */
    public static function create(string $type, \PDO $dbHandler, array $dumpSettings = []);

    /**
     * Description: Add sql to create and use database.
     *
     * @param string $database
     *
     * @return string
     */
    public function databases(string $database): string;

    /**
     * Description:.
     *
     * @param string $tableName
     *
     * @return string
     */
    public function showCreateTable(string $tableName): string;

    /**
     * Description: Get table creation code from database.
     *
     * @param string $row
     *
     * @return string
     */
    public function createTable(string $row = ''): string;

    /**
     * Description:.
     *
     * @param string $viewName
     *
     * @return string
     */
    public function showCreateView(string $viewName): string;

    /**
     * Description:.
     *
     * @param string $row
     *
     * @return string
     */
    public function createView(string $row = ''): string;

    /**
     * Description: Get trigger creation code from database.
     *
     * @param string $triggerName
     *
     * @return string
     */
    public function showCreateTrigger(string $triggerName = ''): string;

    /**
     * Description: Modify trigger code, add delimiters, etc.
     *
     * @param string $triggerName
     *
     * @return string
     */
    public function createTrigger(string $triggerName = ''): string;

    /**
     * Description: Modify procedure code, add delimiters, etc.
     *
     * @param string $procedureName
     *
     * @return string
     */
    public function createProcedure(string $procedureName = ''): string;

    /**
     * Description:.
     *
     * @param string $table
     *
     * @return string
     */
    public function showTables(string $table): string;

    /**
     * Description:.
     *
     * @param string $view
     *
     * @return mixed
     */
    public function showViews(string $view);

    /**
     * Description:.
     *
     * @param string $trigger
     *
     * @return mixed
     */
    public function showTriggers(string $trigger);

    /**
     * Description:.
     *
     * @param string $columns
     *
     * @return string
     */
    public function showColumns(string $columns): string;

    /**
     * Description:.
     *
     * @param string $procedures
     *
     * @return string
     */
    public function showProcedures(string $procedures): string;

    /**
     * Description:.
     *
     * @param string $event
     *
     * @return string
     */
    public function showEvents(string $event): string;

    /**
     * Description:.
     *
     * @return string
     */
    public function setupTransaction(): string;

    /**
     * Description:.
     *
     * @return string
     */
    public function startTransaction(): string;

    /**
     * Description:.
     *
     * @return string
     */
    public function commitTransaction(): string;

    /**
     * Description:.
     *
     * @param string $table
     *
     * @return int
     */
    public function lockTable(string $table): ?int;

    /**
     * Description:.
     *
     * @param string $table
     *
     * @return int
     */
    public function unlockTable(string $table): ?int;

    /**
     * Description:.
     *
     * @param string $table
     *
     * @return string
     */
    public function startAddLockTable(string $table): string;

    /**
     * Description:.
     *
     * @param string $table
     *
     * @return string
     */
    public function endAddLockTable(string $table): string;

    /**
     * Description:.
     *
     * @param string $key
     *
     * @return string
     */
    public function startAddDisableKeys(string $key): string;

    /**
     * Description:.
     *
     * @param string $key
     *
     * @return string
     */
    public function endAddDisableKeys(string $key): string;

    /**
     * Description:.
     *
     * @return string
     */
    public function startDisableForeignKeysCheck(): string;

    /**
     * Description:.
     *
     * @return string
     */
    public function endDisableForeignKeysCheck(): string;

    /**
     * Description:.
     *
     * @param string $database
     *
     * @return string
     */
    public function addDropDatabase(string $database): string;

    /**
     * Description:.
     *
     * @param string $trigger
     *
     * @return string
     */
    public function addDropTrigger(string $trigger): string;

    /**
     * Description:.
     *
     * @param string $table
     *
     * @return string
     */
    public function dropTable(string $table): string;

    /**
     * Description:.
     *
     * @param string $view
     *
     * @return string
     */
    public function dropView(string $view): string;

    /**
     * Description: Decode column metadata and fill info structure.
     * type, is_numeric and is_blob will always be available.
     *
     * @param array $colType Array returned from "SHOW COLUMNS FROM tableName"
     *
     * @return array
     */
    public function parseColumnType(array $colType = []): array;

    /**
     * Description:.
     *
     * @return string
     */
    public function backupParameters(): string;

    /**
     * Description:.
     *
     * @return string
     */
    public function restoreParameters(): string;

    /**
     * Description:.
     *
     * @param string $header
     *
     * @return string
     */
    public function getDatabaseHeader(string $header): string;

    /**
     * Description:.
     *
     * @return string
     */
    public function startDisableAutocommit(): string;

    /**
     * Description:.
     *
     * @return string
     */
    public function endDisableAutocommit(): string;

    /**
     * Description:.
     *
     * @param string $eventName
     *
     * @return string
     */
    public function showCreateEvent(string $eventName): string;

    /**
     * Description:.
     *
     * @param $row
     *
     * @return string
     */
    public function createEvent(array $row): string;

    /**
     * Description:.
     *
     * @param string $procedureName
     *
     * @return string
     */
    public function showCreateProcedure(string $procedureName): string;
}
