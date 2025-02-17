<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('manifests', function (Blueprint $table) {
            // 删除原来的 consignor 和 consignee
            $table->dropColumn('consignor');
            $table->dropColumn('consignee');

            // 添加新的 consignor_id 和 consignee_id 外键
            $table->foreignId('consignor_id')->nullable()->constrained('companies')->onDelete('set null');
            $table->foreignId('consignee_id')->nullable()->constrained('companies')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('manifests', function (Blueprint $table) {
            // 回滚时移除外键
            $table->dropForeign(['consignor_id']);
            $table->dropForeign(['consignee_id']);
            $table->dropColumn(['consignor_id', 'consignee_id']);

            // 重新添加原来的 consignor 和 consignee
            $table->string('consignor');
            $table->string('consignee');
        });
    }
};
