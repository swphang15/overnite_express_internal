<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Manifest; // 确保 Manifest 代替 Invoice
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\ManifestExport;

class InvoiceExportController extends Controller
{
    // 生成 PDF 并返回下载
    public function exportPDF($id)
{
    // 直接查询 Manifest，不使用 with('items')
    $manifest = Manifest::findOrFail($id);

    // 载入 PDF 视图并传递数据
    $pdf = Pdf::loadView('pdf.manifest', compact('manifest'));

    return $pdf->download("Manifest_{$manifest->id}.pdf");
}


    // 生成 Excel 并返回下载
    public function exportExcel($id)
{
    return Excel::download(new ManifestExport($id), "Manifest_{$id}.xlsx");
}

}
