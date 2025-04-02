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
        // 获取今年年份
        $currentYear = Carbon::now()->year;

        // 获取 1-12 月的总价
        $monthlyTotalPrice = ManifestList::selectRaw('MONTH(created_at) as month, SUM(total_price) as total_price')
            ->whereYear('created_at', $currentYear)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total_price', 'month')
            ->toArray();

        // 确保返回 1-12 月份，即使没有数据也填充 0
        $monthlyTotals = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthlyTotals[$month] = $monthlyTotalPrice[$month] ?? 0;
        }

        return response()->json([
            'clients' => Client::count(),
            'manifest_infos' => ManifestInfo::count(),
            'shipping_plans' => ShippingPlan::count(),
            'users' => User::count(),
            'monthly_total_price' => $monthlyTotals
        ]);
    }
}
