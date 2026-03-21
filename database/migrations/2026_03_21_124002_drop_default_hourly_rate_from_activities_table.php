<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activities', 'default_hourly_rate')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropColumn('default_hourly_rate');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('activities', 'default_hourly_rate')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            $table->decimal('default_hourly_rate', 10, 2)->nullable()->after('description');
        });
    }
};
