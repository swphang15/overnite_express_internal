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

    public function __construct($consignorId)
    {
        $this->consignorId = $consignorId;
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

        $consignor = Client::find($this->consignorId);
        $consignorName = $consignor ? $consignor->name : "UNKNOWN";

        $manifestData = DB::table('manifest_lists')
            ->where('consignor_id', $this->consignorId)
            ->get();

        $exportNo = $this->generateExportNo();
        $exportDate = Carbon::now()->format('d-m-Y');

        $counter = 1;

        $data[] = ["", "",  "INVOICE", "", "", "No.", ":$exportNo"];
        $data[] = [$consignorName, "", "", "", "", "Your Ref.", ":"];
        $data[] = ["", "", "", "", "", "Our D/O No.", ":"];
        $data[] = ["CLIENT / DESTINATION", "", "", "", "", "Terms", ":"];
        $data[] = ["", "", "", "", "", "Date", ":$exportDate"];
        $data[] = ["TEL : ____________", "", "FAX : ____________", "", "", "Page", ":"];
        $data[] = [""];
        $data[] = ["Item", "Description", "Consignment Note", "Delivery Date", "Qty", "UOM", "Total RM"];

        $totalPrice = 0;
        foreach ($manifestData as $row) {
            // 确保 total_price 存在且是数字
            $rowTotal = is_numeric($row->total_price) ? floatval($row->total_price) : 0;
            $totalPrice += $rowTotal; // 计算总和

            $data[] = [
                $counter++, // Item 递增
                "DCN " . $row->origin . " - " . $row->destination, // Description
                $row->cn_no, // Consignment Note
                date('d-m-Y', strtotime($row->created_at)), // Delivery Date
                $row->pcs, // Qty
                "KG", // UOM
                number_format($rowTotal, 2) // Total RM
            ];
        }

        // **添加 Total Price 行**
        $totalRowIndex = count($data) + 1; // 获取总价所在行索引
        $data[] = ["", "", "", "", "TOTAL PRICE:", number_format($totalPrice, 2)];
        return new Collection($data);
    }

    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1  => ['font' => ['bold' => true, 'size' => 14]],
            8  => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5.56,
            'B' => 16.44,
            'C' => 21.00,
            'D' => 13.44,
            'E' => 13.67,
            'F' => 10.78,
            'G' => 14.22
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getRowDimension(8)->setRowHeight(22.90);
                $sheet->getStyle('A8:G8')->applyFromArray([
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
                ]);


                $sheet->mergeCells('C1:D1');
                $sheet->mergeCells('F' . ($event->sheet->getHighestRow()) . ':G' . ($event->sheet->getHighestRow()));

                $sheet->getStyle('C1:D1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                        'color' => ['rgb' => '000000'],
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical' => 'center',
                    ],
                ]);

                $sheet->getStyle('A8:G8')->applyFromArray([
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical' => 'center',
                    ],
                ]);

                $totalRow = $event->sheet->getHighestRow();
                $sheet->getStyle('F' . $totalRow . ':G' . $totalRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
                ]);

                $sheet->getStyle('A9:A1000')->getAlignment()->setHorizontal('center'); // Item 居中
                $sheet->getStyle('B9:B1000')->getAlignment()->setHorizontal('center');   // Description 左对齐
                $sheet->getStyle('C9:C1000')->getAlignment()->setHorizontal('center'); // Consignment Note 居中
                $sheet->getStyle('D9:D1000')->getAlignment()->setHorizontal('center'); // Delivery Date 居中
                $sheet->getStyle('E9:E1000')->getAlignment()->setHorizontal('center'); // Qty 居中
                $sheet->getStyle('F9:F1000')->getAlignment()->setHorizontal('center');  // UOM 右对齐
                $sheet->getStyle('G9:G1000')->getAlignment()->setHorizontal('center');  // UOM 右对齐
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
