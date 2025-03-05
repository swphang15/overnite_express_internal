<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Manifest;
use App\Models\ShippingRate;
use App\Http\Controllers\Controller;
class ShippingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum'); // 所有方法都需要 Token
    }
    // 获取所有运费
  // 获取唯一的 origin
public function getUniqueOrigins()
{
    $uniqueOrigins = ShippingRate::distinct()->pluck('origin');
    return response()->json($uniqueOrigins);
}

// 获取唯一的 destination
public function getUniqueDestinations()
{
    $uniqueDestinations = ShippingRate::distinct()->pluck('destination');
    return response()->json($uniqueDestinations);
}


    public function index()
    {
        return response()->json(ShippingRate::all());
    }

    // 存储新的运费
    public function store(Request $request)
{
    $validated = $request->validate([
        'origin' => 'required|string|max:3',
        'destination' => 'required|string|max:3',
        'minimum_price' => 'required|numeric|min:0',
        'minimum_weight' => 'required|numeric|min:0',
        'additional_price_per_kg' => 'required|numeric|min:0',
    ]);

    // 检查是否已有相同的 origin 和 destination
    $exists = ShippingRate::where('origin', $validated['origin'])
                          ->where('destination', $validated['destination'])
                          ->exists();

    if ($exists) {
        return response()->json(['error' => 'Shipping rate for this route already exists'], 400);
    }

    $rate = ShippingRate::create($validated);

    return response()->json($rate, 201);
}


    // 计算运费
    public function calculateShipping(Request $request)
    {
        // 获取 Manifest 记录（假设是通过 `id` 获取）
        $manifest = Manifest::find($request->input('id'));

        if (!$manifest) {
            return response()->json(['error' => 'Manifest not found'], 404);
        }

        // 获取运费数据
        $rate = ShippingRate::where('origin', $manifest->from)
            ->where('destination', $manifest->to)
            ->first();

        if (!$rate) {
            return response()->json(['error' => 'Shipping rate not found'], 404);
        }

        // 计算总重量
        $total_kg = $manifest->kg + ($manifest->gram / 1000);

        // 计算运费
        if ($total_kg <= $rate->minimum_weight) {
            $total_price = $rate->minimum_price;
        } else {
            $extra_kg = $total_kg - $rate->minimum_weight;
            $total_price = $rate->minimum_price + ($extra_kg * $rate->additional_price_per_kg);
        }

        return response()->json([
            'manifest_id' => $manifest->id,
            'origin' => $manifest->from,
            'destination' => $manifest->to,
            'total_kg' => $total_kg,
            'total_price' => round($total_price, 2) . ' MYR',
        ]);
    }
    public function update(Request $request, $id)
{
    $validated = $request->validate([
        'origin' => 'required|string|max:3',
        'destination' => 'required|string|max:3',
        'minimum_price' => 'required|numeric|min:0',
        'minimum_weight' => 'required|numeric|min:0',
        'additional_price_per_kg' => 'required|numeric|min:0',
    ]);

    $rate = ShippingRate::find($id);

    if (!$rate) {
        return response()->json(['error' => 'Shipping rate not found'], 404);
    }

    // 确保新的 origin 和 destination 不会重复已有的记录（但允许更新当前记录）
    $exists = ShippingRate::where('origin', $validated['origin'])
                          ->where('destination', $validated['destination'])
                          ->where('id', '!=', $id)
                          ->exists();

    if ($exists) {
        return response()->json(['error' => 'Shipping rate for this route already exists'], 400);
    }

    $rate->update($validated);

    return response()->json($rate);
}
public function destroy($id)
{
    $rate = ShippingRate::find($id);

    if (!$rate) {
        return response()->json(['error' => 'Shipping rate not found'], 404);
    }

    $rate->delete();

    return response()->json(['message' => 'Shipping rate deleted successfully']);
}


}
