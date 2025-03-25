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
        $user = $request->user();
        if (!in_array($user->role, ['superadmin', 'admin'])) {
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
        $response = null;

        if (!in_array($request->user()->role, ['admin', 'superadmin'])) {
            $response = response()->json(['message' => 'Forbidden'], 403);
        } elseif ($id) {
            $user = User::find($id);
            $response = $user
                ? response()->json($user)
                : response()->json(['message' => 'User not found'], 404);
        } else {
            $users = User::whereNotIn('role', ['superadmin'])->get(); // Exclude only superadmin
            $response = response()->json($users);
        }

        return $response;
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['superadmin', 'admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($user->id)
            ],
        ]);

        $user->update($validatedData);

        return response()->json(['message' => 'Profile updated successfully.', 'user' => $user]);
    }

    public function updateUser(Request $request, $id = null)
    {
        $authUser = $request->user();

        if (!in_array($authUser->role, ['superadmin', 'admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // If no ID is provided, update the authenticated user
        $user = $id ? User::find($id) : $authUser;

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => 'sometimes|string|in:superadmin,admin,user',
        ]);

        $user->update($validatedData);

        return response()->json(['message' => 'User updated successfully.', 'user' => $user]);
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
        $user = $request->user();
        if (!in_array($user->role, ['superadmin', 'admin'])) {
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
