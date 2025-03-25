<?php

namespace App\Exports;

use Barryvdh\DomPDF\Facade\Pdf;

use App\Models\ManifestInfo;
use App\Models\ManifestList;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

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
        // 1️⃣ 获取 Manifest 数据
        $manifestInfo = ManifestInfo::findOrFail($manifestId);
        $manifestLists = ManifestList::where('manifest_info_id', $manifestInfo->id)->get();

        // 2️⃣ 生成 PDF
        $pdf = Pdf::loadView('pdf.manifest', compact('manifestInfo', 'manifestLists'))->setPaper('A4', 'portrait');

        return $pdf->download("Manifest_{$manifestInfo->id}.pdf");
    }



    public function view(): View
    {
        // ✅ 按 `consignor_id` 查询 Manifest List 数据
        $manifestLists = ManifestList::where('consignor_id', $this->consignor_id)->get();

        return view('excel.manifest', [
            'consignor_id' => $this->consignor_id,
            'manifestLists' => $manifestLists,
        ]);
    }
}
