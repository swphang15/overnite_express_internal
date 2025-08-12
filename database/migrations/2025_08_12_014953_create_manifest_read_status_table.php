<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('manifest_read_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('manifest_info_id');
            $table->timestamp('read_at');
            $table->timestamps();

            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('manifest_info_id')->references('id')->on('manifest_infos')->onDelete('cascade');
            
            // 确保每个用户对每个manifest只能有一条已读记录
            $table->unique(['user_id', 'manifest_info_id']);
            
            // 索引优化查询性能
            $table->index(['user_id', 'manifest_info_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_read_status');
    }
};
