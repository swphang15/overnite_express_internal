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
                'cn_no' => 'required|numeric',
                'misc_charge' => 'required|numeric',
                'fuel_surcharge' => 'required|numeric',
            ]);

            // 检查 CN No 是否已存在
            $existingManifest = ManifestList::where('cn_no', $validatedData['cn_no'])->exists();

            if ($existingManifest) {
                return response()->json([
                    'estimated_total_price' => "0.00",
                    'message' => "CN No: {$validatedData['cn_no']} already exists, total price set to 0."
                ], 200);
            }

            // 获取 consignor 信息，并检查是否有有效的 shipping plan
            $consignor = Client::findOrFail($validatedData['consignor_id']);
            $shippingPlanId = $consignor->shipping_plan_id;

            if (!$shippingPlanId) {
                return response()->json([
                    'message' => 'Consignor does not have a shipping plan assigned.'
                ], 400);
            }

            // 检查是否存在有效的 shipping rate
            $routeExists = ShippingRate::where('shipping_plan_id', $shippingPlanId)
                ->where('origin', $validatedData['origin'])
                ->where('destination', $validatedData['destination'])
                ->exists();

            if (!$routeExists) {
                return response()->json([
                    'message' => 'Route not found. Please double-check the origin and destination you have selected.'
                ], 400);
            }

            // ====== 新版：修改 calculateTotalPrice 返回结构 ======
            $priceDetails = $this->calculateTotalPriceDetailed(
                $validatedData['fuel_surcharge'],
                $validatedData['misc_charge'],
                $validatedData['origin'],
                $validatedData['destination'],
                $validatedData['consignor_id'],
                $validatedData['kg']
            );

            return response()->json([
                'base_price' => number_format($priceDetails['base_price'], 2, '.', ''),
                'misc_charge' => number_format($priceDetails['misc_charge'], 2, '.', ''),
                'estimated_total_price' => number_format($priceDetails['total'], 2, '.', ''),
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
                'manifest_lists.*.misc_charge' => 'nullable|numeric|min:0',
                'manifest_lists.*.fuel_surcharge' => 'nullable|numeric|min:0',
                'manifest_lists.*.total_price' => isset($request->manifest_info_id)
                    ? 'prohibited'
                    : 'required|numeric|min:0'
            ]);

            $userId = Auth::id();
            $maxManifestNo = ManifestInfo::withTrashed()->max('manifest_no');
            $nextManifestNo = $this->getNextManifestNo($maxManifestNo);

            if (!isset($validatedData['manifest_info_id'])) {
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
                $manifestInfo = ManifestInfo::findOrFail($validatedData['manifest_info_id']);
            }

            $warningMessages = [];

            $manifestLists = collect($validatedData['manifest_lists'])->map(function ($list) use ($manifestInfo, $validatedData, &$warningMessages) {
                $existingManifest = ManifestList::where('cn_no', $list['cn_no'])->exists();

                if ($existingManifest) {
                    $warningMessages[] = "CN No: {$list['cn_no']} already exists, the total price will be set to 0";
                    $totalPrice = 0;
                    $basePrice = 0;
                } else {
                    $basePrice = 0;
                    if (isset($validatedData['manifest_info_id'])) {
                        $totalDetails = $this->calculateTotalPriceDetailed(
                            $list['fuel_surcharge'],
                            $list['misc_charge'],
                            $list['origin'],
                            $list['destination'],
                            $list['consignor_id'],
                            $list['kg']
                        );
                        $totalPrice = $totalDetails['total'];
                        $basePrice = $totalDetails['base_price'];
                    } else {
                        $totalPrice = $list['total_price'];
                    }
                }

                // ✅ 正确抓取 misc_charge
                // $miscCharge = $this->getMiscCharge($list['consignor_id'], $list['origin'], $list['destination']);

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
                    'base_price' => number_format($basePrice, 2, '.', ''),
                    'fuel_surcharge' => number_format($list['fuel_surcharge'], 2, '.', ''),
                    'misc_charge' => number_format($list['misc_charge'], 2, '.', ''),
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
    private function getMiscCharge($consignorId, $origin, $destination)
    {
        $client = \App\Models\Client::find($consignorId);
        if (!$client || !$client->shipping_plan_id) {
            return 0.00;
        }

        $shippingRate = DB::table('shipping_rates')
            ->where('shipping_plan_id', $client->shipping_plan_id)
            ->where('origin', $origin)
            ->where('destination', $destination)
            ->first();

        return $shippingRate ? $shippingRate->misc_charge : 0.00;
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


    private function calculateTotalPriceDetailed($fuel_surcharge, $misc_charge, $from, $to, $consignorId, $kg)
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
        $total = $basePrice * $fuel_surcharge + $misc_charge;

        return [
            'base_price' => (float) $basePrice,
            'misc_charge' => (float) $misc_charge,
            'total' => (float) $total,
        ];
    }



    public function searchManifest(Request $request)
    {
        $request->validate([
            'consignor_id' => 'required|integer',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date',
        ]);

        $perPage = $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 20, 50, 100]) ? $perPage : 10;
        $query = DB::table('manifest_lists')
            ->join('manifest_infos', 'manifest_lists.manifest_info_id', '=', 'manifest_infos.id')
            ->where('manifest_lists.consignor_id', $request->consignor_id)
            ->whereNull('manifest_lists.deleted_at')
            ->whereNull('manifest_infos.deleted_at')
            ->select(
                'manifest_infos.manifest_no AS Manifest_No',
                DB::raw("CONCAT(manifest_lists.origin, '-', manifest_lists.destination) AS Description"),
                'manifest_lists.cn_no AS Consignment_Note',
                DB::raw("DATE_FORMAT(manifest_infos.date, '%d-%m-%Y') AS Delivery_Date"),
                'manifest_lists.pcs AS Qty',
                'manifest_lists.total_price AS Total_RM'
            );


        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start_date = $request->start_date . " 00:00:00";
            $end_date = $request->end_date . " 23:59:59";

            $query->whereBetween('manifest_infos.date', [$start_date, $end_date]);
        }

        $manifests = $query->paginate($perPage);

        return response()->json([
            'data' => $manifests->items(),
            'pagination' => [
                'total' => $manifests->total(),
                'per_page' => $manifests->perPage(),
                'current_page' => $manifests->currentPage(),
                'last_page' => $manifests->lastPage(),
                'next_page_url' => $manifests->nextPageUrl(),
                'prev_page_url' => $manifests->previousPageUrl(),
            ],
        ]);
    }
}
