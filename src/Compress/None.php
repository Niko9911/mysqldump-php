<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE
 * This source file is released under GPL V3 License
 *
 * @copyright Copyright (c) Niko Granö & Contributors
 * @author Niko Granö <niko@ironlions.fi>
 */

namespace Niko9911\MysqlDump\Compress;

use RuntimeException;

class None extends ManagerFactory
{
    private $fileHandler;

    /**
     * @param string $filename
     *
     * @throws RuntimeException
     *
     * @return bool
     */
    public function open($filename): bool
    {
        $this->fileHandler = \fopen($filename, 'wb');
        if (false === $this->fileHandler) {
            throw new RuntimeException('Output file is not writable');
        }

        return true;
    }

    public function write($str)
    {
        if (false === ($bytesWritten = \fwrite($this->fileHandler, $str))) {
            throw new RuntimeException('Writing to file failed! Probably, there is no more free space left?');
        }

        return $bytesWritten;
    }

    public function close(): bool
    {
        return \fclose($this->fileHandler);
    }
}
