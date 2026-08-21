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
        Schema::table('soa_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('soa_runs', 'total_net_remittance')) {
                $table->decimal('total_net_remittance', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('soa_runs', 'total_cogs')) {
                $table->decimal('total_cogs', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('soa_runs', 'total_ds_fee')) {
                $table->decimal('total_ds_fee', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('soa_runs', 'total_net_pay')) {
                $table->decimal('total_net_pay', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('soa_runs', 'store_count')) {
                $table->unsignedInteger('store_count')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('soa_runs', function (Blueprint $table) {
            $table->dropColumn([
                'total_net_remittance',
                'total_cogs',
                'total_ds_fee',
                'total_net_pay',
                'store_count',
            ]);
        });
    }
};
