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
                    'from' => 'required|string',
                    'to' => 'required|string',
                    'consignor_id' => 'required|exists:clients,id',
                    'kg' => 'required|numeric|min:0',
                ]);
    
                // 计算价格
                $totalPrice = $this->calculateTotalPrice(
                    $validatedData['from'],
                    $validatedData['to'],
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
        public function store(Request $request)
        {
            try {
                // 1️⃣ 参数验证
                $validatedData = $request->validate([
                    'manifest_info_id' => 'nullable|exists:manifest_infos,id',
                    'date' => 'required_without:manifest_info_id|date',
                    'awb_no' => 'required_without:manifest_info_id|string|unique:manifest_infos,awb_no',
                    'to' => 'required_without:manifest_info_id|string',
                    'from' => 'required_without:manifest_info_id|string',
                    'flt' => 'nullable|string',
    
                    'lists' => 'required|array|min:1',
                    'lists.*.consignor_id' => 'required|exists:clients,id',
                    'lists.*.consignee_name' => 'required|string',
                    'lists.*.cn_no' => 'required|numeric|unique:manifest_lists,cn_no',
                    'lists.*.pcs' => 'required|integer|min:1',
                    'lists.*.kg' => 'required|numeric|min:0',
                    // 'lists.*.total_price' => 'required|numeric|min:0', // ✅ 直接使用前端传过来的 total_price
                    'lists.*.total_price' => 'nullable|numeric|min:0', // ✅ total_price 允许为 null

                    'lists.*.discount' => 'sometimes|nullable|numeric|min:0',
                    'lists.*.origin' => 'required|string',
                    'lists.*.remarks' => 'nullable|string',
                ]);
    
                // 2️⃣ 计算 `manifest_no`
                $maxManifestNo = ManifestInfo::withTrashed()->max('manifest_no');
                $nextManifestNo = $this->getNextManifestNo($maxManifestNo);
    
                // 3️⃣ 创建或获取 ManifestInfo
                if (!isset($validatedData['manifest_info_id'])) {
                    $manifestInfo = ManifestInfo::create([
                        'date' => $validatedData['date'],
                        'awb_no' => $validatedData['awb_no'],
                        'to' => $validatedData['to'],
                        'from' => $validatedData['from'],
                        'flt' => $validatedData['flt'],
                        'manifest_no' => $nextManifestNo,
                    ]);
                } else {
                    $manifestInfo = ManifestInfo::findOrFail($validatedData['manifest_info_id']);
                }
    
                // 4️⃣ 批量创建 ManifestList
                $manifestLists = [];
                foreach ($validatedData['lists'] as $index => $list) {
                    $fullKg = floor($list['kg']);
                    $grams = ($list['kg'] - $fullKg) * 1000;
    
                    $manifestLists[] = ManifestList::create([
                        'manifest_info_id' => $manifestInfo->id,
                        'manifest_no' => $nextManifestNo + $index,
                        'consignor_id' => $list['consignor_id'],
                        'consignee_name' => $list['consignee_name'],
                        'cn_no' => $list['cn_no'],
                        'pcs' => $list['pcs'],
                        'kg' => $fullKg,
                        'gram' => $grams,
                        // 'total_price' => $list['total_price'], // ✅ 直接使用前端传来的 total_price

                        'total_price' => $list['total_price'] ?? null, // ✅ total_price 默认 null

                        'discount' => $list['discount'] ?? null,
                        'origin' => $list['origin'],
                        'remarks' => $list['remarks'] ?? null,
                    ]);
                }
    
                return response()->json([
                    'message' => 'Manifest created successfully',
                    'manifest_info' => $manifestInfo,
                    'manifest_list' => $manifestLists
                ], 201);
    
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            } catch (Exception $e) {
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
    

