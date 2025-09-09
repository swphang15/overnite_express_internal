<?php

namespace App\Services;

use App\Models\ManifestList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class InvoicePriceService
{
    /**
     * Process manifest lists and apply maximum price logic for duplicate cn_no
     * 
     * @param Collection $manifestLists
     * @return Collection
     */
    public function processManifestPrices(Collection $manifestLists): Collection
    {
        // Group by cn_no to find duplicates
        $groupedByCnNo = $manifestLists->groupBy('cn_no');
        
        $processedLists = collect();
        
        foreach ($groupedByCnNo as $cnNo => $items) {
            if ($items->count() > 1) {
                // Multiple items with same cn_no found
                $maxPriceItem = $this->getItemWithMaxPrice($items);
                $processedLists->push($maxPriceItem);
            } else {
                // Single item, no processing needed
                $processedLists->push($items->first());
            }
        }
        
        return $processedLists;
    }
    
    /**
     * Get the item with maximum total_price from a collection of items
     * 
     * @param Collection $items
     * @return ManifestList
     */
    private function getItemWithMaxPrice(Collection $items): ManifestList
    {
        $maxPriceItem = $items->first();
        $maxPrice = floatval($maxPriceItem->total_price ?? 0);
        
        foreach ($items as $item) {
            $currentPrice = floatval($item->total_price ?? 0);
            if ($currentPrice > $maxPrice) {
                $maxPrice = $currentPrice;
                $maxPriceItem = $item;
            }
        }
        
        return $maxPriceItem;
    }
    
    /**
     * Process manifest lists by consignor and date range with maximum price logic
     * 
     * @param int $consignorId
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function processManifestPricesByDateRange(int $consignorId, string $startDate, string $endDate): Collection
    {
        // Get manifest_info_ids based on the date range
        $manifestInfoIds = DB::table('manifest_infos')
            ->whereBetween('date', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay()
            ])
            ->whereNull('deleted_at')
            ->pluck('id');
        
        // Get manifest_lists data
        $manifestData = DB::table('manifest_lists')
            ->where('consignor_id', $consignorId)
            ->whereIn('manifest_info_id', $manifestInfoIds)
            ->whereNull('deleted_at')
            ->get();
        
        // Convert to ManifestList models for consistency
        $manifestLists = $manifestData->map(function ($item) {
            return new ManifestList((array) $item);
        });
        
        return $this->processManifestPrices($manifestLists);
    }
    
    /**
     * Get unique cn_no items with maximum prices for a specific consignor
     * 
     * @param int $consignorId
     * @return Collection
     */
    public function getUniqueCnNoWithMaxPrices(int $consignorId): Collection
    {
        $manifestLists = ManifestList::where('consignor_id', $consignorId)
            ->whereNull('deleted_at')
            ->get();
            
        return $this->processManifestPrices($manifestLists);
    }
    
    /**
     * Update existing manifest lists to reflect maximum prices
     * This method can be used to clean up existing data
     * 
     * @param int $consignorId
     * @return int Number of records updated
     */
    public function updateExistingManifestPrices(int $consignorId): int
    {
        $uniqueItems = $this->getUniqueCnNoWithMaxPrices($consignorId);
        $updatedCount = 0;
        
        foreach ($uniqueItems as $item) {
            // Update all items with same cn_no to have the maximum price
            $updateCount = ManifestList::where('consignor_id', $consignorId)
                ->where('cn_no', $item->cn_no)
                ->whereNull('deleted_at')
                ->update([
                    'total_price' => $item->total_price,
                    'base_price' => $item->base_price
                ]);
            
            $updatedCount += $updateCount;
        }
        
        return $updatedCount;
    }
}
