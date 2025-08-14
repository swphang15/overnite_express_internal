<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ManifestInfo;
use App\Models\ManifestList;

class InitializeManifestListSortOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manifest:init-sort-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize sort_order values for existing manifest lists based on creation time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Initializing sort_order for manifest lists...');

        $manifestInfos = ManifestInfo::with('manifestLists')->get();
        $totalUpdated = 0;

        foreach ($manifestInfos as $manifestInfo) {
            $manifestLists = ManifestList::where('manifest_info_id', $manifestInfo->id)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($manifestLists as $index => $manifestList) {
                if ($manifestList->sort_order == 0) {
                    $manifestList->update(['sort_order' => $index + 1]);
                    $totalUpdated++;
                }
            }
        }

        $this->info("Successfully updated sort_order for {$totalUpdated} manifest lists.");
        return 0;
    }
}
