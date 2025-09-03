<?php

namespace App\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\ManifestInfo;
use App\Models\ManifestList;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

class ManifestExport implements FromView
{
    protected $consignor_id;

    public function __construct($consignor_id)
    {
        $this->consignor_id = $consignor_id;
    }

    // ✅ 保持原来的 PDF 导出功能
    public function exportPdf($manifestId)
    {
        // 获取 Manifest 数据
        $manifestInfo = ManifestInfo::findOrFail($manifestId);
        // 按照 drag sort 的 sort_order 排序获取 manifestLists
        $manifestLists = ManifestList::where('manifest_info_id', $manifestInfo->id)
            ->orderBy('sort_order', 'asc')
            ->get();

        // 获取字段值
        $to = $manifestInfo->to;
        $date = $manifestInfo->date; // 假设 _date 是 Y-m-d 格式


        $formattedDate = \Carbon\Carbon::parse($date)->format('ymd'); // 输出 '250313'


        // 创建文件名
        $fileName = "{$to}_{$formattedDate}.pdf";

        // 生成 PDF
        $pdf = Pdf::loadView('pdf.manifest', compact('manifestInfo', 'manifestLists'))->setPaper('A4', 'portrait');

        // 返回带 Header 的 PDF 响应
        return response($pdf->download($fileName)->getOriginalContent(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }



    // ✅ 实现 FromView 接口的 view 方法
    public function view(): View
    {
        $manifestLists = ManifestList::where('consignor_id', $this->consignor_id)->get();
        return view('exports.manifest', [
            'manifestLists' => $manifestLists
        ]);
    }
}
