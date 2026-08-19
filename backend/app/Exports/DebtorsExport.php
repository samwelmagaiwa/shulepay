<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class DebtorsExport implements FromCollection
{
    public function collection()
    {
        return collect([]);
    }
}
