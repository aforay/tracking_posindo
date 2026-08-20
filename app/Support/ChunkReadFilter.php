<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Read filter that limits PhpSpreadsheet to a single row range of one sheet,
 * so large spreadsheets can be loaded chunk by chunk.
 */
class ChunkReadFilter implements IReadFilter
{
    public function __construct(
        protected string $sheetName,
        protected int $startRow,
        protected int $endRow,
    ) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($worksheetName !== '' && $worksheetName !== $this->sheetName) {
            return false;
        }

        return $row >= $this->startRow && $row <= $this->endRow;
    }
}
