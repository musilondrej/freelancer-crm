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
        if (Schema::hasColumn('time_entries', 'hourly_rate_override')) {
            return;
        }

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->decimal('hourly_rate_override', 10, 2)->nullable()->after('is_billable_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('time_entries', 'hourly_rate_override')) {
            return;
        }

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropColumn('hourly_rate_override');
        });
    }
};
