<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_plans', function (Blueprint $table) {
            $table->id(); // 主键
            $table->string('plan_name'); // 方案名称
            $table->timestamps(); // created_at 和 updated_at
            $table->softDeletes(); // 软删除字段 deleted_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_plans');
    }
};
