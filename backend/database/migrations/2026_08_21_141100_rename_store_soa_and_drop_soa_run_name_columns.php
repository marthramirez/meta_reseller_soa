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
        if (Schema::hasTable('store_soa') && ! Schema::hasTable('store_soa_runs')) {
            Schema::rename('store_soa', 'store_soa_runs');
        }

        Schema::table('soa_runs', function (Blueprint $table) {
            if (Schema::hasColumn('soa_runs', 'store_name')) {
                $table->dropColumn('store_name');
            }

            if (Schema::hasColumn('soa_runs', 'seller_name')) {
                $table->dropColumn('seller_name');
            }

            if (Schema::hasColumn('soa_runs', 'timestamp')) {
                $table->dropColumn('timestamp');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('store_soa_runs') && ! Schema::hasTable('store_soa')) {
            Schema::rename('store_soa_runs', 'store_soa');
        }
    }
};
