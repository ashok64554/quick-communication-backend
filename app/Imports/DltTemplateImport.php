<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DltTemplateImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return true;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function startRow(): int 
    {
         return 2;
    }
}
