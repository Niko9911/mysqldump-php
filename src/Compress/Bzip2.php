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

class Bzip2 extends ManagerFactory
{
    private $fileHandler;

    public function __construct()
    {
        if (!\function_exists('bzopen')) {
            throw new RuntimeException('Compression is enabled, but bzip2 lib is not installed or configured properly');
        }
    }

    /**
     * @param string $filename
     *
     * @throws RuntimeException
     *
     * @return bool
     */
    public function open($filename): bool
    {
        $this->fileHandler = bzopen($filename, 'w');
        if (false === $this->fileHandler) {
            throw new RuntimeException('Output file is not writable');
        }

        return true;
    }

    public function write($str)
    {
        if (false === ($bytesWritten = bzwrite($this->fileHandler, $str))) {
            throw new RuntimeException('Writing to file failed! Probably, there is no more free space left?');
        }

        return $bytesWritten;
    }

    public function close()
    {
        return bzclose($this->fileHandler);
    }
}
