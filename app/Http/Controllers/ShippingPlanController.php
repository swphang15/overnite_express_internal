<?php

namespace App\Http\Controllers;

use App\Models\ShippingPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShippingPlanController extends Controller
{
    // 保护 API，所有方法都需要 Token 认证
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // 获取所有 Shipping Plans
    public function index()
    {
        return response()->json(ShippingPlan::all(), 200);
    }

    // 创建新的 Shipping Plan
    public function store(Request $request)
{
    $request->validate([
        'plan_name' => 'required|string|max:255|unique:shipping_plans,plan_name',
    ]);

    $plan = ShippingPlan::create([
        'plan_name' => $request->plan_name,
    ]);

    return response()->json($plan, 201);
}


    // 获取单个 Shipping Plan
    public function show($id)
    {
        $plan = ShippingPlan::find($id);
        if (!$plan) {
            return response()->json(['message' => 'Shipping Plan not found'], 404);
        }

        return response()->json($plan, 200);
    }

    // 更新 Shipping Plan
    public function update(Request $request, $id)
{
    $plan = ShippingPlan::find($id);
    if (!$plan) {
        return response()->json(['message' => 'Shipping Plan not found'], 404);
    }

    $request->validate([
        'plan_name' => "required|string|max:255|unique:shipping_plans,plan_name,{$id}",
    ]);

    $plan->update([
        'plan_name' => $request->plan_name,
    ]);

    return response()->json($plan, 200);
}


    // 软删除 Shipping Plan
    public function destroy($id)
    {
        $plan = ShippingPlan::find($id);
        if (!$plan) {
            return response()->json(['message' => 'Shipping Plan not found'], 404);
        }

        $plan->delete();
        return response()->json(['message' => 'Shipping Plan deleted'], 200);
    }

    // 获取被软删除的 Shipping Plans
    public function trashed()
    {
        $plans = ShippingPlan::onlyTrashed()->get();
        return response()->json($plans, 200);
    }

    // 恢复被软删除的 Shipping Plan
    public function restore($id)
    {
        $plan = ShippingPlan::onlyTrashed()->find($id);
        if (!$plan) {
            return response()->json(['message' => 'Shipping Plan not found in trash'], 404);
        }

        $plan->restore();
        return response()->json(['message' => 'Shipping Plan restored'], 200);
    }
}
