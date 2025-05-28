<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;  // 确保引入 Log 类
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use PDO;

class DBBackupController extends Controller
{
    public function downloadBackupWithPDO()
    {
        $dbConfig = config('database.connections.mysql');

        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};port={$dbConfig['port']}";
        $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'"
        ]);

        $sqlDump = "-- Backup of {$dbConfig['database']} generated on " . now()->toDateTimeString() . "\n\n";

        // 获取所有表名
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // DROP 语句
            $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";

            // CREATE TABLE 结构
            $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $sqlDump .= $createStmt['Create Table'] . ";\n\n";

            // 插入数据
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $values = array_map(function ($value) use ($pdo) {
                    if (is_null($value)) return "NULL";
                    return $pdo->quote($value);
                }, $row);

                $sqlDump .= "INSERT INTO `$table` VALUES (" . implode(", ", $values) . ");\n";
            }

            $sqlDump .= "\n\n";
        }

        // 保存为文件
        $fileName = 'backup_' . now()->format('Y_m_d_His') . '.sql';
        $filePath = storage_path("app/$fileName");
        file_put_contents($filePath, $sqlDump);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }
}
