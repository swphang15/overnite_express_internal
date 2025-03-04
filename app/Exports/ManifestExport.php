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
        $totalPriceSum = 0;

        $data = $manifests->map(function ($manifest, $index) use (&$totalPriceSum) {
            $shippingRate = ShippingRate::where('origin', $manifest->from)
                                        ->where('destination', $manifest->to)
                                        ->first();

            $pricePerKg = $shippingRate ? $shippingRate->additional_price_per_kg : 0;
            $discount = 0; // 默认折扣为 0
            $totalPrice = $manifest->total_price;

            $totalPriceSum += $totalPrice; // 计算总金额

            return [
                'Item' => $index + 1,
                'Description' => "{$manifest->from} - {$manifest->to}",
                'Consignment Note' => $manifest->cn_no,
                'Delivery Date' => $manifest->date,
                'Qty' => $manifest->pcs,
                'U/ Price RM' => number_format($pricePerKg, 2),
                'Disc. (RM)' => number_format($discount, 2), // 折扣列
                'Total RM' => number_format($totalPrice, 2),
            ];
        });

        // 添加总计行
        $data->push([
            'Item' => '',
            'Description' => '',
            'Consignment Note' => '',
            'Delivery Date' => '',
            'Qty' => '',
            'U/ Price RM' => '',
            'Disc. (RM)' => 'Total Price:',
            'Total RM' => number_format($totalPriceSum, 2),
        ]);

        return new Collection($data);
    }

    public function headings(): array
    {
        return [
            "Item", 
            "Description", 
            "Consignment Note", 
            "Delivery Date", 
            "Qty", 
            "U/ Price RM", 
            "Disc. (RM)", 
            "Total RM"
        ];
    }
}
