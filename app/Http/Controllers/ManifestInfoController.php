<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\ShippingRate;
use App\Models\ManifestInfo;
use App\Models\ManifestList;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use Exception;
class ManifestInfoController extends Controller
    // 获取所有清单记录
    
    {
        /**
         * 获取虚拟的 total_price 让前端确认
         */
        public function getEstimatedTotalPrice(Request $request)
{
    try {
        // 验证输入
        $validatedData = $request->validate([
            'origin' => 'required|string',  // ✅ 把 from 改成 origin
            'destination' => 'required|string', // ✅ 把 to 改成 destination
            'consignor_id' => 'required|exists:clients,id',
            'kg' => 'required|numeric|min:0',
        ]);

        // 计算价格
        $totalPrice = $this->calculateTotalPrice(
            $validatedData['origin'],      // ✅ 这里用 origin 代替 from
            $validatedData['destination'], // ✅ 这里用 destination 代替 to
            $validatedData['consignor_id'],
            $validatedData['kg']
        );

        return response()->json([
            'estimated_total_price' => $totalPrice
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (Exception $e) {
        return response()->json([
            'message' => 'Error calculating estimated total price',
            'error' => $e->getMessage()
        ], 500);
    }
}

    
        /**
         * 创建 Manifest 并存入数据库
         */
       
// 处理创建整个 ManifestInfo + ManifestList
public function store(Request $request)
{
    return $this->handleManifest($request);
}

// 处理追加 ManifestList 到现有 ManifestInfo
public function addLists(Request $request, $id)
{
    $request->merge(['manifest_info_id' => $id]); // 设置 manifest_info_id
    return $this->handleManifest($request);
}

// 处理逻辑复用
private function handleManifest(Request $request)
{
    try {
        DB::beginTransaction();

        $validatedData = $request->validate([
            'manifest_info_id' => 'nullable|exists:manifest_infos,id',
            'date' => 'required_without:manifest_info_id|date',
            'awb_no' => 'required_without:manifest_info_id|string|unique:manifest_infos,awb_no',
            'to' => 'required_without:manifest_info_id|string',
            'from' => 'required_without:manifest_info_id|string',
            'flt' => 'nullable|string',
            'manifest_lists' => 'required|array|min:1',
            'manifest_lists.*.consignor_id' => 'required|exists:clients,id',
            'manifest_lists.*.consignee_name' => 'required|string',
            'manifest_lists.*.cn_no' => 'required|numeric',
            'manifest_lists.*.pcs' => 'required|integer|min:1',
            'manifest_lists.*.kg' => 'required|numeric|min:0',
            'manifest_lists.*.discount' => 'sometimes|nullable|numeric|min:0',
            'manifest_lists.*.origin' => 'required|string',
            'manifest_lists.*.remarks' => 'nullable|string',
        ]);

        // ✅ 获取当前用户 ID
        $userId = auth()->id(); 

        // ✅ 获取最大 Manifest No
        $maxManifestNo = ManifestInfo::withTrashed()->max('manifest_no');
        $nextManifestNo = $this->getNextManifestNo($maxManifestNo);

        if (!isset($validatedData['manifest_info_id'])) {
            // ✅ 创建新的 ManifestInfo 并记录 `user_id`
            $manifestInfo = ManifestInfo::create([
                'date' => $validatedData['date'],
                'awb_no' => $validatedData['awb_no'],
                'to' => $validatedData['to'],
                'from' => $validatedData['from'],
                'flt' => $validatedData['flt'],
                'manifest_no' => $nextManifestNo,
                'user_id' => $userId, // ✅ 记录当前用户 ID
            ]);
        } else {
            // ✅ 追加 ManifestList
            $manifestInfo = ManifestInfo::findOrFail($validatedData['manifest_info_id']);
        }

        // ✅ 避免重复查询数据库，提高效率
        $cnNumbers = array_column($validatedData['manifest_lists'], 'cn_no');
        $existingCNs = ManifestList::whereIn('cn_no', $cnNumbers)->whereNull('deleted_at')->exists();

        if ($existingCNs) {
            DB::rollBack();
            return response()->json([
                'message' => 'C/N No already exist and are active!',
                'errors' => ['cn_no' => ['Some CN numbers already exist and are active.']]
            ], 422);
        }

        // ✅ 批量创建 ManifestList，加入 `destination`
        $manifestLists = collect($validatedData['manifest_lists'])->map(function ($list) use ($manifestInfo) {
            $totalPrice = $this->calculateTotalPrice(
                $manifestInfo->from,
                $manifestInfo->to,
                $list['consignor_id'],
                $list['kg']
            );

            return [
                'manifest_info_id' => $manifestInfo->id,
                'consignor_id' => $list['consignor_id'],
                'consignee_name' => $list['consignee_name'],
                'cn_no' => $list['cn_no'],
                'pcs' => $list['pcs'],
                'kg' => floor($list['kg']),
                'gram' => round(($list['kg'] - floor($list['kg'])) * 1000),
                'total_price' => $totalPrice,
                'discount' => $list['discount'] ?? null,
                'origin' => $list['origin'],
                'destination' => $manifestInfo->to, // ✅ 确保 `destination` 被插入
                'remarks' => $list['remarks'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        ManifestList::insert($manifestLists);

        DB::commit();
        return response()->json([
            'message' => 'Manifest created successfully',
            'manifest_info' => $manifestInfo,
            'manifest_lists' => $manifestLists
        ], 201);
    } catch (ValidationException $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (QueryException $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Database error',
            'error' => $e->getMessage()
        ], 500);
    } catch (Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);
    }
}




        
        private function getNextManifestNo($maxManifestNo)
        {
            // 检查是否有删除的空缺编号
            $missingNo = DB::table('manifest_infos as m1')
                ->leftJoin('manifest_infos as m2', 'm1.manifest_no', '=', DB::raw('m2.manifest_no - 1'))
                ->whereNull('m2.manifest_no')
                ->orderBy('m1.manifest_no')
                ->value('m1.manifest_no');
        
            // 如果找到空缺编号，就用这个，否则用 `maxManifestNo + 1`
            return $missingNo ? $missingNo + 1 : ($maxManifestNo + 1 ?? 1001);
        }
        
    
        /**
         * 计算运费 (如果找不到运费规则，则返回 0)
         */
        private function calculateTotalPrice($from, $to, $consignorId, $kg)
        {
            $client = Client::find($consignorId);
            if (!$client) {
                return 0; // 没有找到 consignor，返回 0
            }
    
            // 转换大写以匹配数据库中的记录
            $from = strtoupper($from);
            $to = strtoupper($to);
    
            $shippingRate = ShippingRate::where('origin', $from)
                ->where('destination', $to)
                ->where('shipping_plan_id', $client->shipping_plan_id)
                ->first();
    
            if (!$shippingRate) {
                return 0; // 没有匹配的运费规则，返回 0
            }
    
            if ($kg <= $shippingRate->minimum_weight) {
                return (float) $shippingRate->minimum_price;
            }
    
            $extraWeight = $kg - $shippingRate->minimum_weight;
            $extraCost = $extraWeight * $shippingRate->additional_price_per_kg;
    
            return (float) ($shippingRate->minimum_price + $extraCost);
        }
    }
    

