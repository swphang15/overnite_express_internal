<?php

namespace App\Exports;

use App\Models\Manifest;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ManifestExport implements FromCollection, WithHeadings
{
    protected $manifestId;

    public function __construct($manifestId)
    {
        $this->manifestId = $manifestId;
    }

    public function collection()
    {
        $manifest = Manifest::findOrFail($this->manifestId);

        return collect([
            [
                'ID' => $manifest->id,
                'Origin' => $manifest->origin,
                'Consignor ID' => $manifest->consignor_id,
                'Consignee ID' => $manifest->consignee_id,
                'CN No' => $manifest->cn_no,
                'Total Price' => $manifest->total_price,
                'Date' => $manifest->date,
                'AWB No' => $manifest->awb_no,
            ]
        ]);
    }

    public function headings(): array
    {
        return ["ID", "Origin", "Consignor ID", "Consignee ID", "CN No", "Total Price", "Date", "AWB No"];
    }
}
