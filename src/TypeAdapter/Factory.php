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

abstract class Factory implements Type
{
    /** @var \PDO */
    protected $dbHandler;

    /** @var array */
    protected $dumpSettings = [];

    /**
     * Factory constructor.
     *
     * @param string $dbHandler
     * @param array  $dumpSettings
     */
    public function __construct(string $dbHandler, array $dumpSettings = [])
    {
        $this->dbHandler = $dbHandler;
        $this->dumpSettings = $dumpSettings;
    }

    public static function create(string $c, $dbHandler = null, array $dumpSettings = [])
    {
        $c = \ucfirst(\mb_strtolower($c));
        if (!AbstractTypeAdapter::isValid($c)) {
            throw new RuntimeException("Database type support for ($c) not yet available");
        }
        $method = __NAMESPACE__.'\\'.$c;

        return new $method($dbHandler, $dumpSettings);
    }

    public function showCreateTable(string $tableName): string
    {
        return "SELECT tbl_name as 'Table', sql as 'Create Table' ".
            'FROM sqlite_master '.
            "WHERE type='table' AND tbl_name='$tableName'";
    }

    public function showCreateView(string $viewName): string
    {
        return "SELECT tbl_name as 'View', sql as 'Create View' ".
            'FROM sqlite_master '.
            "WHERE type='view' AND tbl_name='$viewName'";
    }

    public function showColumns(string $columns): string
    {
        return "pragma table_info(${columns})";
    }

    public function commitTransaction(): string
    {
        return 'COMMIT';
    }
}
