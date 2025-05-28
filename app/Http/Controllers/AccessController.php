<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AccessController extends Controller
{
    public function verifyAdminPassword(Request $request)
    {
        $user = $request->user(); // 当前已登录用户

        // 如果是 admin，直接通过
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Access granted (admin)'], 200);
        }

        // 否则是普通用户，必须输入 admin 密码
        $adminPassword = $request->input('password');

        if (!$adminPassword) {
            return response()->json(['message' => 'Admin password is required'], 403);
        }

        // 检查是否有 admin 密码匹配
        $admins = User::where('role', 'admin')->get();

        $matched = $admins->first(function ($admin) use ($adminPassword) {
            return Hash::check($adminPassword, $admin->password);
        });

        if (!$matched) {
            return response()->json(['message' => 'Invalid admin password'], 403);
        }

        return response()->json(['message' => 'Access granted'], 200);
    }
}
