<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InvoicePriceService;
use App\Models\Client;

class ProcessInvoicePrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:process-prices {consignor_id} {--update : Update existing records with maximum prices} {--analyze : Analyze duplicate entries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process invoice prices for duplicate cargo codes (cn_no)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $consignorId = $this->argument('consignor_id');
        $shouldUpdate = $this->option('update');
        $shouldAnalyze = $this->option('analyze');

        // Verify consignor exists
        $consignor = Client::find($consignorId);
        if (!$consignor) {
            $this->error("Consignor with ID {$consignorId} not found!");
            return 1;
        }

        $this->info("Processing prices for consignor: {$consignor->name} (ID: {$consignorId})");

        $service = new InvoicePriceService();

        if ($shouldAnalyze) {
            $this->analyzeDuplicates($service, $consignorId);
        }

        if ($shouldUpdate) {
            $this->updatePrices($service, $consignorId);
        }

        // Always show processed data
        $this->showProcessedData($service, $consignorId);

        $this->info('Price processing completed successfully!');
        return 0;
    }

    private function analyzeDuplicates(InvoicePriceService $service, int $consignorId)
    {
        $this->info('Analyzing duplicate entries...');

        try {
            $manifestLists = \App\Models\ManifestList::where('consignor_id', $consignorId)
                ->whereNull('deleted_at')
                ->get();

            $groupedByCnNo = $manifestLists->groupBy('cn_no');
            $duplicates = $groupedByCnNo->filter(function ($items) {
                return $items->count() > 1;
            });

            if ($duplicates->isEmpty()) {
                $this->info('No duplicate entries found.');
                return;
            }

            $this->info("Found {$duplicates->count()} cargo codes with duplicate entries:");

            foreach ($duplicates as $cnNo => $items) {
                $prices = $items->pluck('total_price')->filter()->toArray();
                $maxPrice = max($prices);
                $minPrice = min($prices);
                $difference = $maxPrice - $minPrice;

                $this->line("  - {$cnNo}: {$items->count()} entries, prices: " . implode(', ', $prices));
                $this->line("    Max: {$maxPrice}, Min: {$minPrice}, Difference: {$difference}");
            }

        } catch (\Exception $e) {
            $this->error("Error analyzing duplicates: " . $e->getMessage());
        }
    }

    private function updatePrices(InvoicePriceService $service, int $consignorId)
    {
        $this->info('Updating existing manifest prices...');

        try {
            $updatedCount = $service->updateExistingManifestPrices($consignorId);
            $this->info("Successfully updated {$updatedCount} manifest records with maximum prices.");
        } catch (\Exception $e) {
            $this->error("Error updating prices: " . $e->getMessage());
        }
    }

    private function showProcessedData(InvoicePriceService $service, int $consignorId)
    {
        $this->info('Showing processed manifest data...');

        try {
            $processedData = $service->getUniqueCnNoWithMaxPrices($consignorId);

            if ($processedData->isEmpty()) {
                $this->info('No manifest data found for this consignor.');
                return;
            }

            $this->info("Total unique cargo codes: {$processedData->count()}");

            $headers = ['CN No', 'Total Price', 'Base Price', 'PCS', 'KG', 'Origin', 'Destination'];
            $rows = [];

            foreach ($processedData as $item) {
                $rows[] = [
                    $item->cn_no,
                    $item->total_price ?? '0.00',
                    $item->base_price ?? '0.00',
                    $item->pcs ?? '0',
                    $item->kg ?? '0',
                    $item->origin ?? 'N/A',
                    $item->destination ?? 'N/A'
                ];
            }

            $this->table($headers, $rows);

            $totalValue = $processedData->sum('total_price');
            $this->info("Total value: {$totalValue}");

        } catch (\Exception $e) {
            $this->error("Error showing processed data: " . $e->getMessage());
        }
    }
}
