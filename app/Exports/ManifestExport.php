<?php

namespace App\Exports;

use App\Models\Manifest;
use App\Models\ShippingRate;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class ManifestExport implements FromCollection, WithHeadings
{
    protected $manifestIds;

    public function __construct(array $manifestIds)
    {
        $this->manifestIds = $manifestIds;
    }

    public function collection()
    {
        $manifests = Manifest::whereIn('id', $this->manifestIds)->get();

        return new Collection($manifests->map(function ($manifest, $index) {
            $shippingRate = ShippingRate::where('origin', $manifest->from)
                                        ->where('destination', $manifest->to)
                                        ->first();

            $pricePerKg = $shippingRate ? $shippingRate->additional_price_per_kg : 0;

            return [
                'Item' => $index + 1,
                'Description' => "{$manifest->from} - {$manifest->to}",
                'Consignment Note' => $manifest->cn_no,
                'Delivery Date' => $manifest->date,
                'Qty' => $manifest->pcs,
                'U/ Price RM' => number_format($pricePerKg, 2),
                'Total RM' => number_format($manifest->total_price, 2),
            ];
        }));
    }

    public function headings(): array
    {
        return ["Item", "Description", "Consignment Note", "Delivery Date", "Qty", "U/ Price RM", "Total RM"];
    }
}
