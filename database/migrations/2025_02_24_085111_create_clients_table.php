<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('role')->default('client'); // 默认 role 是 client
            $table->string('company_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes(); // 添加 softDeletes 字段
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};
