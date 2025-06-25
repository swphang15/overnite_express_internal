<?php

namespace App\Http\Controllers;

use App\Exports\AutocountARInvoiceExport;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\ShippingRate;
use App\Models\ManifestInfo;
use App\Models\ManifestList;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Exports\ManifestExport;
use App\Exports\ManifestExcelExport;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ManifestController extends Controller
{
    public function checkRouteValidity(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'consignor_id' => 'required|exists:clients,id',
                'origin' => 'required|string',
                'destination' => 'required|string'
            ]);

            // 获取 consignor 的 shipping plan id
            $consignor = Client::findOrFail($validatedData['consignor_id']);
            $shippingPlanId = $consignor->shipping_plan_id;

            if (!$shippingPlanId) {
                return response()->json([
                    'message' => 'Consignor does not have a shipping plan assigned.'
                ], 400);
            }

            // 检查是否存在对应的 route
            $routeExists = ShippingRate::where('shipping_plan_id', $shippingPlanId)
                ->where('origin', $validatedData['origin'])
                ->where('destination', $validatedData['destination'])
                ->exists();

            if ($routeExists) {
                return response()->json([
                    'message' => 'Route is valid.'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Route not found. Please check if your origin and destination are correct.'
                ], 400);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Consignor not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function downloadPdf($manifestId)
    {
        $export = new ManifestExport($manifestId);
        return $export->exportPdf($manifestId);
    }

    // 📌 【2️⃣ 导出 Excel 】
    public function exportManifest(Request $request)
    {
        $request->validate([
            'consignor_id' => 'required|integer',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date'
        ]);

        $consignorId = $request->consignor_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // 获取 Client 名称（保留原样）
        $client = Client::findOrFail($consignorId);
        $clientName = $client->name;

        // 清理文件名非法字符
        $cleanName = preg_replace('/[\\\\\/:*?"<>|]/', '', $clientName);

        // 格式化日期为：月缩写 + 年后两位（如：MAR25）
        $date = Carbon::parse($startDate);
        $monthAbbr = strtoupper($date->format('M'));  // 月缩写，如 MAR
        $yearTwoDigit = $date->format('y');           // 年后两位，如 25

        $formattedDate = "{$monthAbbr}{$yearTwoDigit}"; // 拼接成：MAR25

        // 文件名：Company B_MAR25_invoice.xlsx
        $filename = "{$cleanName}_{$formattedDate}.xlsx";

        // 生成 Excel 内容
        if ($request->autocount) {
            $excelExport = new AutocountARInvoiceExport($consignorId, $startDate, $endDate);
        } else {
            $excelExport = new ManifestExcelExport($consignorId, $startDate, $endDate);
        }
        $excelContent = Excel::raw($excelExport, \Maatwebsite\Excel\Excel::XLSX);

        return response($excelContent, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }

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
                'lists.*.total_price' => 'nullable|numeric|min:0',
                'lists.*.discount' => 'sometimes|nullable|numeric|min:0',
                'lists.*.origin' => 'required|string',
                'lists.*.remarks' => 'nullable|string',
                'lists.*.misc_charge' => 'nullable|numeric|min:0',
                'lists.*.fuel_surcharge' => 'nullable|numeric|min:0',
            ]);

            // 2️⃣ 计算 `manifest_no`
            $maxManifestNo = ManifestInfo::withTrashed()->max('manifest_no'); // 包含软删除的最大值
            $nextManifestNo = $this->getNextManifestNo($maxManifestNo); // 找到下一个可用的编号

            // 3️⃣ 创建或获取 ManifestInfo
            if (!isset($validatedData['manifest_info_id'])) {
                $manifestInfo = ManifestInfo::create([
                    'date' => $validatedData['date'],
                    'awb_no' => $validatedData['awb_no'],
                    'to' => $validatedData['to'],
                    'from' => $validatedData['from'],
                    'flt' => $validatedData['flt'],
                    'manifest_no' => $nextManifestNo, // 使用计算出的 `manifest_no`
                ]);
            } else {
                $manifestInfo = ManifestInfo::findOrFail($validatedData['manifest_info_id']);
            }

            // 4️⃣ 批量创建 ManifestList
            $manifestLists = [];
            foreach ($validatedData['lists'] as $index => $list) {
                $fullKg = floor($list['kg']);
                $grams = ($list['kg'] - $fullKg) * 1000;

                // 计算 total_price
                $totalDetails = $this->calculateTotalPrice(
                    $list['fuel_surcharge'],
                    $list['misc_charge'],
                    $manifestInfo->from,
                    $manifestInfo->to,
                    $list['consignor_id'],
                    $list['kg']
                );
                $basePrice = $totalDetails['base_price'];
                $totalPrice = $totalDetails['total'];

                $manifestLists[] = ManifestList::create([
                    'manifest_info_id' => $manifestInfo->id,
                    'manifest_no' => $nextManifestNo + $index, // 确保 manifest_no 递增
                    'consignor_id' => $list['consignor_id'],
                    'consignee_name' => $list['consignee_name'],
                    'cn_no' => $list['cn_no'],
                    'pcs' => $list['pcs'],
                    'kg' => $fullKg,
                    'gram' => $grams,
                    'total_price' => $totalPrice,
                    'discount' => $list['discount'] ?? null,
                    'origin' => $list['origin'],
                    'remarks' => $list['remarks'] ?? null,
                    'base_price' => number_format($basePrice, 2, '.', ''),
                    'misc_charge' => $list['misc_charge'] ?? null,
                    'fuel_surcharge' => $list['fuel_surcharge'] ?? null,
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


    public function index(Request $request)
    {
        try {
            // 获取 start_date 和 end_date，如果用户没有提供，则不进行日期过滤
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // 获取 per_page 参数，默认 10，允许 10, 20, 50, 100
            $perPage = $request->input('per_page', 10); // 默认 10
            $perPage = in_array($perPage, [10, 20, 50, 100]) ? $perPage : 10; // 只允许特定值

            // 获取搜索条件
            $search = $request->input('search');

            // 查询数据库
            $query = ManifestInfo::with('user:id,name')
                ->latest(); // 按 created_at 倒序排序

            // 如果提供了 start_date 和 end_date，就进行过滤
            if ($startDate && $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }

            // 如果提供了 search 条件，就进行模糊搜索
            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('manifest_no', 'like', "%$search%")
                        ->orWhere('date', 'like', "%$search%")
                        ->orWhere('awb_no', 'like', "%$search%")
                        ->orWhere('to', 'like', "%$search%")
                        ->orWhere('from', 'like', "%$search%")
                        ->orWhere('flt', 'like', "%$search%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%$search%");
                        });
                });
            }

            // 分页，每页 $perPage 条
            $manifests = $query->paginate($perPage);

            // 返回 JSON 数据
            return response()->json([
                'data' => $manifests->items(), // 当前页数据
                'pagination' => [
                    'total' => $manifests->total(), // 总条数
                    'per_page' => $manifests->perPage(), // 每页数量
                    'current_page' => $manifests->currentPage(), // 当前页码
                    'last_page' => $manifests->lastPage(), // 最后一页
                    'next_page_url' => $manifests->nextPageUrl(), // 下一页 URL
                    'prev_page_url' => $manifests->previousPageUrl(), // 上一页 URL
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve manifests',
                'error' => $e->getMessage()
            ], 500);
        }
    }





    public function show($id)
    {
        try {
            // 获取 ManifestInfo，并加载关联的 ManifestList 和 Client (consignor)
            $manifestInfo = ManifestInfo::with(['manifestLists', 'manifestLists.client'])->findOrFail($id);

            // 修改 manifest_lists，将 kg 和 gram 合并，并调整 consignor_name 的位置
            $manifestInfo->manifestLists->transform(function ($item) {
                $item->kg = $item->kg + ($item->gram / 1000);
                unset($item->gram); // 移除 gram 字段

                // 重新构建 JSON 结构，确保 consignor_name 在 consignor_id 下面
                return [
                    'id' => $item->id,
                    'manifest_info_id' => $item->manifest_info_id,
                    'consignor_id' => $item->consignor_id,
                    'consignor_name' => $item->client->name ?? null, // 这里确保 consignor_name 在 consignor_id 下面
                    'consignee_name' => $item->consignee_name,
                    'cn_no' => $item->cn_no,
                    'pcs' => $item->pcs,
                    'kg' => $item->kg,
                    'remarks' => $item->remarks,
                    'total_price' => $item->total_price,
                    'discount' => $item->discount,
                    'origin' => $item->origin,
                    'destination' => $item->destination,
                    'misc_charge' => $item->misc_charge,
                    'fuel_surcharge' => $item->fuel_surcharge,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                    'deleted_at' => $item->deleted_at
                ];
            });

            return response()->json($manifestInfo, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Manifest not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showOneList($id, $listId)
    {
        try {
            $manifestInfo = ManifestInfo::with(['manifestLists' => function ($query) use ($listId) {
                $query->where('id', $listId);
            }, 'manifestLists.client'])->findOrFail($id);

            // 如果没有匹配的 list
            if ($manifestInfo->manifestLists->isEmpty()) {
                return response()->json(['message' => 'Manifest list not found'], 404);
            }

            $list = $manifestInfo->manifestLists->first();

            // 合并 kg 和 gram，移除 gram 字段
            $list->kg = $list->kg + ($list->gram / 1000);
            unset($list->gram);

            $data = [
                'id' => $list->id,
                'manifest_info_id' => $list->manifest_info_id,
                'consignor_id' => $list->consignor_id,
                'consignor_name' => $list->client->name ?? null,
                'consignee_name' => $list->consignee_name,
                'cn_no' => $list->cn_no,
                'pcs' => $list->pcs,
                'kg' => $list->kg,
                'remarks' => $list->remarks,
                'total_price' => $list->total_price,
                'discount' => $list->discount,
                'origin' => $list->origin,
                'destination' => $list->destination,
                'misc_charge' => $list->misc_charge,
                'fuel_surcharge' => $list->fuel_surcharge,
                'created_at' => $list->created_at,
                'updated_at' => $list->updated_at,
                'deleted_at' => $list->deleted_at
            ];

            return response()->json($data, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Manifest not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }



    public function update(Request $request, $id)
    {
        try {
            // 📝 仅验证 `ManifestInfo` 的数据
            $validatedData = $request->validate([
                'date' => 'required|date',
                'awb_no' => 'required|string', // ❌ 不检查唯一性
                'to' => 'required|string',
                'from' => 'required|string',
                'flt' => 'nullable|string',
            ]);

            // ✨ 查找 ManifestInfo
            $manifestInfo = ManifestInfo::findOrFail($id);

            // ✏️ 更新 ManifestInfo
            $manifestInfo->update($validatedData);

            return response()->json([
                'message' => 'Manifest updated successfully',
                'manifest_info' => $manifestInfo
            ], 200);
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
    private function getMiscCharge($consignorId, $origin, $destination)
    {
        $client = \App\Models\Client::find($consignorId);
        if (!$client || !$client->shipping_plan_id) {
            Log::info('Client or shipping_plan_id not found for consignorId: ' . $consignorId);
            return 0.00;
        }

        $shippingRate = DB::table('shipping_rates')
            ->where('shipping_plan_id', $client->shipping_plan_id)
            ->where('origin', $origin)
            ->where('destination', $destination)
            ->first();

        if (!$shippingRate) {
            Log::info('Shipping rate not found for shipping_plan_id: ' . $client->shipping_plan_id . ', origin: ' . $origin . ', destination: ' . $destination);
            return 0.00;
        }

        Log::info('Misc charge found: ' . $shippingRate->misc_charge);
        return $shippingRate->misc_charge;
    }

    public function updateManifestList(Request $request, $id)
    {
        try {
            $validatedData = $request->validate([
                'consignor_id' => 'required|exists:clients,id',
                'consignee_name' => 'required|string',
                'cn_no' => 'required|numeric',
                'pcs' => 'required|integer|min:1',
                'kg' => 'required|numeric|min:0',
                'origin' => 'required|string',
                'destination' => 'required|string',
                'remarks' => 'nullable|string',
                'misc_charge' => 'required|numeric|min:0',
                'fuel_surcharge' => 'required|numeric|min:0'
            ]);

            $manifestList = ManifestList::findOrFail($id);

            // 检查 consignor 是否存在 shipping_plan_id
            $consignor = Client::findOrFail($validatedData['consignor_id']);
            $shippingPlanId = $consignor->shipping_plan_id;

            if (!$shippingPlanId) {
                return response()->json([
                    'message' => 'Consignor does not have a shipping plan assigned.'
                ], 400);
            }

            // 检查是否存在对应的 shipping rate
            $routeExists = ShippingRate::where('shipping_plan_id', $shippingPlanId)
                ->where('origin', $validatedData['origin'])
                ->where('destination', $validatedData['destination'])
                ->exists();

            if (!$routeExists) {
                return response()->json([
                    'message' => 'Route not found. Please check if your origin and destination are correct.'

                ], 400);
            }

            $duplicate = ManifestList::where('cn_no', $validatedData['cn_no'])
                ->where('id', '!=', $id)
                ->exists();

            if ($duplicate) {
                $warning = "CN No: {$validatedData['cn_no']} already exists, total_price set to 0";
                $totalPrice = 0;
                // $miscCharge = 0;
            } else {
                $totalDetails = $this->calculateTotalPriceDetailed(
                    $validatedData['fuel_surcharge'],
                    $validatedData['misc_charge'],
                    $validatedData['origin'],
                    $validatedData['destination'],
                    $validatedData['consignor_id'],
                    $validatedData['kg']
                );
                $totalPrice = $totalDetails['total'];
                $basePrice = $totalDetails['base_price'];
                // $miscCharge = $this->getMiscCharge(
                //     $validatedData['consignor_id'],
                //     $validatedData['origin'],
                //     $validatedData['destination']
                // );

                $warning = null;
            }

            $manifestList->update([
                'consignor_id' => $validatedData['consignor_id'],
                'consignee_name' => $validatedData['consignee_name'],
                'cn_no' => $validatedData['cn_no'],
                'pcs' => $validatedData['pcs'],
                'kg' => floor($validatedData['kg']),
                'gram' => round(($validatedData['kg'] - floor($validatedData['kg'])) * 1000),
                'total_price' => number_format($totalPrice, 2, '.', ''),
                'origin' => $validatedData['origin'],
                'destination' => $validatedData['destination'],
                'remarks' => $validatedData['remarks'] ?? null,
                'misc_charge' => number_format($validatedData['misc_charge'], 2, '.', ''),
                'fuel_surcharge' => number_format($validatedData['fuel_surcharge'], 2, '.', ''),
                'base_price' => number_format($basePrice, 2, '.', ''),
                // 'misc_charge' => number_format($miscCharge, 2, '.', ''),
            ]);

            $manifestList->refresh();

            return response()->json([
                'message' => 'Manifest list updated successfully',
                'data' => $manifestList,
                'warning' => $warning
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Manifest list not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }





    private function calculateTotalPriceDetailed($fuel_surcharge, $miscCharge, $from, $to, $consignorId, $kg)
    {
        $client = Client::find($consignorId);
        if (!$client) {
            return ['base_price' => 0, 'misc_charge' => 0, 'total' => 0];
        }

        $from = strtoupper($from);
        $to = strtoupper($to);

        $shippingRate = ShippingRate::where('origin', $from)
            ->where('destination', $to)
            ->where('shipping_plan_id', $client->shipping_plan_id)
            ->first();

        if (!$shippingRate) {
            return ['base_price' => 0, 'misc_charge' => 0, 'total' => 0];
        }

        // 🚚 关键修改点：超重部分向上取整计算
        if ($kg <= $shippingRate->minimum_weight) {
            $basePrice = $shippingRate->minimum_price;
        } else {
            $extraWeight = ceil($kg - $shippingRate->minimum_weight); // 👈 向上取整！
            $extraCost = $extraWeight * $shippingRate->additional_price_per_kg;
            $basePrice = $shippingRate->minimum_price + $extraCost;
        }

        // $miscCharge = $shippingRate->misc_charge ?? 0;
        $total = $basePrice * $fuel_surcharge + $miscCharge;

        return [
            'base_price' => (float) $basePrice,
            'misc_charge' => (float) $miscCharge,
            'total' => (float) $total,
        ];
    }



    public function destroy($id)
    {
        try {
            // 查找 ManifestInfo
            $manifestInfo = ManifestInfo::findOrFail($id);

            // 级联删除所有 ManifestList
            $manifestInfo->manifestLists()->delete();

            // 删除 ManifestInfo
            $manifestInfo->delete();

            return response()->json([
                'message' => 'ManifestInfo and its lists deleted successfully'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Manifest not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function destroyManifestList($id)
    {
        try {
            // 查找 ManifestList
            $manifestList = ManifestList::findOrFail($id);

            // 删除 ManifestList
            $manifestList->delete();

            return response()->json([
                'message' => 'Manifest list deleted successfully'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Manifest list not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * 计算 total_price
     */
    private function calculateTotalPrice($fuel_surcharge, $misc_charge, $from, $to, $consignorId, $kg)
    {
        // 1️⃣ 获取 consignor 的运费计划
        $client = Client::find($consignorId);
        if (!$client) {
            throw new Exception("Consignor not found.");
        }

        // 2️⃣ 获取 shipping_rate
        $shippingRate = ShippingRate::where('origin', $from)
            ->where('destination', $to)
            ->where('shipping_plan_id', $client->shipping_plan_id)
            ->first();

        if (!$shippingRate) {
            throw new Exception("Shipping rate not found.");
        }

        // 3️⃣ 计算运费
        if ($kg <= $shippingRate->minimum_weight) {
            return (float) $shippingRate->minimum_price;
        }

        // 计算额外重量费用
        $extraWeight = $kg - $shippingRate->minimum_weight;
        $extraCost = $extraWeight * $shippingRate->additional_price_per_kg;
        return [
            'base_price' => (float) ($shippingRate->minimum_price + $extraCost),
            'total' => (float) (($shippingRate->minimum_price + $extraCost) * $fuel_surcharge + $misc_charge)
        ];
    }
}

// namespace App\Http\Controllers;

// use App\Models\Manifest;
// use App\Models\Client;
// use App\Models\Agent;
// use App\Models\ShippingRate;
// use App\Models\ManifestInfo;
// use App\Models\ManifestList;
// use Illuminate\Http\Request;
// use Carbon\Carbon;

// class ManifestController extends Controller
// {
//     public function createManifestFormData()
//     {
//         $from = ShippingRate::distinct()->pluck('origin');
//         $to = ShippingRate::distinct()->pluck('destination');

//         // 只获取非 admin 的公司
//         $companies = Client::where('role', '!=', 'admin')
//             ->select(["id", "company_name"])
//             ->get();

//         return response()->json([
//             "companies" => $companies,
//             "from" => $from,
//             "to" => $to
//         ]);
//     }


//     public function index()
//     {
//         $manifests = Manifest::with(['consignor'])->get();
//         return response()->json($manifests);
//     }
//     public function store(Request $request)
// {
//     $validatedData = $request->validate([
//         // ManifestInfo 的字段
//         'date' => 'required|date',
//         'awb_no' => 'required|string|unique:manifest_info',
//         'to' => 'required|string',
//         'from' => 'required|string',
//         'flt' => 'nullable|string',
//         'manifest_no' => 'required|string|unique:manifest_info',

//         // ManifestList 的字段
//         'consignor_id' => 'required|exists:clients,id',
//         'consignee_name' => 'required|string',
//         'cn_no' => 'required|string|unique:manifest_list',
//         'pcs' => 'required|integer|min:1',
//         'kg' => 'required|numeric|min:0', // 允许小数
//         'total_price' => 'required|numeric|min:0',
//         'discount' => 'nullable|numeric|min:0',
//         'origin' => 'required|string',
//         'remarks' => 'nullable|string',
//     ]);

//     // 1️⃣ 创建 ManifestInfo
//     $manifestInfo = ManifestInfo::create([
//         'date' => $validatedData['date'],
//         'awb_no' => $validatedData['awb_no'],
//         'to' => $validatedData['to'],
//         'from' => $validatedData['from'],
//         'flt' => $validatedData['flt'],
//         'manifest_no' => $validatedData['manifest_no'],
//     ]);

//     // 2️⃣ 拆分 kg -> kg 和 gram
//     $fullKg = floor($validatedData['kg']);
//     $grams = ($validatedData['kg'] - $fullKg) * 1000;

//     // 3️⃣ 创建 ManifestList
//     $manifestList = ManifestList::create([
//         'manifest_info_id' => $manifestInfo->id, // 关联 ManifestInfo ID
//         'consignor_id' => $validatedData['consignor_id'],
//         'consignee_name' => $validatedData['consignee_name'],
//         'cn_no' => $validatedData['cn_no'],
//         'pcs' => $validatedData['pcs'],
//         'kg' => $fullKg,
//         'gram' => $grams,
//         'total_price' => $validatedData['total_price'],
//         'discount' => $validatedData['discount'],
//         'origin' => $validatedData['origin'],
//         'remarks' => $validatedData['remarks']
//     ]);

//     return response()->json([
//         'message' => 'Manifest created successfully',
//         'manifest_info' => $manifestInfo,
//         'manifest_list' => $manifestList
//     ], 201);
// }

//     // public function store(Request $request)
//     // {
//     //     $request->validate([
//     //         'origin' => 'required|string',
//     //         'consignor' => 'required',
//     //         'consignee' => 'required|string', // 这里 consignee 变成 string
//     //         'cn_no' => 'required|integer',
//     //         'pcs' => 'required|integer',
//     //         'kg' => 'required|integer',
//     //         'gram' => 'required|integer',
//     //         'remarks' => 'nullable|string',
//     //         'date' => 'required|date',
//     //         'awb_no' => 'required|integer',
//     //         'to' => 'required|string',
//     //         'from' => 'required|string',
//     //         'flt' => 'required|string',
//     //         'manifest_no' => 'required|integer',
//     //         'discount' => 'nullable|numeric|min:0|max:100',
//     //     ]);

//     //     $consignor = is_numeric($request->input('consignor'))
//     //         ? Client::find($request->input('consignor'))
//     //         : Client::firstOrCreate(['name' => $request->input('consignor')]);

//     //     if (!$consignor) {
//     //         return response()->json(['error' => 'Consignor not found'], 400);
//     //     }

//     //     $kg = $request->kg;
//     //     $gram = $request->gram;
//     //     $origin = $request->from;
//     //     $destination = $request->to;
//     //     $shippingRate = ShippingRate::where('origin', $origin)->where('destination', $destination)->first();

//     //     if (!$shippingRate) {
//     //         return response()->json(['error' => 'Shipping rate not found for this route'], 400);
//     //     }

//     //     $total_weight = $kg + ($gram / 1000);
//     //     if ($total_weight <= $shippingRate->minimum_weight) {
//     //         $total_price = $shippingRate->minimum_price;
//     //     } else {
//     //         $extra_kg = $total_weight - $shippingRate->minimum_weight;
//     //         $total_price = $shippingRate->minimum_price + ($extra_kg * $shippingRate->additional_price_per_kg);
//     //     }

//     //     $discount = $request->discount ?? 0;
//     //     $total_price_after_discount = $total_price * (1 - ($discount / 100));

//     //     $manifest = Manifest::create([
//     //         'origin' => $request->input('origin'),
//     //         'consignor_id' => $consignor->id,
//     //         'consignee_name' => $request->input('consignee'), // 直接存字符串
//     //         'cn_no' => $request->input('cn_no'),
//     //         'pcs' => $request->input('pcs'),
//     //         'kg' => $request->input('kg'),
//     //         'gram' => $request->input('gram'),
//     //         'remarks' => $request->input('remarks'),
//     //         'date' => $request->input('date'),
//     //         'awb_no' => $request->input('awb_no'),
//     //         'to' => $request->input('to'),
//     //         'from' => $request->input('from'),
//     //         'flt' => $request->input('flt'),
//     //         'manifest_no' => $request->input('manifest_no'),
//     //         'total_price' => $total_price_after_discount,
//     //         'discount' => $discount,
//     //         'delivery_date' => null,
//     //     ]);

//     //     return response()->json($manifest->load('consignor'), 201);
//     // }


//     public function confirmShipment($id, Request $request)
//     {
//         $manifest = Manifest::findOrFail($id);

//         if ($manifest->delivery_date) {
//             return response()->json(['error' => 'Shipment already confirmed'], 400);
//         }

//         $deliveryDate = $request->input('delivery_date') ?: Carbon::now()->toDateString();
//         $manifest->update(['delivery_date' => $deliveryDate]);

//         return response()->json([
//             'message' => 'Shipment confirmed successfully',
//             'manifest' => $manifest
//         ]);
//     }

//     public function show($id)
//     {
//         $manifest = Manifest::with(['consignor'])->findOrFail($id);
//         return response()->json($manifest);
//     }

//     public function update(Request $request, Manifest $manifest)
//     {
//         $request->validate([
//             'origin' => 'sometimes|string',
//             'consignor' => 'sometimes',
//             'consignee' => 'sometimes|string',
//             'cn_no' => 'sometimes|integer',
//             'pcs' => 'sometimes|integer',
//             'kg' => 'sometimes|integer',
//             'gram' => 'sometimes|integer',
//             'remarks' => 'nullable|string',
//             'date' => 'sometimes|date',
//             'awb_no' => 'sometimes|integer',
//             'to' => 'sometimes|string',
//             'from' => 'sometimes|string',
//             'flt' => 'sometimes|string',
//             'manifest_no' => 'sometimes|integer',
//             'discount' => 'nullable|numeric|min:0|max:100',
//         ]);

//         $manifest_data = [
//             'manifest_number' => $manifest->manifest_number,
//         ];

//         // // 处理 Consignor
//         // if ($request->has('consignor')) {
//         //     $consignor = is_numeric($request->input('consignor'))
//         //         ? Client::find($request->input('consignor'))
//         //         : Client::firstOrCreate(['name' => $request->input('consignor')]);

//         //     if ($consignor) {
//         //         $manifest->consignor_id = $consignor->id;
//         //     } else {
//         //         return response()->json(['error' => 'Invalid consignor'], 400);
//         //     }
//         // }

//         // 处理 Consignee 和其他字段
//         if ($request->has('consignee')) {
//             $manifest_data['consignee'] = $request->consignee;
//         }
//         if ($request->has('origin')) {
//             $manifest_data['origin'] = $request->origin;
//         }
//         if ($request->has('from')) {
//             $manifest_data['from'] = $request->from;
//         }
//         if ($request->has('to')) {
//             $manifest_data['to'] = $request->to;
//         }

//         if ($request->has('cn_no')) {
//             $manifest_data['cn_no'] = $request->cn_no;
//         }
//         if ($request->has('pcs')) {
//             $manifest_data['pcs'] = $request->pcs;
//         }
//         if ($request->has('kg')) {
//             $manifest_data['kg'] = $request->kg;
//         }
//         if ($request->has('gram')) {
//             $manifest_data['gram'] = $request->gram;
//         }
//         if ($request->has('remarks')) {
//             $manifest_data['remarks'] = $request->remarks;
//         }
//         if ($request->has('date')) {
//             $manifest_data['date'] = $request->date;
//         }
//         if ($request->has('awb_no')) {
//             $manifest_data['awb_no'] = $request->awb_no;
//         }
//         if ($request->has('flt')) {
//             $manifest_data['flt'] = $request->flt;
//         }
//         if ($request->has('manifest_no')) {
//             $manifest_data['manifest_no'] = $request->manifest_no;
//         }



//         // 计算新价格
//         if ($request->hasAny(['kg', 'gram', 'discount', 'from', 'to'])) {
//             $kg = $request->input('kg', $manifest->kg);
//             $gram = $request->input('gram', $manifest->gram);
//             $discount = $request->input('discount', $manifest->discount);
//             $origin = $request->input('from', $manifest->from);
//             $destination = $request->input('to', $manifest->to);


//             $shippingRate = ShippingRate::where('origin', $origin)
//                 ->where('destination', $destination)
//                 ->first();

//             if ($shippingRate) {
//                 $total_weight = $kg + ($gram / 1000);
//                 if ($total_weight <= $shippingRate->minimum_weight) {
//                     $total_price = $shippingRate->minimum_price;
//                 } else {
//                     $extra_kg = $total_weight - $shippingRate->minimum_weight;
//                     $total_price = $shippingRate->minimum_price + ($extra_kg * $shippingRate->additional_price_per_kg);
//                 }

//                 $total_price_after_discount = $total_price * (1 - ($discount / 100));
//                 $manifest_data['total_price'] = $total_price_after_discount;
//                 $manifest_data['discount'] = $discount;
//             }
//         }

//         // 批量更新字段
//         $manifest->fill($manifest_data);
//         $manifest->save();

//         return response()->json($manifest->load('consignor'));
//     }


//     public function destroy($id)
//     {
//         Manifest::destroy($id);
//         return response()->json(null, 204);
//     }
// }
