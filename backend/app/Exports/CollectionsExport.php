<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class CollectionsExport implements FromCollection
{
    public function collection()
    {
        return collect([]);
    }
}
