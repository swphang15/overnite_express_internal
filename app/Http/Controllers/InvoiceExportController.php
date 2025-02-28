<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exports\ManifestExport;
use App\Models\ShippingRate;
use App\Models\Manifest;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;


class InvoiceExportController extends Controller
{
    // 导出 Excel
    public function exportExcel(Request $request)
    {
        // 从请求获取 manifest ID 数组
        $manifestIds = $request->input('manifest_ids');
    
        // 查询所有匹配的 manifest
        $manifests = Manifest::whereIn('id', $manifestIds)->get();
        
    
        if ($manifests->isEmpty()) {
            return response()->json(['error' => '未找到匹配的 Manifest'], 404);
        }
    
        // 生成 Excel
        return Excel::download(new ManifestExport($manifests), 'manifests.xlsx');
    }
    

    // 生成 PDF
    public function exportPdf(Request $request)
    {
        // 从请求中获取 manifest ID 列表（可以是多个）
        $manifestIds = $request->input('manifest_ids');

        // 查询所有匹配的 manifest 数据
        $manifests = Manifest::whereIn('id', $manifestIds)->get();

        // 遍历每个 manifest 计算价格
        foreach ($manifests as $manifest) {
            $shippingRate = ShippingRate::where('origin', $manifest->from)
                ->where('destination', $manifest->to)
                ->first();

            $manifest->price_per_kg = $shippingRate ? $shippingRate->additional_price_per_kg : 0;
        }

        // 生成 PDF
        $pdf = Pdf::loadView('manifest_pdf', compact('manifests'));

        // 下载 PDF
        return $pdf->download('manifest.pdf');
    }
}
