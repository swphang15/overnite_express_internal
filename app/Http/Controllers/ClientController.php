<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    // 保护 API，所有方法都需要 Token 认证
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // 获取所有 Clients
    public function index()
    {
        $clients = Client::with('shippingPlan')->get();

        return response()->json($clients->map(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'shipping_plan_id' => $client->shipping_plan_id,
                'plan_name' => $client->shippingPlan ? $client->shippingPlan->plan_name : null, // 确保返回
                'created_at' => $client->created_at,
                'updated_at' => $client->updated_at,
                'deleted_at' => $client->deleted_at,
            ];
        }), 200);
    }


    // 创建新的 Client
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:clients,name',
            'shipping_plan_id' => 'required|exists:shipping_plans,id',
        ]);

        $client = Client::create([
            'name' => $request->name,
            'shipping_plan_id' => $request->shipping_plan_id,
        ]);

        return response()->json($client, 201);
    }


    public function show($id)
    {
        $client = Client::with('shippingPlan')->find($id);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'shipping_plan_id' => $client->shipping_plan_id,
            'plan_name' => $client->shippingPlan ? $client->shippingPlan->plan_name : null, // 确保 plan_name 正确返回
            'created_at' => $client->created_at,
            'updated_at' => $client->updated_at,
            'deleted_at' => $client->deleted_at,
        ], 200);
    }


    // 更新 Client
    public function update(Request $request, $id)
    {
        $client = Client::find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'shipping_plan_id' => 'required|exists:shipping_plans,id',
        ]);

        $client->update([
            'name' => $request->name,
            'shipping_plan_id' => $request->shipping_plan_id,
        ]);

        return response()->json($client, 200);
    }


    // 软删除 Client
    public function destroy($id)
    {
        $client = Client::find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        $client->delete();
        return response()->json(['message' => 'Client deleted'], 200);
    }

    // 获取被软删除的 Clients
    public function trashed()
    {
        $clients = Client::onlyTrashed()->get();
        return response()->json($clients, 200);
    }

    // 恢复被软删除的 Client
    public function restore($id)
    {
        $client = Client::onlyTrashed()->find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found in trash'], 404);
        }

        $client->restore();
        return response()->json(['message' => 'Client restored'], 200);
    }
}
