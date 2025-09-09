<?php

namespace App\Exports;

use App\Models\Client;
use App\Services\InvoicePriceService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AutocountARInvoiceExport implements FromCollection, WithHeadings, WithColumnFormatting
{

    protected $consignorId;
    protected $startDate;
    protected $endDate;
    protected $invoicePriceService;

    public function __construct($consignorId, $startDate, $endDate)
    {
        $this->consignorId = $consignorId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->invoicePriceService = new InvoicePriceService();
    }

    private function getExportCount()
    {
        $filePath = 'export_count.txt';
        if (Storage::exists($filePath)) {
            return (int) Storage::get($filePath);
        }
        return 0;
    }

    private function incrementExportCount()
    {
        $filePath = 'export_count.txt';
        $count = $this->getExportCount() + 1;
        Storage::put($filePath, $count);
    }

    private function generateExportNo()
    {
        $currentDate = Carbon::now();
        $yearMonth = $currentDate->format('y') . $currentDate->format('m');
        $exportCount = $this->getExportCount();
        $exportNo = sprintf("I-%s-%02d", $yearMonth, $exportCount + 1);
        $this->incrementExportCount();
        return $exportNo;
    }

    public function columnFormats(): array
    {
        return [
            'U' => NumberFormat::FORMAT_NUMBER_00, // B column = 2 decimal places
        ];
    }

    // public function collection()
    // {
    //     $data = [];
    //     // $totalPrice = 0;

    //     $consignor = Client::find($this->consignorId);
    //     $consignorCode = $consignor ? $consignor->code : "";

    //     // 获取 manifest_lists 数据，并排除软删除的记录
    //     $manifestData = DB::table('manifest_lists')
    //         ->where('consignor_id', $this->consignorId)
    //         ->whereBetween('created_at', [
    //             Carbon::parse($this->startDate)->startOfDay(),
    //             Carbon::parse($this->endDate)->endOfDay()
    //         ])
    //         ->whereNull('deleted_at') // 加上这一行
    //         ->get();

    //     // 获取所有相关的 manifest_info_id
    //     $manifestInfoIds = $manifestData->pluck('manifest_info_id')->unique()->filter();

    //     // 提前查询 manifest_infos 并用 id 做 key（id => date），也排除软删除的记录
    //     $manifestInfos = DB::table('manifest_infos')
    //         ->whereIn('id', $manifestInfoIds)
    //         ->whereNull('deleted_at') // 加上这一行
    //         ->pluck('date', 'id');

    //     // 排序 manifestData based on delivery date
    //     $manifestData = $manifestData->sortByDesc(function ($row) use ($manifestInfos) {
    //         return isset($manifestInfos[$row->manifest_info_id])
    //             ? Carbon::parse($manifestInfos[$row->manifest_info_id])
    //             : Carbon::createFromTimestamp(0); // 如果找不到就给个最早的时间
    //     })->values(); // 重新索引

    //     $exportNo = $this->generateExportNo();
    //     $exportDate = Carbon::now()->format('d/m/Y');

    //     foreach ($manifestData as $row) {
    //         $rowTotal = !empty($row->total_price) ? floatval($row->total_price) : 0;
    //         // $totalPrice += $rowTotal;

    //         $data[] = [
    //             $exportNo,
    //             $exportDate,
    //             $consignorCode,
    //             'SALES',
    //             'C.O.D.',
    //             '',
    //             'SALES',
    //             'MYR',
    //             1.00,
    //             '',
    //             '',
    //             'F',
    //             '5001-0000',
    //             1.00,
    //             $row->cn_no,
    //             '',
    //             '',
    //             '',
    //             0,
    //             0,
    //             number_format((float) $rowTotal, 2, '.', '')
    //         ];
    //     }
    //     return new Collection($data);
    // }

    public function collection()
    {
        $data = [];

        $consignor = Client::find($this->consignorId);
        $consignorCode = $consignor ? $consignor->code : "";

        // Use the new service to process manifest prices with maximum price logic
        $processedManifestData = $this->invoicePriceService->processManifestPricesByDateRange(
            $this->consignorId, 
            $this->startDate, 
            $this->endDate
        );

        $exportNo = $this->generateExportNo();
        $exportDate = Carbon::now()->format('d/m/Y');

        foreach ($processedManifestData as $row) {
            $rowTotal = !empty($row->total_price) ? floatval($row->total_price) : 0;

            $data[] = [
                $exportNo,
                $exportDate,
                $consignorCode,
                'SALES',
                'C.O.D.',
                '',
                'SALES',
                'MYR',
                1.00,
                '',
                '',
                'F',
                '5001-0000',
                1.00,
                $row->cn_no,
                '',
                '',
                '',
                0,
                0,
                number_format((float) $rowTotal, 2, '.', '')
            ];
        }

        return new Collection($data);
    }


    public function headings(): array
    {
        return [
            'DocNo',
            'DocDate',
            'DebtorCode',
            'JournalType',
            'DisplayTerm',
            'SalesAgent',
            'Description',
            'CurrencyCode',
            'CurrencyRate',
            'RefNo2',
            'Note',
            'InclusiveTax',
            'AccNo',
            'ToAccountRate',
            'DetailDescription',
            'ProjNo',
            'DeptNo',
            'TaxType',
            'TaxableAmt',
            'TaxAdjustment',
            'Amount',
        ];
    }
}
