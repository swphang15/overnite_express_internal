<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id(); // 主键
            $table->unsignedBigInteger('shipping_plan_id'); // 必须是 UNSIGNED，确保与 shipping_plans.id 类型匹配
            $table->foreign('shipping_plan_id')->references('id')->on('shipping_plans')->onDelete('cascade');
            $table->string('name'); // 客户名称
            $table->timestamps(); // created_at 和 updated_at
            $table->softDeletes(); // 软删除字段 deleted_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
