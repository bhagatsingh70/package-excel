<?php

namespace Bhagat\Excel;

use Bhagat\Excel\Concerns\Importable;
use Bhagat\Excel\Concerns\WithLimit;
use Bhagat\Excel\Concerns\WithMapping;
use Bhagat\Excel\Concerns\WithStartRow;
use Bhagat\Excel\Imports\HeadingRowFormatter;

class HeadingRowImport implements WithStartRow, WithLimit, WithMapping
{
    use Importable;

    /**
     * @var int
     */
    private $headingRow;

    /**
     * @param  int  $headingRow
     */
    public function __construct(int $headingRow = 1)
    {
        $this->headingRow = $headingRow;
    }

    /**
     * @return int
     */
    public function startRow(): int
    {
        return $this->headingRow;
    }

    /**
     * @return int
     */
    public function limit(): int
    {
        return 1;
    }

    /**
     * @param  mixed  $row
     * @return array
     */
    public function map($row): array
    {
        return HeadingRowFormatter::format($row);
    }
}
