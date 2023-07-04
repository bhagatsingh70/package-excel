<?php

namespace Bhagat\Excel\Concerns;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

interface WithReadFilter
{
    /**
     * @return IReadFilter
     */
    public function readFilter(): IReadFilter;
}
