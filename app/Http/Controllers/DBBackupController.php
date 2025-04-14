<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;  // 确保引入 Log 类
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class DBBackupController extends Controller
{
    public function download(Request $request)
    {
        // 验证请求参数
        $request->validate([
            'password' => 'required|string',
        ]);

        // 获取当前用户
        $user = Auth::user();

        // 只有管理员才允许备份
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized: Admins only.'], 403);
        }

        // 验证密码是否正确
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid password.'], 401);
        }

        // 获取数据库配置
        $dbHost = config('database.connections.mysql.host');
        $dbPort = config('database.connections.mysql.port');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        // 构造文件名与保存路径
        $fileName = 'backup_' . now()->format('Y_m_d_His') . '.sql';
        $filePath = storage_path('app/' . $fileName);

        // 构造 mysqldump 命令
        $command = [
            'mysqldump',
            '--user=' . $dbUser,
            '--password=' . $dbPass,
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            '--routines',           // 导出存储过程和函数
            '--skip-comments',      // 跳过注释
            '--complete-insert',    // 更完整的 INSERT 语句
            '--skip-add-locks',     // 更适合导入
            $dbName,
        ];

        // 执行 mysqldump 命令
        $process = new Process($command);
        $process->run();

        // 记录 mysqldump 命令的输出和错误信息
        Log::debug("mysqldump output: " . $process->getOutput());
        Log::debug("mysqldump error output: " . $process->getErrorOutput());

        if (!$process->isSuccessful()) {
            return response()->json(['message' => 'Database backup failed.'], 500);
        }

        // 保存 SQL 文件
        file_put_contents($filePath, $process->getOutput());

        // 返回下载响应并删除临时文件
        return response()->download($filePath)->deleteFileAfterSend(true);
    }
}
