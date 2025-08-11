<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('manifest_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // 先创建 user_id
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade'); // 添加外键
            $table->date('date');
            $table->string('awb_no');
            $table->string('to');
            $table->string('from');
            $table->string('flt')->nullable();
            $table->string('manifest_no');
            $table->boolean('readed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('manifest_infos');
    }
};
