<?php

namespace App\Http\Controllers;

use App\Models\ShippingRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Client;

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
            'minimum_weight' => 'required|numeric|min:0',
            'additional_price_per_kg' => 'required|numeric|min:0',
            'misc_charge' => 'required|numeric|min:0',
            'fuel_surcharge' => 'required|numeric|min:0',
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
    public function show($shipping_plan_id, $rate_id)
    {
        // 先找该 shipping_plan_id 下有没有这个 rate_id 的记录
        $rate = ShippingRate::where('shipping_plan_id', $shipping_plan_id)
            ->where('id', $rate_id)
            ->first();

        if (!$rate) {
            return response()->json(['message' => 'Shipping Rate not found under this Shipping Plan'], 404);
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
            'origin' => 'sometimes|string|max:3',
            'destination' => 'sometimes|string|max:3',
            'minimum_price' => 'sometimes|numeric|min:0',
            'minimum_weight' => 'sometimes|numeric|min:0',
            'additional_price_per_kg' => 'sometimes|numeric|min:0',
            'misc_charge' => 'sometimes|numeric|min:0', // ✅ 新增字段验证
            'fuel_surcharge' => 'required|numeric|min:0',
        ]);

        // 获取当前 shipping plan id
        $shippingPlanId = $rate->shipping_plan_id;

        // 如果前端传了 origin 或 destination，就检查是否冲突
        if ($request->has('origin') && $request->has('destination')) {
            $exists = ShippingRate::where('origin', $request->origin)
                ->where('destination', $request->destination)
                ->where('shipping_plan_id', $shippingPlanId)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'This shipping route already exists in this shipping plan.'], 422);
            }
        }

        $rate->update($request->except('shipping_plan_id'));

        return response()->json([
            'message' => 'Shipping Rate updated successfully.',
            'rate' => $rate,
            'shipping_plan_id' => $shippingPlanId,
        ], 200);
    }


    public function OAD(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
        ]);

        // ✅ 根据 client_id 获取 client，并加载它关联的 shippingPlan
        $client = Client::with('shippingPlan.shippingRates')->find($request->client_id);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        // ✅ 获取与该 client 关联的所有 shippingRates
        $rates = $client->shippingPlan->shippingRates;

        // ✅ 提取唯一的 origin
        $origins = collect($rates)->pluck('origin')->unique()->map(function ($origin) {
            return [
                'id' => (string) $origin,
                'name' => $origin
            ];
        })->values()->toArray();

        // ✅ 提取唯一的 destination
        $destinations = collect($rates)->pluck('destination')->unique()->map(function ($destination) {
            return [
                'id' => (string) $destination,
                'name' => $destination
            ];
        })->values()->toArray();

        return response()->json([
            'rates' => $rates->map(function ($rate) {
                return [
                    'id' => $rate->id,
                    'origin' => $rate->origin,
                    'destination' => $rate->destination,
                    'misc_charge' => $rate->misc_charge,
                    'fuel_surcharge' => $rate->fuel_surcharge,
                ];
            }),
            'origin' => $origins,
            'destinations' => $destinations
        ]);
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

}
