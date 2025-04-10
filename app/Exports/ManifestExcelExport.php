<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;
use App\Models\ManifestList;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ManifestExcelExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected $consignorId;
    protected $startDate;
    protected $endDate;
    protected $totalPrice; // ✅ 新增 totalPrice 属性

    public function __construct($consignorId, $startDate, $endDate)
    {
        $this->consignorId = $consignorId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * 获取导出次数
     */
    private function getExportCount()
    {
        $filePath = 'export_count.txt';
        if (Storage::exists($filePath)) {
            return (int) Storage::get($filePath);
        }
        return 0;
    }

    /**
     * 递增导出次数
     */
    private function incrementExportCount()
    {
        $filePath = 'export_count.txt';
        $count = $this->getExportCount() + 1;
        Storage::put($filePath, $count);
    }

    /**
     * 生成 I-YYMM-XX 自动编号
     */
    private function generateExportNo()
    {
        $currentDate = Carbon::now();
        $yearMonth = $currentDate->format('y') . $currentDate->format('m');
        $exportCount = $this->getExportCount();
        $exportNo = sprintf("I-%s-%02d", $yearMonth, $exportCount + 1);
        $this->incrementExportCount();
        return $exportNo;
    }

    public function collection()
    {
        $data = [];
        $totalPrice = 0;

        // 获取 Consignor 信息
        $consignor = Client::find($this->consignorId);
        $consignorName = $consignor ? $consignor->name : "UNKNOWN";

        // 查询 Manifest 数据，并按日期筛选
        $manifestData = DB::table('manifest_lists')
            ->where('consignor_id', $this->consignorId)
            ->whereBetween('created_at', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ])
            ->get();

        // 生成 Invoice No.
        $exportNo = $this->generateExportNo();
        $exportDate = Carbon::now()->format('d-m-Y');

        // Excel Header
        $data[] = ["", "", "", "", "INVOICE", "", "", "No.", ": $exportNo"];
        $data[] = [$consignorName, "", "", "", "", "", "", "Your Ref.", ":"];
        $data[] = ["", "", "", "", "", "", "", "Our D/O No.", ":"];
        $data[] = ["CLIENT / DESTINATION", "", "", "", "", "", "", "Terms", ": C.O.D"];
        $data[] = ["", "", "", "", "", "", "", "Date", ": $exportDate"];
        $data[] = ["TEL : ____________", "", "FAX : ____________", "", "", "", "", "Page", ":"];
        $data[] = [""]; // 空行

        // 表头
        $data[] = ["Item", "Manifest No", "Description", "Consignment Note", "Delivery Date", "Qty", "Weight", "UOM", "Total RM"];

        // 数据内容
        $counter = 1;
        foreach ($manifestData as $row) {
            $rowTotal = !empty($row->total_price) ? floatval($row->total_price) : 0;
            $totalPrice += $rowTotal;

            $weight = floatval($row->kg) + (floatval($row->gram) / 1000);
            $manifestNo = DB::table('manifest_infos')->where('id', $row->manifest_info_id)->value('manifest_no');

            $data[] = [
                $counter++,
                $manifestNo ?? "N/A",
                $row->origin . " - " . $row->destination,
                $row->cn_no,
                Carbon::parse($row->created_at)->format('d-m-Y'),
                intval($row->pcs),
                number_format((float) $weight, 2, '.', ''),
                "KG",
                number_format((float) $rowTotal, 2, '.', '')
            ];
        }

        // Total Price
        $data[] = ["", "", "", "", "", "", "", "", ""]; // 空行
        $data[] = ["", "", "", "", "", "", "", "TOTAL PRICE:", number_format((float) $totalPrice, 2, '.', '')];

        return new Collection($data);
    }



    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            8 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 4.3,
            'B' => 13.56,
            'C' => 9.78,
            'D' => 16.33,
            'E' => 11.89,
            'F' => 6.00,
            'G' => 8.78,
            'H' => 11.23,
            'I' => 14.00
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 设置左边距
                $sheet->getPageMargins()->setLeft(0.52);

                // 设置表头样式（第8行）
                $sheet->getRowDimension(8)->setRowHeight(22.90);
                $sheet->getStyle('A8:I8')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                            'color' => ['rgb' => '000000'],
                        ],
                        'bottom' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical' => 'center',
                    ],
                ]);

                // 合并 E1:F1 为 INVOICE 标题栏，并左对齐
                $sheet->mergeCells('E1:F1');
                $sheet->getStyle('E1')->getAlignment()->setHorizontal('left');

                // 设置数字格式（Weight => G列，Total RM => I列）
                $sheet->getStyle('G9:G1000')->getNumberFormat()->setFormatCode('0.00'); // Weight
                $sheet->getStyle('I9:I1000')->getNumberFormat()->setFormatCode('0.00'); // Total RM

                // 获取 TOTAL PRICE 所在行（最后一行）
                $totalRow = $event->sheet->getHighestRow();

                // 明确设置 TOTAL PRICE label 样式（H列）
                $sheet->getStyle("H{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
                ]);

                // 设置 TOTAL PRICE 数值样式（I列）
                $sheet->getStyle("I{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                ]);

                // 强制 TOTAL PRICE 的金额格式为 0.00
                $sheet->getStyle("I{$totalRow}")->getNumberFormat()->setFormatCode('0.00');

                // 所有数据列居中对齐（第9行起到 TOTAL 行）
                foreach (range('A', 'I') as $col) {
                    $sheet->getStyle("{$col}9:{$col}{$totalRow}")->getAlignment()->setHorizontal('center');
                }

                // ✅ 可选调试：查看 TOTAL PRICE 的值
                // \Log::info("H{$totalRow}: " . $sheet->getCell("H{$totalRow}")->getValue());
                // \Log::info("I{$totalRow}: " . $sheet->getCell("I{$totalRow}")->getValue());
            }
        ];
    }
}











// namespace App\Exports;

// use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
// use Maatwebsite\Excel\Concerns\WithHeadings;
// use Maatwebsite\Excel\Concerns\WithStyles;
// use Maatwebsite\Excel\Concerns\WithColumnWidths;
// use Maatwebsite\Excel\Concerns\FromCollection;
// use Maatwebsite\Excel\Concerns\WithEvents;
// use Maatwebsite\Excel\Events\AfterSheet;
// use Illuminate\Support\Collection;
// use App\Models\ManifestList;
// use Illuminate\Support\Facades\DB;

// class ManifestExcelExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithEvents
// {
//     protected $consignorId;

//     public function __construct($consignorId)
//     {
//         $this->consignorId = $consignorId;
//     }

//     public function collection()
//     {
//         $data = [];
//         $manifestData = DB::table('manifest_lists')
//             ->where('consignor_id', 1) // 你可以传入 consignor_id
//             ->get();

//         $pageSize = 47; // 每页 47 行后换页
//         $counter = 1;

//         foreach ($manifestData as $index => $row) {
//             // **每页第一个数据前，插入表头**
//             if ($index % $pageSize == 0) {
//                 $data[] = ["", "", "INVOICE", "", "No.", ":"];
//                 $data[] = ["COMPANY A", "", "", "", "Your Ref.", ":"];
//                 $data[] = ["", "", "", "", "Our D/O No.", ":"];
//                 $data[] = ["CLIENT / DESTINATION", "", "", "", "Terms", ":"];
//                 $data[] = ["", "", "", "", "Date", ":"];
//                 $data[] = ["TEL : ____________", "", "FAX : ____________", "", "Page", ":"];
//                 $data[] = [""];
//                 $data[] = ["Item", "Description", "Consignment Note", "Delivery Date", "Qty", "Total RM"];
//             }

//             // **数据**
//             $data[] = [
//                 $counter++, // Item 递增
//                 "DCN " . $row->origin . " - " . $row->destination, // Description
//                 $row->cn_no, // Consignment Note
//                 date('d-m-Y', strtotime($row->created_at)), // Delivery Date (只要日期)
//                 $row->pcs, // Qty
//                 $row->total_price // Total RM
//             ];
//         }

//         return new Collection($data);
//     }

//     public function headings(): array
//     {
//         return [];
//     }

//     public function styles(Worksheet $sheet)
//     {
//         return [
//             1  => ['font' => ['bold' => true, 'size' => 14]], // INVOICE 标题加粗
//             8 => ['font' => ['bold' => true]], // 表头加粗
//         ];
//     }

//     public function columnWidths(): array
//     {
//         return [
//             'A' => 7.56,
//             'B' => 18.00,
//             'C' => 24.44,
//             'D' => 17.78,
//             'E' => 13.78,
//             'F' => 15.44
//         ];
//     }

//     public function registerEvents(): array
//     {
//         return [
//             AfterSheet::class => function (AfterSheet $event) {
//                 $sheet = $event->sheet->getDelegate();

//                 // 合并单元格
//                 $sheet->mergeCells('C1:D1'); // INVOICE 居中

//                 // 设定对齐方式
//                 $sheet->getStyle('C1:D1')->applyFromArray([
//                     'font' => [
//                         'bold' => true,
//                         'size' => 14,
//                         'color' => ['rgb' => '000000'],
//                     ],
//                     'alignment' => [
//                         'horizontal' => 'center',
//                         'vertical' => 'center',
//                     ],
//                 ]);

//                 $sheet->getStyle('E1')->getAlignment()->setHorizontal('right'); // 只让 E1 右对齐
//                 $sheet->getStyle('E2:F6')->getAlignment()->setHorizontal('left'); // 让 E2 到 F6 左对齐
//                 $sheet->getStyle('A8:F8')->applyFromArray([
//                     'alignment' => [
//                         'horizontal' => 'center',
//                         'vertical' => 'center',
//                     ],
//                 ]);
//                 $sheet->getStyle('A9:A1000')->getAlignment()->setHorizontal('center'); // Item 居中
//                 $sheet->getStyle('B9:B1000')->getAlignment()->setHorizontal('center');   // Description 左对齐
//                 $sheet->getStyle('C9:C1000')->getAlignment()->setHorizontal('left'); // Consignment Note 居中
//                 $sheet->getStyle('D9:D1000')->getAlignment()->setHorizontal('center'); // Delivery Date 居中
//                 $sheet->getStyle('E9:E1000')->getAlignment()->setHorizontal('center'); // Qty 居中
//                 $sheet->getStyle('F9:F1000')->getAlignment()->setHorizontal('center');  // Total RM 右对齐
//             }
//         ];
//     }
// }
