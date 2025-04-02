<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use App\Models\ManifestInfo;
use App\Models\ManifestList;
use App\Models\Client;
use App\Models\ShippingRate;
use Exception;

class ManifestInfoController extends Controller
{
    /**
     * 获取预计总价格
     */
    public function getEstimatedTotalPrice(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'origin' => 'required|string',
                'destination' => 'required|string',
                'consignor_id' => 'required|exists:clients,id',
                'kg' => 'required|numeric|min:0',
                'cn_no' => 'required|numeric', // 添加 CN No 检查
            ]);

            // 检查 CN No 是否已经存在
            $existingManifest = ManifestList::where('cn_no', $validatedData['cn_no'])->exists();

            if ($existingManifest) {
                return response()->json([
                    'estimated_total_price' => "0.00",
                    'message' => "CN No: {$validatedData['cn_no']} already exists, total price set to 0."
                ], 200);
            }

            $totalPrice = $this->calculateTotalPrice(
                $validatedData['origin'],
                $validatedData['destination'],
                $validatedData['consignor_id'],
                $validatedData['kg']
            );

            return response()->json([
                'estimated_total_price' => number_format($totalPrice, 2, '.', '')
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
     * 创建 Manifest
     */
    public function store(Request $request)
    {
        return $this->handleManifest($request);
    }

    /**
     * 追加 ManifestList 到现有 ManifestInfo
     */
    public function addLists(Request $request, $id)
    {
        $request->merge(['manifest_info_id' => $id]);
        return $this->handleManifest($request);
    }

    /**
     * 处理创建/追加 Manifest 逻辑
     */
    private function handleManifest(Request $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'manifest_info_id' => 'nullable|exists:manifest_infos,id',
                'date' => 'required_without:manifest_info_id|date',
                'awb_no' => 'required_without:manifest_info_id|string',
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
                'manifest_lists.*.destination' => 'required|string',
                'manifest_lists.*.remarks' => 'nullable|string',
                'manifest_lists.*.total_price' => isset($request->manifest_info_id)
                    ? 'prohibited' // 追加时 total_price 不能提供
                    : 'required|numeric|min:0' // 创建时 total_price 必须提供
            ]);

            $userId = Auth::id();
            $maxManifestNo = ManifestInfo::withTrashed()->max('manifest_no');
            $nextManifestNo = $this->getNextManifestNo($maxManifestNo);

            if (!isset($validatedData['manifest_info_id'])) {
                // 创建 ManifestInfo
                $manifestInfo = ManifestInfo::create([
                    'date' => $validatedData['date'],
                    'awb_no' => $validatedData['awb_no'],
                    'to' => $validatedData['to'],
                    'from' => $validatedData['from'],
                    'flt' => $validatedData['flt'],
                    'manifest_no' => $nextManifestNo,
                    'user_id' => $userId,
                ]);
            } else {
                // 获取已有 ManifestInfo
                $manifestInfo = ManifestInfo::findOrFail($validatedData['manifest_info_id']);
            }

            // 处理 ManifestList
            $warningMessages = [];
            $manifestLists = collect($validatedData['manifest_lists'])->map(function ($list) use ($manifestInfo, $validatedData, &$warningMessages) {
                // 检查 `cn_no` 是否已存在
                $existingManifest = ManifestList::where('cn_no', $list['cn_no'])->exists();

                if ($existingManifest) {
                    // `cn_no` 已存在，提醒用户
                    $warningMessages[] = "CN No: {$list['cn_no']} already exists, the total price will be set to 0";
                    $totalPrice = 0;
                } else {
                    // `cn_no` 不存在，处理价格
                    if (isset($validatedData['manifest_info_id'])) {
                        $totalPrice = $this->calculateTotalPrice(
                            $list['origin'],
                            $list['destination'],
                            $list['consignor_id'],
                            $list['kg']
                        );
                    } else {
                        $totalPrice = $list['total_price'];
                    }
                }

                return [
                    'manifest_info_id' => $manifestInfo->id,
                    'consignor_id' => $list['consignor_id'],
                    'consignee_name' => $list['consignee_name'],
                    'cn_no' => $list['cn_no'],
                    'pcs' => $list['pcs'],
                    'kg' => floor($list['kg']),
                    'gram' => round(($list['kg'] - floor($list['kg'])) * 1000),
                    'total_price' => number_format($totalPrice, 2, '.', ''),
                    'discount' => $list['discount'] ?? null,
                    'origin' => $list['origin'],
                    'destination' => $list['destination'],
                    'remarks' => $list['remarks'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            ManifestList::insert($manifestLists);

            DB::commit();
            return response()->json([
                'message' => isset($validatedData['manifest_info_id']) ? 'Manifest updated successfully' : 'Manifest created successfully',
                'manifest_info' => $manifestInfo,
                'manifest_lists' => $manifestLists,
                'warnings' => $warningMessages
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

    public function getCnNumbers($consignor_id)
    {
        $cnNumbers = ManifestList::where('consignor_id', $consignor_id)
            ->select('consignee_name', 'cn_no', 'pcs', 'kg', 'origin', 'destination', 'remarks')
            ->get();

        return response()->json($cnNumbers);
    }


    private function getNextManifestNo()
    {
        $yearMonth = now()->format('Ym'); // 获取当前年月，例如 202503

        // 查找当前月份的最大 manifest_no
        $maxManifestNo = ManifestInfo::where('manifest_no', 'like', $yearMonth . '%')
            ->orderBy('manifest_no', 'desc')
            ->value('manifest_no');

        if (!$maxManifestNo) {
            // 如果没有记录，从 001 开始
            return $yearMonth . '001';
        }

        // 直接获取后三位序号并递增
        $lastSequence = (int) substr($maxManifestNo, -3);
        $nextSequence = $lastSequence + 1;

        return $yearMonth . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
    }


    private function calculateTotalPrice($from, $to, $consignorId, $kg)
    {
        $client = Client::find($consignorId);
        if (!$client) {
            return 0;
        }

        $from = strtoupper($from);
        $to = strtoupper($to);

        $shippingRate = ShippingRate::where('origin', $from)
            ->where('destination', $to)
            ->where('shipping_plan_id', $client->shipping_plan_id)
            ->first();

        if (!$shippingRate) {
            return 0;
        }

        if ($kg <= $shippingRate->minimum_weight) {
            return (float) $shippingRate->minimum_price;
        }

        $extraWeight = $kg - $shippingRate->minimum_weight;
        $extraCost = $extraWeight * $shippingRate->additional_price_per_kg;

        return (float) ($shippingRate->minimum_price + $extraCost);
    }


    public function searchManifest(Request $request)
    {
        // 验证 consignor_id 必填，start_date 和 end_date 可选
        $request->validate([
            'consignor_id' => 'required|integer',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date'
        ]);

        // 获取 per_page 参数，默认 10，允许 10, 20, 50, 100
        $perPage = $request->input('per_page', 10); // 默认 10
        $perPage = in_array($perPage, [10, 20, 50, 100]) ? $perPage : 10; // 限制可选值

        // 查询数据库
        $query = DB::table('manifest_lists')
            ->where('consignor_id', $request->consignor_id)
            ->select(
                DB::raw("CONCAT('DCN ', origin, '-', destination) AS Description"),
                'cn_no as Consignment_Note',  // **不能有空格**
                DB::raw("DATE_FORMAT(created_at, '%d-%m-%Y') AS Delivery_Date"),
                'pcs as Qty',
                'total_price as Total_RM'
            );

        // 如果有 start_date 和 end_date，则加上日期范围过滤
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start_date = $request->start_date . " 00:00:00";
            $end_date = $request->end_date . " 23:59:59"; // **确保包含完整一天的数据**

            $query->whereBetween('created_at', [$start_date, $end_date]);
        }

        // **分页，每页 $perPage 条**
        $manifests = $query->paginate($perPage);

        // **计算当前页的总价格**
        $totalPrice = $manifests->sum('Total_RM');

        // 返回 JSON 响应
        return response()->json([
            'data' => $manifests->items(), // 只返回当前页数据
            'total_price' => $totalPrice, // 当前页的总价格
            'pagination' => [
                'total' => $manifests->total(), // 总条数
                'per_page' => $manifests->perPage(), // 每页数量
                'current_page' => $manifests->currentPage(), // 当前页码
                'last_page' => $manifests->lastPage(), // 最后一页
                'next_page_url' => $manifests->nextPageUrl(), // 下一页 URL
                'prev_page_url' => $manifests->previousPageUrl(), // 上一页 URL
            ],
        ]);
    }
}
