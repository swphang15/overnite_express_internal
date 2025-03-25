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
use App\Exports\ManifestExport;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Maatwebsite\Excel\Facades\Excel;

class ManifestController extends Controller
{


    public function downloadPdf($manifestId)
    {
        $export = new ManifestExport($manifestId);
        return $export->exportPdf($manifestId);
    }

    // 📌 【2️⃣ 导出 Excel 】

    public function exportManifestExcel($consignor_id)
    {
        return Excel::download(new ManifestExport($consignor_id), "Manifest_Consignor_{$consignor_id}.xlsx");
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
                $totalPrice = $this->calculateTotalPrice(
                    $manifestInfo->from,
                    $manifestInfo->to,
                    $list['consignor_id'],
                    $list['kg']
                );

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


    public function index()
    {
        try {
            $manifests = ManifestInfo::with('user:id,name')
                ->latest() // 按照 created_at 倒序排序，最新的在最前
                ->get();

            return response()->json($manifests->map(function ($manifest) {
                return [
                    'id' => $manifest->id,
                    'date' => $manifest->date,
                    'awb_no' => $manifest->awb_no,
                    'to' => $manifest->to,
                    'from' => $manifest->from,
                    'flt' => $manifest->flt,
                    'manifest_no' => $manifest->manifest_no,
                    'user_id' => $manifest->user_id,
                    'created_by' => $manifest->user ? $manifest->user->name : null, // ✅ 获取用户名
                    'created_at' => $manifest->created_at, // 可以让前端看到创建时间
                ];
            }), 200);
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


    public function update(Request $request, $id)
    {
        try {
            // 📝 验证请求数据
            $validatedData = $request->validate([
                'date' => 'required|date',
                'awb_no' => 'required|string|unique:manifest_infos,awb_no,' . $id,
                'to' => 'required|string',
                'from' => 'required|string',
                'flt' => 'nullable|string',

                'manifest_lists' => 'required|array|min:1',
                'manifest_lists.*.id' => 'nullable|exists:manifest_lists,id',
                'manifest_lists.*.consignor_id' => 'required|exists:clients,id',
                'manifest_lists.*.consignee_name' => 'required|string',
                'manifest_lists.*.cn_no' => [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) use ($request, $id) {
                        $listIndex = explode('.', $attribute)[1];
                        $listId = $request->input("manifest_lists.$listIndex.id");

                        if ($listId) {
                            // If updating an existing record, check excluding itself
                            $exists = ManifestList::where('cn_no', $value)
                                ->where('id', '!=', $listId)
                                ->exists();
                        } else {
                            // If creating a new record, check only within the same manifest
                            $exists = ManifestList::where('cn_no', $value)
                                ->where('manifest_info_id', $id) // Only check within the same manifest
                                ->exists();
                        }

                        if ($exists) {
                            $fail('The ' . $attribute . ' has already been taken.');
                        }
                    }
                ],
                'manifest_lists.*.pcs' => 'required|integer|min:1',
                'manifest_lists.*.kg' => 'required|numeric|min:0',
                'manifest_lists.*.origin' => 'required|string',
                'manifest_lists.*.remarks' => 'nullable|string',
            ]);

            // ✨ 查找 ManifestInfo
            $manifestInfo = ManifestInfo::findOrFail($id);

            // ✏️ 更新 ManifestInfo
            $manifestInfo->update([
                'date' => $validatedData['date'],
                'awb_no' => $validatedData['awb_no'],
                'to' => $validatedData['to'],
                'from' => $validatedData['from'],
                'flt' => $validatedData['flt'],
            ]);

            // 🚀 处理 ManifestLists 的更新
            foreach ($validatedData['manifest_lists'] as $list) {
                if (isset($list['id'])) {
                    // ✅ 更新现有的 ManifestList
                    $manifestList = ManifestList::findOrFail($list['id']);
                    $manifestList->update([
                        'consignor_id' => $list['consignor_id'],
                        'consignee_name' => $list['consignee_name'],
                        'cn_no' => $list['cn_no'],
                        'pcs' => $list['pcs'],
                        'kg' => floor($list['kg']),
                        'gram' => ($list['kg'] - floor($list['kg'])) * 1000, // 计算 gram
                        'origin' => $list['origin'],
                        'remarks' => $list['remarks'] ?? null,
                        'total_price' => $this->calculateTotalPrice(
                            $manifestInfo->from,
                            $manifestInfo->to,
                            $list['consignor_id'],
                            $list['kg']
                        ),
                    ]);
                } else {
                    // ➕ 创建新的 ManifestList
                    ManifestList::create([
                        'manifest_info_id' => $manifestInfo->id,
                        'manifest_no' => $manifestInfo->manifest_no,
                        'consignor_id' => $list['consignor_id'],
                        'consignee_name' => $list['consignee_name'],
                        'cn_no' => $list['cn_no'],
                        'pcs' => $list['pcs'],
                        'kg' => floor($list['kg']),
                        'gram' => ($list['kg'] - floor($list['kg'])) * 1000, // 计算 gram
                        'origin' => $list['origin'],
                        'remarks' => $list['remarks'] ?? null,
                        'total_price' => $this->calculateTotalPrice(
                            $manifestInfo->from,
                            $manifestInfo->to,
                            $list['consignor_id'],
                            $list['kg']
                        ),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Manifest updated successfully',
                'manifest_info' => $manifestInfo->load('manifestLists')
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
        return response()->json(['data' => $request->all()], 200);
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
    private function calculateTotalPrice($from, $to, $consignorId, $kg)
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

        return (float) ($shippingRate->minimum_price + $extraCost);
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
