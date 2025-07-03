<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\ManifestInfo;
use App\Models\ManifestList;
use App\Models\ShippingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function getCounts()
    {
        $currentYear = Carbon::now()->year;

        // Get all clients
        $clients = Client::select('id', 'name')->get();

        // Get monthly total_price grouped by client and month
        $clientMonthlyData = ManifestList::selectRaw('consignor_id, MONTH(created_at) as month, SUM(total_price) as total_price')
            ->whereYear('created_at', $currentYear)
            ->groupBy('consignor_id', 'month')
            ->get();

        // Initialize result arrays
        $clientMonthlyTotals = [];
        $allMonthlyTotals = array_fill(1, 12, 0);

        // Build each client's monthly total
        foreach ($clients as $client) {
            $monthly = array_fill(1, 12, 0);

            foreach ($clientMonthlyData as $data) {
                if ($data->consignor_id == $client->id) {
                    $monthly[$data->month] = (float) $data->total_price;
                    $allMonthlyTotals[$data->month] += (float) $data->total_price;
                }
            }

            $clientMonthlyTotals[$client->name] = $monthly;
        }

        // Prepend "ALL" totals
        $clientMonthlyTotals = ['ALL' => $allMonthlyTotals] + $clientMonthlyTotals;

        return response()->json([
            'clients' => Client::count(),
            'manifest_infos' => ManifestInfo::count(),
            'shipping_plans' => ShippingPlan::count(),
            'users' => User::count(),
            'monthly_total_price' => $clientMonthlyTotals,
        ]);
    }
}
