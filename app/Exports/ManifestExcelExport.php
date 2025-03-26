<?php

namespace App\Exports;

use App\Models\ManifestList;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;

class ManifestExcelExport implements FromCollection, WithHeadings, WithStyles
{
    protected $consignorId;

    public function __construct($consignorId)
    {
        $this->consignorId = $consignorId;
    }

    public function collection()
    {
        return ManifestList::where('consignor_id', $this->consignorId)
            ->select(DB::raw('
                @rownum := @rownum + 1 AS item,
                "DCN KCH - KUL" AS description,
                cn_no AS consignment_note,
                DATE_FORMAT(created_at, "%d/%m/%Y") AS delivery_date,
                pcs AS qty,
                total_price AS total_rm
            '))
            ->crossJoin(DB::raw('(SELECT @rownum := 0) AS r'))
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        $totalPrice = ManifestList::where('consignor_id', $this->consignorId)->sum('total_price');

        return [
            ['', '', 'INVOICE', '', 'No. : I-2411-13'], // 第一行
            ['COMPANY A', '', '', '', 'Your Ref. :'], // 第二行
            ['CLIENT / DESTINATION', '', '', '', 'Our D/O No. :'], // 第三行
            ['TEL : ___________    FAX : ___________', '', '', '', 'Terms : C.O.D'], // 第四行
            ['', '', '', '', 'Date : (latest date)'], // 第五行
            ['', '', '', '', 'Page : 1 of 6'], // 第六行
            ['', '', '', '', ''], // 空行
            ['Item', 'Description', 'Consignment Note', 'Delivery Date', 'Qty', 'Total RM'], // **表头**
            ['', '', '', '', '', 'Total Price: RM ' . number_format($totalPrice, 2)] // **最后一行总价**
        ];
    }

    public function map($row): array
    {
        return [
            $row->item,
            $row->description,
            $row->consignment_note,
            $row->delivery_date,
            $row->qty,
            number_format($row->total_rm, 2),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // **合并单元格**
        $sheet->mergeCells('C1:D1'); // `INVOICE`
        $sheet->mergeCells('E1:F1'); // `No. :`
        $sheet->mergeCells('A2:B2'); // `COMPANY A`
        $sheet->mergeCells('A3:B3'); // `CLIENT / DESTINATION`
        $sheet->mergeCells('A4:C4'); // `TEL : ___    FAX : ___`
        $sheet->mergeCells('E4:F4'); // `Terms : C.O.D`
        $sheet->mergeCells('E5:F5'); // `Date :`
        $sheet->mergeCells('E6:F6'); // `Page :`
        $sheet->mergeCells('A8:F8'); // 空行
        $sheet->mergeCells('A9:F9'); // **表头**
        $sheet->mergeCells('A' . ($sheet->getHighestRow() + 1) . ':E' . ($sheet->getHighestRow() + 1)); // **总价行**

        // **设置列宽**
        $sheet->getColumnDimension('A')->setWidth(10.71); // 75px
        $sheet->getColumnDimension('B')->setWidth(24.71); // 169px
        $sheet->getColumnDimension('C')->setWidth(33.86); // 227px
        $sheet->getColumnDimension('D')->setWidth(24.86); // 167px
        $sheet->getColumnDimension('E')->setWidth(19.43); // 130px
        $sheet->getColumnDimension('F')->setWidth(21.71); // 146px

        // **设置样式**
        return [
            1 => ['font' => ['bold' => true, 'size' => 14], 'alignment' => ['horizontal' => 'center']], // `INVOICE`
            2 => ['font' => ['bold' => true, 'size' => 12]], // `COMPANY A`
            9 => ['font' => ['bold' => true], 'alignment' => ['horizontal' => 'center']], // 表头
            'A' => ['alignment' => ['horizontal' => 'center']], // `Item` 列居中
            'C' => ['alignment' => ['horizontal' => 'center']], // `Consignment Note` 居中
            'E' => ['alignment' => ['horizontal' => 'center']], // `Qty` 列居中
            'F' => ['alignment' => ['horizontal' => 'right']], // `Total RM` 右对齐
        ];
    }
}
