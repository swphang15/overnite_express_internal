<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
            return response()->json($user); // 直接返回用户对象
        }

        $users = User::where('role', '!=', 'superadmin')->get(); // 获取所有非 superadmin 用户
        return response()->json($users);
    }


    /**
     * 更新用户信息（仅限 superadmin）
     */
    public function updateProfile(Request $request)
    {
        // 确保用户已登录
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 验证 name 和 email（忽略当前用户自己的 email）
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id)
            ],
        ], [
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Invalid email format.',
            'email.unique' => 'This email is already in use.',
        ]);

        // 更新用户信息
        $user->update($validatedData);

        return response()->json(['message' => 'Profile updated successfully.', 'user' => $user]);
    }

    /**
     * 更新密码
     */
    public function updatePassword(Request $request)
    {
        // 确保用户已登录
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 验证密码字段
        $validatedData = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'Current password is required.',
            'password.required' => 'New password is required.',
            'password.min' => 'New password must be at least 6 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        // 检查当前密码是否正确
        if (!Hash::check($validatedData['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 403);
        }

        // 更新密码
        $user->update([
            'password' => Hash::make($validatedData['password']),
        ]);

        return response()->json(['message' => 'Password updated successfully.']);
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
