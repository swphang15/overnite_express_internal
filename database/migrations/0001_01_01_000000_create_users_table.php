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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // , ['superadmin', 'admin', 'user']
            $table->string('role')->default('user'); // 角色字段
            $table->string('name'); // 添加用户的姓名
            $table->string('email');
            $table->string('password');
            $table->timestamps();
            $table->softDeletes(); // 添加 softDeletes 字段
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
