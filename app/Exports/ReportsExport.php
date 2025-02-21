<?php

namespace App\Exports;

use App\Models\Manifest;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReportsExport implements FromCollection, WithHeadings
{
    public function collection()
{
    return Manifest::select(
        'origin', 'consignor_id', 'consignee_id', 'cn_no', 'pcs', 'kg',
        'gram', 'remarks', 'date', 'awb_no', 'to', 'from', 'flt', 'manifest_no'
    )->get();
}


    public function headings(): array
    {
        return [
            'Origin', 'Consignor', 'Consignee', 'C/N No.', 'PCS', 'KG', 'GRAM',
            'Remarks', 'Date', 'AWB No.', 'To', 'From', 'FLT', 'Manifest No.'
        ];
    }
}
