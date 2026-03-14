<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('color');
            $table->index(['owner_id', 'sort_order']);
        });

        Schema::table('recurring_service_types', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            $table->index(['owner_id', 'sort_order']);
        });

        DB::table('tags')
            ->select('owner_id')
            ->distinct()
            ->pluck('owner_id')
            ->each(function (int $ownerId): void {
                DB::table('tags')
                    ->where('owner_id', $ownerId)
                    ->orderBy('name')
                    ->orderBy('id')
                    ->pluck('id')
                    ->values()
                    ->each(function (int $id, int $index): void {
                        DB::table('tags')
                            ->where('id', $id)
                            ->update(['sort_order' => ($index + 1) * 10]);
                    });
            });

        DB::table('recurring_service_types')
            ->select('owner_id')
            ->distinct()
            ->pluck('owner_id')
            ->each(function (int $ownerId): void {
                DB::table('recurring_service_types')
                    ->where('owner_id', $ownerId)
                    ->orderBy('name')
                    ->orderBy('id')
                    ->pluck('id')
                    ->values()
                    ->each(function (int $id, int $index): void {
                        DB::table('recurring_service_types')
                            ->where('id', $id)
                            ->update(['sort_order' => ($index + 1) * 10]);
                    });
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });

        Schema::table('recurring_service_types', function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
