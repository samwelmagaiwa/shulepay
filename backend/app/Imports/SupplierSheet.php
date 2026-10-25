<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/** Reads a supplier sheet into rows keyed by its (lower-cased) column headings. */
class SupplierSheet implements ToArray, WithHeadingRow
{
    public function array(array $array): void
    {
        // Rows are collected by Excel::toArray(); nothing to do per sheet.
    }
}
