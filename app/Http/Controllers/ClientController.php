<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        return response()->json(Client::all());
    }
    public function register(Request $request)
{
    $request->validate([
        'company_name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:clients',
        'password' => 'required|string|min:6|confirmed',
    ]);

    // ✅ 将 create() 的返回值存入变量
    $client = Client::create([
        'company_name' => $request->company_name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
    ]);

    return response()->json([
        'message' => 'User registered successfully!',
        'client' => $client, // ✅ 现在 $client 变量已经正确定义
    ], 201);
}

}
