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

use RuntimeException;

class Mysql extends Factory
{
    public const DEFINER_RE = 'DEFINER=`(?:[^`]|``)*`@`(?:[^`]|``)*`';

    // Numerical Mysql types
    public $mysqlTypes = [
        'numerical' => [
            'bit',
            'tinyint',
            'smallint',
            'mediumint',
            'int',
            'integer',
            'bigint',
            'real',
            'double',
            'float',
            'decimal',
            'numeric',
        ],
        'blob' => [
            'tinyblob',
            'blob',
            'mediumblob',
            'longblob',
            'binary',
            'varbinary',
            'bit',
            'geometry', /* http://bugs.mysql.com/bug.php?id=43544 */
            'point',
            'linestring',
            'polygon',
            'multipoint',
            'multilinestring',
            'multipolygon',
            'geometrycollection',
        ],
    ];

    public function databases(string $databaseName): string
    {
        $resultSet = $this->dbHandler->query("SHOW VARIABLES LIKE 'character_set_database';");
        $characterSet = $resultSet->fetchColumn(1);
        $resultSet->closeCursor();

        $resultSet = $this->dbHandler->query("SHOW VARIABLES LIKE 'collation_database';");
        $collationDb = $resultSet->fetchColumn(1);
        $resultSet->closeCursor();
        $ret = '';

        $ret .= "CREATE DATABASE /*!32312 IF NOT EXISTS*/ `${databaseName}`".
            " /*!40100 DEFAULT CHARACTER SET ${characterSet} ".
            " COLLATE ${collationDb} */;".PHP_EOL.PHP_EOL.
            "USE `${databaseName}`;".PHP_EOL.PHP_EOL;

        return $ret;
    }

    public function showCreateTable(string $tableName): string
    {
        return "SHOW CREATE TABLE `$tableName`";
    }

    public function showCreateView(string $viewName): string
    {
        return "SHOW CREATE VIEW `$viewName`";
    }

    public function showCreateTrigger(?string $triggerName = null): string
    {
        return "SHOW CREATE TRIGGER `$triggerName`";
    }

    public function showCreateProcedure(string $procedureName): string
    {
        return "SHOW CREATE PROCEDURE `$procedureName`";
    }

    public function showCreateEvent(string $eventName): string
    {
        return "SHOW CREATE EVENT `$eventName`";
    }

    public function createTable(?string $row = null): string
    {
        if (!isset($row['Create Table'])) {
            throw new RuntimeException('Error getting table code, unknown output');
        }

        $createTable = $row['Create Table'];
        if ($this->dumpSettings['reset-auto-increment']) {
            $match = '/AUTO_INCREMENT=[0-9]+/s';
            $replace = '';
            $createTable = \preg_replace($match, $replace, $createTable);
        }

        $ret = '/*!40101 SET @saved_cs_client     = @@character_set_client */;'.PHP_EOL.
            '/*!40101 SET character_set_client = '.$this->dumpSettings['default-character-set'].' */;'.PHP_EOL.
            $createTable.';'.PHP_EOL.
            '/*!40101 SET character_set_client = @saved_cs_client */;'.PHP_EOL.
            PHP_EOL;

        return $ret;
    }

    public function createView(?string $row = null): string
    {
        $ret = '';
        if (!isset($row['Create View'])) {
            throw new RuntimeException('Error getting view structure, unknown output');
        }

        $viewStmt = $row['Create View'];

        $definerStr = $this->dumpSettings['skip-definer'] ? '' : '/*!50013 \2 */'.PHP_EOL;

        if ($viewStmtReplaced = \preg_replace(
            '/^(CREATE(?:\s+ALGORITHM=(?:UNDEFINED|MERGE|TEMPTABLE))?)\s+('
            .self::DEFINER_RE.'(?:\s+SQL SECURITY DEFINER|INVOKER)?)?\s+(VIEW .+)$/',
            '/*!50001 \1 */'.PHP_EOL.$definerStr.'/*!50001 \3 */',
            $viewStmt,
            1
        )) {
            $viewStmt = $viewStmtReplaced;
        }

        $ret .= $viewStmt.';'.PHP_EOL.PHP_EOL;

        return $ret;
    }

    public function createTrigger(?string $row = null): string
    {
        $ret = '';
        if (!isset($row['SQL Original Statement'])) {
            throw new RuntimeException('Error getting trigger code, unknown output');
        }

        $triggerStmt = $row['SQL Original Statement'];
        $definerStr = $this->dumpSettings['skip-definer'] ? '' : '/*!50017 \2*/ ';
        if ($triggerStmtReplaced = \preg_replace(
            '/^(CREATE)\s+('.self::DEFINER_RE.')?\s+(TRIGGER\s.*)$/s',
            '/*!50003 \1*/ '.$definerStr.'/*!50003 \3 */',
            $triggerStmt,
            1
        )) {
            $triggerStmt = $triggerStmtReplaced;
        }

        $ret .= 'DELIMITER ;;'.PHP_EOL.
            $triggerStmt.';;'.PHP_EOL.
            'DELIMITER ;'.PHP_EOL.PHP_EOL;

        return $ret;
    }

    public function createProcedure(?string $row = null): string
    {
        $ret = '';
        if (!isset($row['Create Procedure'])) {
            throw new RuntimeException('Error getting procedure code, unknown output. '.
                "Please check 'https://bugs.mysql.com/bug.php?id=14564'");
        }
        $procedureStmt = $row['Create Procedure'];

        $ret .= '/*!50003 DROP PROCEDURE IF EXISTS `'.
            $row['Procedure'].'` */;'.PHP_EOL.
            '/*!40101 SET @saved_cs_client     = @@character_set_client */;'.PHP_EOL.
            '/*!40101 SET character_set_client = '.$this->dumpSettings['default-character-set'].' */;'.PHP_EOL.
            'DELIMITER ;;'.PHP_EOL.
            $procedureStmt.' ;;'.PHP_EOL.
            'DELIMITER ;'.PHP_EOL.
            '/*!40101 SET character_set_client = @saved_cs_client */;'.PHP_EOL.PHP_EOL;

        return $ret;
    }

    public function createEvent($row): string
    {
        $ret = '';
        if (!isset($row['Create Event'])) {
            throw new RuntimeException('Error getting event code, unknown output. '.
                "Please check 'http://stackoverflow.com/questions/10853826/mysql-5-5-create-event-gives-syntax-error'");
        }
        $eventName = $row['Event'];
        $eventStmt = $row['Create Event'];
        $sqlMode = $row['sql_mode'];
        $definerStr = $this->dumpSettings['skip-definer'] ? '' : '/*!50117 \2*/ ';

        if ($eventStmtReplaced = \preg_replace(
            '/^(CREATE)\s+('.self::DEFINER_RE.')?\s+(EVENT .*)$/',
            '/*!50106 \1*/ '.$definerStr.'/*!50106 \3 */',
            $eventStmt,
            1
        )) {
            $eventStmt = $eventStmtReplaced;
        }

        $ret .= '/*!50106 SET @save_time_zone= @@TIME_ZONE */ ;'.PHP_EOL.
            '/*!50106 DROP EVENT IF EXISTS `'.$eventName.'` */;'.PHP_EOL.
            'DELIMITER ;;'.PHP_EOL.
            '/*!50003 SET @saved_cs_client      = @@character_set_client */ ;;'.PHP_EOL.
            '/*!50003 SET @saved_cs_results     = @@character_set_results */ ;;'.PHP_EOL.
            '/*!50003 SET @saved_col_connection = @@collation_connection */ ;;'.PHP_EOL.
            '/*!50003 SET character_set_client  = utf8 */ ;;'.PHP_EOL.
            '/*!50003 SET character_set_results = utf8 */ ;;'.PHP_EOL.
            '/*!50003 SET collation_connection  = utf8_general_ci */ ;;'.PHP_EOL.
            '/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;'.PHP_EOL.
            "/*!50003 SET sql_mode              = '".$sqlMode."' */ ;;".PHP_EOL.
            '/*!50003 SET @saved_time_zone      = @@time_zone */ ;;'.PHP_EOL.
            "/*!50003 SET time_zone             = 'SYSTEM' */ ;;".PHP_EOL.
            $eventStmt.' ;;'.PHP_EOL.
            '/*!50003 SET time_zone             = @saved_time_zone */ ;;'.PHP_EOL.
            '/*!50003 SET sql_mode              = @saved_sql_mode */ ;;'.PHP_EOL.
            '/*!50003 SET character_set_client  = @saved_cs_client */ ;;'.PHP_EOL.
            '/*!50003 SET character_set_results = @saved_cs_results */ ;;'.PHP_EOL.
            '/*!50003 SET collation_connection  = @saved_col_connection */ ;;'.PHP_EOL.
            'DELIMITER ;'.PHP_EOL.
            '/*!50106 SET TIME_ZONE= @save_time_zone */ ;'.PHP_EOL.PHP_EOL;
        // Commented because we are doing this in restore_parameters()
        // "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;" . PHP_EOL . PHP_EOL;

        return $ret;
    }

    public function showTables(string $table): string
    {
        return 'SELECT TABLE_NAME AS tbl_name '.
            'FROM INFORMATION_SCHEMA.TABLES '.
            "WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA='${table}'";
    }

    public function showViews(string $view): string
    {
        return 'SELECT TABLE_NAME AS tbl_name '.
            'FROM INFORMATION_SCHEMA.TABLES '.
            "WHERE TABLE_TYPE='VIEW' AND TABLE_SCHEMA='${view}'";
    }

    public function showTriggers(string $trigger): string
    {
        return "SHOW TRIGGERS FROM `${trigger}`;";
    }

    public function showColumns(string $columns): string
    {
        return "SHOW COLUMNS FROM `${columns}`;";
    }

    public function showProcedures(string $procedure): string
    {
        return 'SELECT SPECIFIC_NAME AS procedure_name '.
            'FROM INFORMATION_SCHEMA.ROUTINES '.
            "WHERE ROUTINE_TYPE='PROCEDURE' AND ROUTINE_SCHEMA='${procedure}'";
    }

    /**
     * Get query string to ask for names of events from current database.
     *
     * @param string Name of database
     *
     * @throws RuntimeException
     *
     * @return string
     */
    public function showEvents(string $event): string
    {
        return 'SELECT EVENT_NAME AS event_name '.
            'FROM INFORMATION_SCHEMA.EVENTS '.
            "WHERE EVENT_SCHEMA='${event}'";
    }

    public function setupTransaction(): string
    {
        return 'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ';
    }

    public function startTransaction(): string
    {
        return 'START TRANSACTION';
    }

    public function lockTable(string $table): int
    {
        return $this->dbHandler->exec("LOCK TABLES `${table}` READ LOCAL");
    }

    public function unlockTable(string $table): int
    {
        return $this->dbHandler->exec('UNLOCK TABLES');
    }

    public function startAddLockTable(string $table): string
    {
        return "LOCK TABLES `${table}` WRITE;".PHP_EOL;
    }

    public function endAddLockTable(string $table): string
    {
        return 'UNLOCK TABLES;'.PHP_EOL;
    }

    public function startAddDisableKeys(string $key): string
    {
        return "/*!40000 ALTER TABLE `${key}` DISABLE KEYS */;".
            PHP_EOL;
    }

    public function endAddDisableKeys(string $key): string
    {
        return "/*!40000 ALTER TABLE `${key}` ENABLE KEYS */;".
            PHP_EOL;
    }

    public function startDisableAutocommit(): string
    {
        return 'SET autocommit=0;'.PHP_EOL;
    }

    public function endDisableAutocommit(): string
    {
        return 'COMMIT;'.PHP_EOL;
    }

    public function addDropDatabase(string $database): string
    {
        return "/*!40000 DROP DATABASE IF EXISTS `${database}`*/;".
            PHP_EOL.PHP_EOL;
    }

    public function addDropTrigger(string $trigger): string
    {
        return "DROP TRIGGER IF EXISTS `${trigger}`;".PHP_EOL;
    }

    public function dropTable(string $table): string
    {
        /* @noinspection SqlDialectInspection, SqlNoDataSourceInspection */
        return "DROP TABLE IF EXISTS `${table}`;".PHP_EOL;
    }

    public function dropView(string $view): string
    {
        /* @noinspection SqlDialectInspection, SqlNoDataSourceInspection */
        return "DROP TABLE IF EXISTS `${view}`;".PHP_EOL.
            "/*!50001 DROP VIEW IF EXISTS `${view}`*/;".PHP_EOL;
    }

    public function getDatabaseHeader(string $header): string
    {
        return '--'.PHP_EOL.
            "-- Current Database: `${header}`".PHP_EOL.
            '--'.PHP_EOL.PHP_EOL;
    }

    /**
     * Decode column metadata and fill info structure.
     * type, is_numeric and is_blob will always be available.
     *
     * @param array $colType Array returned from "SHOW COLUMNS FROM tableName"
     *
     * @return array
     */
    public function parseColumnType(array $colType = []): array
    {
        $colInfo = [];
        $colParts = \explode(' ', $colType['Type']);

        if ($fparen = \mb_strpos($colParts[0], '(')) {
            $colInfo['type'] = \mb_substr($colParts[0], 0, $fparen);
            $colInfo['length'] = \str_replace(')', '', \mb_substr($colParts[0], $fparen + 1));
            $colInfo['attributes'] = $colParts[1] ?? null;
        } else {
            $colInfo['type'] = $colParts[0];
        }
        $colInfo['is_numeric'] = \in_array($colInfo['type'], $this->mysqlTypes['numerical'], true);
        $colInfo['is_blob'] = \in_array($colInfo['type'], $this->mysqlTypes['blob'], true);
        // for virtual columns that are of type 'Extra', column type
        // could by "STORED GENERATED" or "VIRTUAL GENERATED"
        // MySQL reference: https://dev.mysql.com/doc/refman/5.7/en/create-table-generated-columns.html
        $colInfo['is_virtual'] = false !== \mb_strpos($colType['Extra'], 'VIRTUAL GENERATED') || false !== \mb_strpos($colType['Extra'], 'STORED GENERATED');

        return $colInfo;
    }

    public function backupParameters(): string
    {
        $ret = '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;'.PHP_EOL.
            '/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;'.PHP_EOL.
            '/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;'.PHP_EOL.
            '/*!40101 SET NAMES '.$this->dumpSettings['default-character-set'].' */;'.PHP_EOL;

        if (false === $this->dumpSettings['skip-tz-utc']) {
            $ret .= '/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;'.PHP_EOL.
                "/*!40103 SET TIME_ZONE='+00:00' */;".PHP_EOL;
        }

        $ret .= '/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;'.PHP_EOL.
            '/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;'.PHP_EOL.
            "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;".PHP_EOL.
            '/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;'.PHP_EOL.PHP_EOL;

        return $ret;
    }

    public function restoreParameters(): string
    {
        $ret = '';

        if (false === $this->dumpSettings['skip-tz-utc']) {
            $ret .= '/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;'.PHP_EOL;
        }

        $ret .= '/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;'.PHP_EOL.
            '/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;'.PHP_EOL.
            '/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;'.PHP_EOL.
            '/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;'.PHP_EOL.
            '/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;'.PHP_EOL.
            '/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;'.PHP_EOL.
            '/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;'.PHP_EOL.PHP_EOL;

        return $ret;
    }

    public function startDisableForeignKeysCheck(): string
    {
        return PHP_EOL;
    }

    public function endDisableForeignKeysCheck(): string
    {
        return PHP_EOL;
    }
}
