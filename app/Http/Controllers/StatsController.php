<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\ManifestInfo;
use App\Models\ShippingPlan;
use App\Models\User;

class StatsController extends Controller
{
    public function getCounts()
    {
        return response()->json([
            'clients' => Client::count(), 
            'manifest_infos' => ManifestInfo::count(), 
            'shipping_plans' => ShippingPlan::count(), 
            'users' => User::count()
        ]);
    }
}
