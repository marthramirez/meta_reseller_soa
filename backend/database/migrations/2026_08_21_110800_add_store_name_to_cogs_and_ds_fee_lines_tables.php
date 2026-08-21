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
        if (! Schema::hasColumn('cogs_lines', 'store_name')) {
            Schema::table('cogs_lines', function (Blueprint $table) {
                $table->string('store_name')->default('');
            });
        }

        if (! Schema::hasColumn('ds_fee_lines', 'store_name')) {
            Schema::table('ds_fee_lines', function (Blueprint $table) {
                $table->string('store_name')->default('');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cogs_lines', function (Blueprint $table) {
            $table->dropColumn('store_name');
        });

        Schema::table('ds_fee_lines', function (Blueprint $table) {
            $table->dropColumn('store_name');
        });
    }
};
