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
        Schema::create('store_soa_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soa_id')->index();
            $table->string('store_name');
            $table->decimal('net_remittance', 12, 2)->default(0);
            $table->decimal('total_cogs', 12, 2)->default(0);
            $table->decimal('total_ds_fee', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['soa_id', 'store_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_soa_runs');
    }
};
