<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('project_activities')
            ->where('status', 'planned')
            ->update(['status' => 'in_progress']);

        /** @var Collection<int, int|string> $ownerIds */
        $ownerIds = DB::table('project_activity_status_options')
            ->select('owner_id')
            ->distinct()
            ->pluck('owner_id');

        foreach ($ownerIds as $ownerId) {
            $hasPlanned = DB::table('project_activity_status_options')
                ->where('owner_id', $ownerId)
                ->where('code', 'planned')
                ->exists();

            if (! $hasPlanned) {
                continue;
            }

            $hasInProgress = DB::table('project_activity_status_options')
                ->where('owner_id', $ownerId)
                ->where('code', 'in_progress')
                ->exists();

            if ($hasInProgress) {
                DB::table('project_activity_status_options')
                    ->where('owner_id', $ownerId)
                    ->where('code', 'planned')
                    ->delete();

                continue;
            }

            DB::table('project_activity_status_options')
                ->where('owner_id', $ownerId)
                ->where('code', 'planned')
                ->update([
                    'code' => 'in_progress',
                    'label' => 'In Progress',
                    'color' => 'warning',
                    'sort_order' => 10,
                    'is_default' => true,
                    'is_open' => true,
                    'is_done' => false,
                    'is_cancelled' => false,
                    'is_running' => true,
                    'updated_at' => now(),
                ]);
        }

        DB::statement("ALTER TABLE project_activities ALTER COLUMN status SET DEFAULT 'in_progress'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE project_activities ALTER COLUMN status SET DEFAULT 'planned'");

        DB::table('project_activities')
            ->where('status', 'in_progress')
            ->update(['status' => 'planned']);
    }
};
