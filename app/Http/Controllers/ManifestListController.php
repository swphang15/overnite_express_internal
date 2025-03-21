<?php

namespace App\Http\Controllers;

use App\Models\ManifestList;
use Illuminate\Http\Request;

class ManifestListController extends Controller
{
    // 获取所有清单列表
    public function index()
    {
        return response()->json(ManifestList::with('manifestInfo', 'consignor')->get(), 200);
    }

    // 创建新的清单记录
    public function store(Request $request)
    {
        $request->validate([
            'manifest_info_id' => 'required|exists:manifest_info,id',
            'consignor_id' => 'required|exists:clients,id',
            'consignee_name' => 'required|string',
            'cn_no' => 'required|string|unique:manifest_list',
            'pcs' => 'required|integer|min:1',
            'kg' => 'required|numeric|min:0', // 允许小数
            'total_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'origin' => 'required|string',
            'remarks' => 'nullable|string',
        ]);

        // 拆分 kg
        $fullKg = floor($request->kg); // 取整数部分
        $grams = ($request->kg - $fullKg) * 1000; // 计算克数

        $manifestList = ManifestList::create([
            'manifest_info_id' => $request->manifest_info_id,
            'consignor_id' => $request->consignor_id,
            'consignee_name' => $request->consignee_name,
            'cn_no' => $request->cn_no,
            'pcs' => $request->pcs,
            'kg' => $fullKg,
            'gram' => $grams,
            'total_price' => $request->total_price,
            'discount' => $request->discount,
            'origin' => $request->origin,
            'remarks' => $request->remarks
        ]);

        return response()->json($manifestList, 201);
    }


    // 获取单个清单项
    public function show($id)
    {
        return response()->json(ManifestList::with('manifestInfo', 'consignor')->findOrFail($id), 200);
    }

    // 更新清单项
    public function update(Request $request, $id)
    {
        $manifestList = ManifestList::findOrFail($id);
        $manifestList->update($request->all());

        return response()->json($manifestList, 200);
    }

    // 删除清单项
    public function destroy($id)
    {
        ManifestList::destroy($id);
        return response()->json(['message' => 'Deleted successfully'], 200);
    }
}
