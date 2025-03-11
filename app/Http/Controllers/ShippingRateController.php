<?php

namespace App\Http\Controllers;

use App\Models\ShippingRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
class ShippingRateController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:sanctum'); // 保护 API，所有方法都需要 Token 认证
    }

    // 获取所有 Shipping Rates
    public function index()
    {
        return response()->json(ShippingRate::all(), 200);
    }

    // 创建新的 Shipping Rate
    public function store(Request $request)
    {
        $request->validate([
            'shipping_plan_id' => 'required|exists:shipping_plans,id',
            'origin' => 'required|string|max:3',
            'destination' => 'required|string|max:3',
            'minimum_price' => 'required|numeric|min:0',
            'minimum_weight' => 'required|integer|min:0',
            'additional_price_per_kg' => 'required|numeric|min:0',
        ]);

        // 检查是否已有相同 origin 和 destination 的记录
        $exists = ShippingRate::where('origin', $request->origin)
            ->where('destination', $request->destination)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This shipping route already exists.'], 422);
        }

        // 创建新记录
        $rate = ShippingRate::create($request->all());

        return response()->json($rate, 201);
    }

    // 获取单个 Shipping Rate
    public function show($id)
    {
        $rate = ShippingRate::find($id);
        if (!$rate) {
            return response()->json(['message' => 'Shipping Rate not found'], 404);
        }

        return response()->json($rate, 200);
    }

    // 更新 Shipping Rate
    public function update(Request $request, $id)
    {
        $rate = ShippingRate::find($id);
        if (!$rate) {
            return response()->json(['message' => 'Shipping Rate not found'], 404);
        }

        $request->validate([
            'shipping_plan_id' => 'sometimes|exists:shipping_plans,id',
            'origin' => 'sometimes|string|max:3',
            'destination' => 'sometimes|string|max:3',
            'minimum_price' => 'sometimes|numeric|min:0',
            'minimum_weight' => 'sometimes|integer|min:0',
            'additional_price_per_kg' => 'sometimes|numeric|min:0',
        ]);

        // 检查是否有相同的 origin 和 destination，但排除当前 id
        $exists = ShippingRate::where('origin', $request->origin)
            ->where('destination', $request->destination)
            ->where('id', '!=', $id) // 排除当前记录
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This shipping route already exists.'], 422);
        }

        $rate->update($request->all());

        return response()->json($rate, 200);
    }

    // 软删除 Shipping Rate
    public function destroy($id)
    {
        $rate = ShippingRate::find($id);
        if (!$rate) {
            return response()->json(['message' => 'Shipping Rate not found'], 404);
        }

        $rate->delete();
        return response()->json(['message' => 'Shipping Rate deleted'], 200);
    }

    // 获取被软删除的 Shipping Rates
    public function trashed()
    {
        return response()->json(ShippingRate::onlyTrashed()->get(), 200);
    }

    // 恢复被软删除的 Shipping Rate
    public function restore($id)
    {
        $rate = ShippingRate::onlyTrashed()->find($id);
        if (!$rate) {
            return response()->json(['message' => 'Shipping Rate not found in trash'], 404);
        }

        $rate->restore();
        return response()->json(['message' => 'Shipping Rate restored'], 200);
    }

    // 获取唯一的 origin 和 destination 组合
    public function OAD()
    {
        $rates = ShippingRate::select('origin', 'destination')->get();
        Log::info($rates);

        // 归类数据，将相同 origin 的 destination 合并为数组
        $grouped = $rates->groupBy('origin')->map(function ($items) {
            return [
                'origin' => $items->first()->origin,
                'destinations' => $items->pluck('destination')->unique()->implode(', ')
            ];
        });

        return response()->json([
            'origin' => $grouped->keys()->implode(', '),
            'destinations' => $grouped->pluck('destinations')->implode(', ')
        ], 200);
    }
}
