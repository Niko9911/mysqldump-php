<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE
 * This source file is released under commercial license by Lamia Oy.
 *
 * @copyright Copyright (c) Lamia Oy (https://lamia.fi)
 * @author Niko Granö <niko.grano@ironlions.fi>
 */

namespace Niko9911\MysqlDump\Compress;

abstract class Method
{
    public static $enums = [
        'None',
        'Gzip',
        'Bzip2',
    ];

    /**
     * @param string $c
     *
     * @return bool
     */
    public static function isValid($c): bool
    {
        return \in_array($c, self::$enums, true);
    }
}
