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
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soa_id')->index();
            $table->string('order_id')->index();
            $table->string('item_sku')->index();
            $table->unsignedInteger('qty');
            $table->boolean('is_freebie')->default(false);
            $table->string('order_status')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->string('store_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
