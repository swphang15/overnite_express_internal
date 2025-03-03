<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    /**
     * 获取所有 client
     */
    public function index()
    {
        return response()->json(Client::all());
    }

    /**
     * 注册
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'company_name' => 'required|string|max:255|unique:clients,company_name',
                'email'        => 'required|string|email|max:255|unique:clients,email',
                'password'     => 'required|string|min:6|confirmed',
            ]);

            $client = Client::create([
                'company_name' => $request->company_name,
                'email'        => $request->email,
                'password'     => bcrypt($request->password),
            ]);

            return response()->json([
                'message' => 'User registered successfully!',
                'client'  => $client,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * 登录
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // 允许找回软删除账号
        $client = Client::withTrashed()->where('email', $request->email)->first();

        // 检查账号是否存在 & 密码是否正确
        if (!$client || !Hash::check($request->password, $client->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // 如果账号被软删除，提示账号被停用
        if ($client->trashed()) {
            return response()->json(['message' => 'Account has been deactivated'], 403);
        }

        // 生成 Sanctum token
        $token = $client->createToken('authToken')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user'    => $client,
            'token'   => $token,
        ]);
    }

    /**
     * 获取单个 client (通过 ID)
     */
    public function show($id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        return response()->json($client);
    }

    /**
     * 获取当前登录用户信息
     */
    public function profile()
    {
        $client = Auth::user(); // 获取当前登录用户

        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json($client);
    }

    /**
     * 更新当前登录用户资料（包括可选更新密码）
     */
    public function updateProfile(Request $request)
    {
        $client = Auth::user(); // 获取当前登录用户

        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'company_name' => 'sometimes|string|max:255|unique:clients,company_name,' . $client->id,
            'email'        => 'sometimes|email|max:255|unique:clients,email,' . $client->id,
            'password'     => 'sometimes|string|min:6|confirmed',
        ]);

        if ($request->has('company_name')) {
            $client->company_name = $request->company_name;
        }
        if ($request->has('email')) {
            $client->email = $request->email;
        }
        if ($request->has('password')) {
            // 如果需要不输入旧密码就能改密码，直接用这段
            $client->password = bcrypt($request->password);
        }

        $client->save();

        return response()->json([
            'message' => 'Profile updated successfully!',
            'client'  => $client,
        ]);
    }

    /**
     * 单独修改密码，需要验证旧密码
     */
    public function changePassword(Request $request)
    {
        $client = Auth::user();

        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 要求用户提供旧密码、新密码并确认
        $request->validate([
            'old_password' => 'required|string',
            'password'     => 'required|string|min:6|confirmed',
        ]);

        // 验证旧密码是否正确
        if (!Hash::check($request->old_password, $client->password)) {
            return response()->json(['message' => 'Old password is incorrect'], 400);
        }

        // 更新为新密码
        $client->password = bcrypt($request->password);
        $client->save();

        return response()->json(['message' => 'Password changed successfully!']);
    }
}
