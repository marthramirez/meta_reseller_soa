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
        Schema::table('cogs_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('cogs_lines', 'item_sku')) {
                $table->string('item_sku')->default('')->index();
            }

            if (! Schema::hasColumn('cogs_lines', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable();
            }

            if (! Schema::hasColumn('cogs_lines', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();
            }
        });

        Schema::table('ds_fee_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('ds_fee_lines', 'item_sku')) {
                $table->string('item_sku')->default('')->index();
            }

            if (! Schema::hasColumn('ds_fee_lines', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable();
            }

            if (! Schema::hasColumn('ds_fee_lines', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cogs_lines', function (Blueprint $table) {
            $table->dropColumn(['item_sku', 'shipped_at', 'delivered_at']);
        });

        Schema::table('ds_fee_lines', function (Blueprint $table) {
            $table->dropColumn(['item_sku', 'shipped_at', 'delivered_at']);
        });
    }
};
