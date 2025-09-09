<?php

namespace App\Http\Controllers;

use App\Services\InvoicePriceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ManifestList;
use Illuminate\Support\Facades\DB;

class InvoicePriceController extends Controller
{
    protected $invoicePriceService;

    public function __construct(InvoicePriceService $invoicePriceService)
    {
        $this->invoicePriceService = $invoicePriceService;
    }

    /**
     * Update existing manifest prices to reflect maximum prices for duplicate cn_no
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateManifestPrices(Request $request): JsonResponse
    {
        $request->validate([
            'consignor_id' => 'required|integer|exists:clients,id'
        ]);

        try {
            $consignorId = $request->input('consignor_id');
            $updatedCount = $this->invoicePriceService->updateExistingManifestPrices($consignorId);

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} manifest records with maximum prices",
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating manifest prices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get processed manifest data with maximum prices for a specific consignor
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getProcessedManifestData(Request $request): JsonResponse
    {
        $request->validate([
            'consignor_id' => 'required|integer|exists:clients,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        try {
            $consignorId = $request->input('consignor_id');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            if ($startDate && $endDate) {
                $processedData = $this->invoicePriceService->processManifestPricesByDateRange(
                    $consignorId, 
                    $startDate, 
                    $endDate
                );
            } else {
                $processedData = $this->invoicePriceService->getUniqueCnNoWithMaxPrices($consignorId);
            }

            return response()->json([
                'success' => true,
                'data' => $processedData,
                'total_count' => $processedData->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving processed manifest data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analysis of duplicate cn_no items and their prices
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getDuplicateAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'consignor_id' => 'required|integer|exists:clients,id'
        ]);

        try {
            $consignorId = $request->input('consignor_id');

            // Get all manifest lists for the consignor
            $manifestLists = ManifestList::where('consignor_id', $consignorId)
                ->whereNull('deleted_at')
                ->get();

            // Group by cn_no and analyze duplicates
            $duplicateAnalysis = [];
            $groupedByCnNo = $manifestLists->groupBy('cn_no');

            foreach ($groupedByCnNo as $cnNo => $items) {
                if ($items->count() > 1) {
                    $prices = $items->pluck('total_price')->filter()->toArray();
                    $maxPrice = max($prices);
                    
                    $duplicateAnalysis[] = [
                        'cn_no' => $cnNo,
                        'duplicate_count' => $items->count(),
                        'prices' => $prices,
                        'max_price' => $maxPrice,
                        'price_difference' => $maxPrice - min($prices),
                        'items' => $items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'manifest_info_id' => $item->manifest_info_id,
                                'total_price' => $item->total_price,
                                'base_price' => $item->base_price,
                                'created_at' => $item->created_at
                            ];
                        })
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'duplicate_analysis' => $duplicateAnalysis,
                'total_duplicates' => count($duplicateAnalysis)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error analyzing duplicates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview what the final export data would look like after price processing
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function previewExportData(Request $request): JsonResponse
    {
        $request->validate([
            'consignor_id' => 'required|integer|exists:clients,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $consignorId = $request->input('consignor_id');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $processedData = $this->invoicePriceService->processManifestPricesByDateRange(
                $consignorId, 
                $startDate, 
                $endDate
            );

            $previewData = $processedData->map(function ($item) {
                return [
                    'cn_no' => $item->cn_no,
                    'total_price' => $item->total_price,
                    'base_price' => $item->base_price,
                    'pcs' => $item->pcs,
                    'kg' => $item->kg,
                    'origin' => $item->origin,
                    'destination' => $item->destination,
                    'manifest_info_id' => $item->manifest_info_id
                ];
            });

            return response()->json([
                'success' => true,
                'preview_data' => $previewData,
                'total_items' => $previewData->count(),
                'total_value' => $previewData->sum('total_price')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating preview: ' . $e->getMessage()
            ], 500);
        }
    }
}
