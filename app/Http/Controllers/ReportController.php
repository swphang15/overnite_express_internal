<?php

namespace App\Http\Controllers;


use App\Models\LogisticRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportsExport;

class ReportController extends Controller
{
    public function exportPdf()
{
    $data = LogisticRecord::all(); // 获取所有物流记录
    $pdf = Pdf::loadView('pdf.logistics_report', compact('data')); // 载入 Blade 视图
    return $pdf->download('logistics_report.pdf'); // 生成 PDF
}
public function exportExcel()
{
    return Excel::download(new ReportsExport, 'logistics_report.xlsx');
}


}
