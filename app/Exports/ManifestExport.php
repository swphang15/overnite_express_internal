<?php

namespace App\Exports;

use App\Models\Manifest;
use App\Models\ShippingRate;
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
        $manifests = Manifest::where('id', $this->manifestId)->get();

        return $manifests->map(function ($manifest, $index) {
            // **确保大小写匹配**
            $shippingRate = ShippingRate::where('origin', [$manifest->from])
                                        ->where('destination', [$manifest->to])
                                        ->first();

                                        
        
            // **如果匹配不到数据，调试看看 manifest->from 和 manifest->to 是什么**
            if (!$shippingRate) {
                \Log::error("No shipping rate found for origin: {$manifest->from}, destination: {$manifest->to}");
            }
        
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
        });
        
    }

    public function headings(): array
    {
        return ["Item", "Description", "Consignment Note", "Delivery Date", "Qty", "U/ Price RM", "Total RM"];
    }
}
