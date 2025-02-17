<?php

namespace App\Http\Controllers;

use App\Models\Manifest;
use App\Models\Company;
use App\Models\ShippingRate;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ManifestController extends Controller
{
    // 获取所有 manifests，并加载公司信息
    public function index()
    {
        $manifests = Manifest::with(['consignor', 'consignee'])->get();
        return response()->json($manifests);
    }

    // 存储新的 manifest 记录（不记录 delivery_date）
    public function store(Request $request)
    {
        $request->validate([
            'origin' => 'required|string',
            'consignor' => 'required|string',
            'consignee' => 'required|string',
            'cn_no' => 'required|integer',
            'pcs' => 'required|integer',
            'kg' => 'required|integer',
            'gram' => 'required|integer',
            'remarks' => 'nullable|string',
            'date' => 'required|date',
            'awb_no' => 'required|integer',
            'to' => 'required|string',
            'from' => 'required|string',
            'flt' => 'required|string',
            'manifest_no' => 'required|integer',
        ]);

        // 查找或创建 Consignor & Consignee
        $consignor = Company::firstOrCreate(['name' => $request->input('consignor')]);
        $consignee = Company::firstOrCreate(['name' => $request->input('consignee')]);

        // 获取运费规则
        $kg = $request->kg;
        $origin = $request->from;
        $destination = $request->to;

        $shippingRate = ShippingRate::where('origin', $origin)
            ->where('destination', $destination)
            ->first();

        if (!$shippingRate) {
            return response()->json(['error' => 'Shipping rate not found for this route'], 400);
        }

        // 计算 total_price
        if ($kg <= $shippingRate->minimum_weight) {
            $total_price = $shippingRate->minimum_price;
        } else {
            $extra_kg = $kg - $shippingRate->minimum_weight;
            $total_price = $shippingRate->minimum_price + ($extra_kg * $shippingRate->additional_price_per_kg);
        }

        // 创建 Manifest（不包含 delivery_date）
        $manifest = Manifest::create([
            'origin' => $request->input('origin'),
            'consignor_id' => $consignor->id,
            'consignee_id' => $consignee->id,
            'cn_no' => $request->input('cn_no'),
            'pcs' => $request->input('pcs'),
            'kg' => $request->input('kg'),
            'gram' => $request->input('gram'),
            'remarks' => $request->input('remarks'),
            'date' => $request->input('date'),
            'awb_no' => $request->input('awb_no'),
            'to' => $request->input('to'),
            'from' => $request->input('from'),
            'flt' => $request->input('flt'),
            'manifest_no' => $request->input('manifest_no'),
            'total_price' => $total_price, 
            'delivery_date' => null, // ❌ 初始为空，等发货后才更新
        ]);

        return response()->json($manifest->load(['consignor', 'consignee']), 201);
    }

    // 订单确认发货（更新 delivery_date）
    public function confirmShipment($id, Request $request)
    {
        $manifest = Manifest::findOrFail($id);

        if ($manifest->delivery_date) {
            return response()->json(['error' => 'Shipment already confirmed'], 400);
        }

        $deliveryDate = $request->input('delivery_date') ?: Carbon::now()->toDateString();

        $manifest->update(['delivery_date' => $deliveryDate]);

        return response()->json([
            'message' => 'Shipment confirmed successfully',
            'manifest' => $manifest
        ]);
    }

    // 获取单个 manifest
    public function show($id)
    {
        $manifest = Manifest::with(['consignor', 'consignee'])->findOrFail($id);
        return response()->json($manifest);
    }

    // 更新 manifest（不允许直接修改 delivery_date）
    public function update(Request $request, $id)
    {
        $manifest = Manifest::findOrFail($id);

        $request->validate([
            'origin' => 'sometimes|string',
            'consignor' => 'sometimes|string',
            'consignee' => 'sometimes|string',
            'cn_no' => 'sometimes|integer',
            'pcs' => 'sometimes|integer',
            'kg' => 'sometimes|integer',
            'gram' => 'sometimes|integer',
            'remarks' => 'nullable|string',
            'date' => 'sometimes|date',
            'awb_no' => 'sometimes|integer',
            'to' => 'sometimes|string',
            'from' => 'sometimes|string',
            'flt' => 'sometimes|string',
            'manifest_no' => 'sometimes|integer',
        ]);

        if ($request->has('consignor')) {
            $consignor = Company::firstOrCreate(['name' => $request->input('consignor')]);
            $manifest->consignor_id = $consignor->id;
        }

        if ($request->has('consignee')) {
            $consignee = Company::firstOrCreate(['name' => $request->input('consignee')]);
            $manifest->consignee_id = $consignee->id;
        }

        // 重新计算 total_price（如果 kg 变了）
        if ($request->has('kg')) {
            $kg = $request->kg;
            $origin = $request->from ?? $manifest->from;
            $destination = $request->to ?? $manifest->to;

            $shippingRate = ShippingRate::where('origin', $origin)
                ->where('destination', $destination)
                ->first();

            if ($shippingRate) {
                if ($kg <= $shippingRate->minimum_weight) {
                    $total_price = $shippingRate->minimum_price;
                } else {
                    $extra_kg = $kg - $shippingRate->minimum_weight;
                    $total_price = $shippingRate->minimum_price + ($extra_kg * $shippingRate->additional_price_per_kg);
                }
                $manifest->total_price = $total_price;
            }
        }

        $manifest->update($request->except(['consignor', 'consignee', 'delivery_date']));

        return response()->json($manifest->load(['consignor', 'consignee']));
    }

    // 删除 manifest
    public function destroy($id)
    {
        Manifest::destroy($id);
        return response()->json(null, 204);
    }
}
