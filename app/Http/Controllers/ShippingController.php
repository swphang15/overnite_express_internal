<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Manifest;
use App\Models\ShippingPlan;
use App\Models\ShippingRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\Client;


class ShippingController extends Controller
{
    public function duplicateShippingPlan($id)
    {
        DB::beginTransaction();

        try {
            // Step 1: 找到原本的 shipping plan
            $originalPlan = ShippingPlan::findOrFail($id);

            // Step 2: 生成不重复的 plan 名称
            $baseName = $originalPlan->plan_name . '-Duplicate';
            $newName = $baseName;

            $counter = 0;
            while (ShippingPlan::where('plan_name', $newName)->exists()) {
                $counter++;
                $newName = $baseName . "($counter)";
            }

            // Step 3: 创建新的 shipping plan
            $newPlan = $originalPlan->replicate();
            $newPlan->plan_name = $newName;
            $newPlan->push();

            // Step 4: 复制 shipping rates
            $originalRates = ShippingRate::where('shipping_plan_id', $originalPlan->id)->get();

            foreach ($originalRates as $rate) {
                $newRate = $rate->replicate();
                $newRate->shipping_plan_id = $newPlan->id;
                $newRate->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Shipping Plan duplicated successfully.',
                'new_plan_id' => $newPlan->id,
                'new_plan_name' => $newPlan->plan_name,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to duplicate shipping plan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function __construct()
    {
        $this->middleware('auth:sanctum'); // 所有方法都需要 Token
    }

    // // 获取唯一的 origin
    // public function getUniqueOrigins()
    // {
    //     $uniqueOrigins = ShippingRate::distinct()->pluck('origin');
    //     return response()->json($uniqueOrigins);
    // }

    // // 获取唯一的 destination
    // public function getUniqueDestinations()
    // {
    //     $uniqueDestinations = ShippingRate::distinct()->pluck('destination');
    //     return response()->json($uniqueDestinations);
    // }

    public function index()
    {
        $plans = ShippingPlan::with('shippingRates')->get();

        return response()->json($plans, 200);
    }
    public function show($id)
    {
        $shippingPlan = ShippingPlan::with('shippingRates')->find($id);

        if (!$shippingPlan) {
            return response()->json(['message' => 'Shipping plan not found'], 404);
        }

        return response()->json([
            'shipping_plan' => $shippingPlan
        ], 200);
    }

    // 存储新的运费
    public function store(Request $request)
    {
        // 验证请求数据


        $request->validate([
            'shipping_plan_id' => 'nullable|exists:shipping_plans,id',
            'plan_name' => [
                'required_without:shipping_plan_id',
                'string',
                'max:255',
                Rule::unique('shipping_plans', 'plan_name')->whereNull('deleted_at'),
            ],
            'shipping_rates' => 'required|array|min:1',
            'shipping_rates.*.origin' => 'required|string|max:3',
            'shipping_rates.*.destination' => 'required|string|max:3',
            'shipping_rates.*.minimum_price' => 'required|numeric|min:0',
            'shipping_rates.*.minimum_weight' => 'required|numeric|min:0',
            'shipping_rates.*.additional_price_per_kg' => 'required|numeric|min:0',
            'shipping_rates.*.misc_charge' => 'required|numeric|min:0',
            'shipping_rates.*.fuel_surcharge' => 'required|numeric|min:0',
        ]);


        DB::beginTransaction(); // 开启事务

        try {
            if ($request->filled('shipping_plan_id')) {
                // **如果提供了 plan_id，查找已有 plan**
                $plan = ShippingPlan::find($request->shipping_plan_id);
            } else {
                // **否则，创建新的 ShippingPlan**
                $plan = ShippingPlan::create([
                    'plan_name' => $request->plan_name,
                ]);
            }

            // **遍历 ShippingRates 并创建**
            $rates = [];
            foreach ($request->shipping_rates as $rateData) {
                // **检查是否在相同 ShippingPlan 内部重复**
                $exists = ShippingRate::where('shipping_plan_id', $plan->id)
                    ->where('origin', $rateData['origin'])
                    ->where('destination', $rateData['destination'])
                    ->exists();

                if ($exists) {
                    DB::rollBack(); // 回滚事务
                    return response()->json(['message' => 'This shipping route already exists within this shipping plan.'], 422);
                }

                // **创建 ShippingRate**
                $rates[] = ShippingRate::create([
                    'shipping_plan_id' => $plan->id,
                    'origin' => $rateData['origin'],
                    'destination' => $rateData['destination'],
                    'minimum_price' => $rateData['minimum_price'],
                    'minimum_weight' => $rateData['minimum_weight'],
                    'additional_price_per_kg' => $rateData['additional_price_per_kg'],
                    'misc_charge' => $rateData['misc_charge'] ?? 0, // ✅ 加进去，默认值 0
                    'fuel_surcharge' => $rateData['fuel_surcharge'] ?? 0, // ✅ 加进去，默认值 0
                ]);
            }

            DB::commit(); // 提交事务

            return response()->json([
                'message' => 'Shipping plan and rates processed successfully',
                'shipping_plan' => $plan,
                'shipping_rates' => $rates,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack(); // 发生错误，回滚
            return response()->json([
                'message' => 'Failed to process shipping plan and rates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'plan_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('shipping_plans', 'plan_name')
                    ->ignore($id) // 忽略当前 plan 的 ID
                    ->whereNull('deleted_at'), // 只检查未软删除的
            ],
            'shipping_rates' => 'nullable|array',
            'shipping_rates.*.id' => 'nullable|exists:shipping_rates,id',
            'shipping_rates.*.origin' => 'required|string|max:3',
            'shipping_rates.*.destination' => 'required|string|max:3',
            'shipping_rates.*.minimum_price' => 'required|numeric|min:0',
            'shipping_rates.*.minimum_weight' => 'required|numeric|min:0',
            'shipping_rates.*.additional_price_per_kg' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction(); // 开启事务

        try {
            $shippingPlan = ShippingPlan::findOrFail($id);
            $shippingPlan->update(['plan_name' => $request->plan_name]);

            // 处理 shipping_rates
            if ($request->has('shipping_rates')) {
                foreach ($request->shipping_rates as $rateData) {
                    if (isset($rateData['id'])) {
                        // 更新已有的运费规则
                        $rate = $shippingPlan->shippingRates()->find($rateData['id']);
                        if ($rate) {
                            $rate->update($rateData);
                        }
                    } else {
                        // 创建新的运费规则
                        $shippingPlan->shippingRates()->create($rateData);
                    }
                }
            }

            DB::commit(); // 提交事务

            return response()->json([
                'message' => 'Shipping plan updated successfully',
                'shipping_plan' => $shippingPlan->load('shippingRates')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // 回滚事务
            return response()->json(['message' => 'Failed to update shipping plan', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteShippingPlan($id)
    {
        DB::beginTransaction();
        try {
            // 查找 Shipping Plan
            $plan = ShippingPlan::findOrFail($id);

            // 检查有没有 Client 使用这个 Plan
            $clientsUsingPlan = Client::where('shipping_plan_id', $id)->exists(); // 👈 重点

            if ($clientsUsingPlan) {
                // 有 client 用着，不能删除
                return response()->json(['message' => 'Cannot delete: Shipping plan is being used by a client'], 400);
            }

            // 没有被使用，可以安全删除
            $plan->shippingRates()->delete();
            $plan->delete();

            DB::commit();
            return response()->json(['message' => 'Shipping plan and all related rates deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete shipping plan', 'error' => $e->getMessage()], 500);
        }
    }


    public function deleteShippingRate($id)
    {
        DB::beginTransaction();
        try {
            // **查找 Shipping Rate**
            $rate = ShippingRate::findOrFail($id);

            // **获取它所属的 Shipping Plan**
            $plan = $rate->shippingPlan;

            // **删除 Shipping Rate**
            $rate->delete();

            DB::commit();
            return response()->json([
                'message' => 'Shipping rate deleted successfully',
                'shipping_plan_id' => $plan->id,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete shipping rate', 'error' => $e->getMessage()], 500);
        }
    }
}



//     // 计算运费
//     public function calculateShipping(Request $request)
//     {
//         // 获取 Manifest 记录（假设是通过 `id` 获取）
//         $manifest = Manifest::find($request->input('id'));

//         if (!$manifest) {
//             return response()->json(['error' => 'Manifest not found'], 404);
//         }

//         // 获取运费数据
//         $rate = ShippingRate::where('origin', $manifest->from)
//             ->where('destination', $manifest->to)
//             ->first();

//         if (!$rate) {
//             return response()->json(['error' => 'Shipping rate not found'], 404);
//         }

//         // 计算总重量
//         $total_kg = $manifest->kg + ($manifest->gram / 1000);

//         // 计算运费
//         if ($total_kg <= $rate->minimum_weight) {
//             $total_price = $rate->minimum_price;
//         } else {
//             $extra_kg = $total_kg - $rate->minimum_weight;
//             $total_price = $rate->minimum_price + ($extra_kg * $rate->additional_price_per_kg);
//         }

//         return response()->json([
//             'manifest_id' => $manifest->id,
//             'origin' => $manifest->from,
//             'destination' => $manifest->to,
//             'total_kg' => $total_kg,
//             'total_price' => round($total_price, 2) . ' MYR',
//         ]);
//     }

//     // 更新运费
//     public function update(Request $request, $id)
//     {
//         $validated = $request->validate([
//             'origin' => 'required|string|max:3',
//             'destination' => 'required|string|max:3',
//             'minimum_price' => 'required|numeric|min:0',
//             'minimum_weight' => 'required|numeric|min:0',
//             'additional_price_per_kg' => 'required|numeric|min:0',
//         ]);

//         $rate = ShippingRate::find($id);

//         if (!$rate) {
//             return response()->json(['error' => 'Shipping rate not found'], 404);
//         }

//         // 确保新的 origin 和 destination 不会重复已有的记录（但允许更新当前记录）
//         $exists = ShippingRate::where('origin', $validated['origin'])
//             ->where('destination', $validated['destination'])
//             ->where('id', '!=', $id)
//             ->exists();

//         if ($exists) {
//             return response()->json(['error' => 'Shipping rate for this route already exists'], 400);
//         }

//         $rate->update($validated);

//         return response()->json($rate);
//     }

//     // 删除运费
//     public function destroy($id)
//     {
//         $rate = ShippingRate::find($id);

//         if (!$rate) {
//             return response()->json(['error' => 'Shipping rate not found'], 404);
//         }

//         $rate->delete();

//         return response()->json(['message' => 'Shipping rate deleted successfully']);
//     }
// }
