<?php



use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id(); // 主键
            $table->unsignedBigInteger('shipping_plan_id'); // 必须是 UNSIGNED，确保与 shipping_plans.id 类型匹配
            $table->foreign('shipping_plan_id')->references('id')->on('shipping_plans')->onDelete('cascade');
            $table->string('origin');
            $table->string('destination');
            $table->decimal('minimum_price', 8, 2);
            $table->decimal('maximum_weight', 8, 2);
            $table->decimal('additional_price_per_kg', 8, 2);
            $table->decimal('misc_charge', 10, 2)->default(0);
            $table->timestamps(); // created_at 和 updated_at
            $table->softDeletes(); // 软删除字段 deleted_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
