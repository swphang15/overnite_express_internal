<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * 创建用户（仅限 superadmin）
     */
    public function createUser(Request $request)
    {
        // 确保调用者是 superadmin
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:admin,user',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json(['message' => 'User created successfully', 'user' => $user]);
    }

    /**
     * 获取所有用户 或 获取单个用户（仅限 superadmin）
     */
    public function readUser(Request $request, $id = null)
    {
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($id) {
            $user = User::find($id);
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }
            return response()->json(['user' => $user]);
        }

        $users = User::where('role', '!=', 'superadmin')->get(); // 过滤 superadmin
        return response()->json(['users' => $users]);
    }

    /**
     * 更新用户信息（仅限 superadmin）
     */
    public function updateUser(Request $request, $id)
    {
        // 获取当前登录的用户
        $currentUser = $request->user();

        // 确保当前用户是 superadmin
        if ($currentUser->role !== 'superadmin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 查找要修改的用户
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // 防止修改 superadmin
        if ($user->role === 'superadmin') {
            return response()->json(['message' => 'Cannot modify superadmin'], 403);
        }

        // 验证请求数据
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|min:6',
            'role' => 'sometimes|in:admin,user',
        ]);

        // 更新数据
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }
        if ($request->has('role')) {
            $user->role = $request->role;
        }

        $user->save();

        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    /**
     * 删除用户（仅限 superadmin）
     */
    public function deleteUser(Request $request, $id)
    {
        // 确保当前用户是 superadmin
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 查找要删除的用户
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // 防止删除 superadmin
        if ($user->role === 'superadmin') {
            return response()->json(['message' => 'Cannot delete superadmin'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
