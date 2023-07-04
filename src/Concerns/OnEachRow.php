<?php

namespace Bhagat\Excel\Concerns;

use Bhagat\Excel\Row;

interface OnEachRow
{
    /**
     * @param  Row  $row
     */
    public function onRow(Row $row);
}
