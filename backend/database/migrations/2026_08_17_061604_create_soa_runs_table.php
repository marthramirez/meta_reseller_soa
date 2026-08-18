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
        Schema::create('soa_runs', function (Blueprint $table) {
            $table->id();
            $table->date('billing_start');
            $table->date('billing_end');
            $table->string('generated_by');
            $table->timestamp('timestamp');
            $table->string('store_name');
            $table->string('seller_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('soa_runs');
    }
};
