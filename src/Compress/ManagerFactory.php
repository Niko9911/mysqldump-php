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

use RuntimeException;

abstract class ManagerFactory
{
    /**
     * @param string $c
     *
     * @throws \RuntimeException
     *
     * @return Bzip2|Gzip|None
     */
    public static function create($c)
    {
        $c = \ucfirst(\mb_strtolower($c));
        if (!Method::isValid($c)) {
            throw new RuntimeException("Compression method ($c) is not defined yet");
        }

        $method = __NAMESPACE__.'\\'.$c;

        return new $method();
    }
}
