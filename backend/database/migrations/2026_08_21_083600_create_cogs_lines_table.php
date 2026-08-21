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
        Schema::create('cogs_lines', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->index();
            $table->foreignId('soa_id')->index();
            $table->unsignedInteger('qty');
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('store_name');
            $table->string('item_sku')->index();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cogs_lines');
    }
};
