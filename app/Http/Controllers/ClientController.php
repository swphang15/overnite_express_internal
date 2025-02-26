<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    // 🔥 允许找回软删除账号
    $client = Client::withTrashed()->where('email', $request->email)->first();

    // 🔥 检查账号是否存在 & 密码是否正确
    if (!$client || !Hash::check($request->password, $client->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // 🔥 如果账号被软删除，提示账号被停用
    if ($client->trashed()) {
        return response()->json(['message' => 'Account has been deactivated'], 403);
    }

    // ✅ 生成 Sanctum token
    $token = $client->createToken('authToken')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'user' => $client,
        'token' => $token,
    ]);
}

}
