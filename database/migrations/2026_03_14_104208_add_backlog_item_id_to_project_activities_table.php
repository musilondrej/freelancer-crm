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
        Schema::table('worklogs', function (Blueprint $table): void {
            $table->foreignId('backlog_item_id')
                ->nullable()
                ->after('activity_id')
                ->constrained('backlog_items')
                ->nullOnDelete();

            $table->index(['owner_id', 'backlog_item_id']);
        });

        DB::table('backlog_items')
            ->whereNotNull('converted_to_worklog_id')
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    DB::table('worklogs')
                        ->where('id', $item->converted_to_worklog_id)
                        ->whereNull('backlog_item_id')
                        ->update([
                            'backlog_item_id' => $item->id,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('worklogs', function (Blueprint $table): void {
            $table->dropIndex('worklogs_owner_id_backlog_item_id_index');
            $table->dropConstrainedForeignId('backlog_item_id');
        });
    }
};
