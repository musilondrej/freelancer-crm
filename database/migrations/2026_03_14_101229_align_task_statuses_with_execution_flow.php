<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tasks')
            ->where('status', TaskStatus::Planned->value)
            ->update(['status' => TaskStatus::InProgress->value]);

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

        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('status', 64)->default(TaskStatus::InProgress->value)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('status', 64)->default(TaskStatus::Planned->value)->change();
        });

        DB::table('tasks')
            ->where('status', TaskStatus::InProgress->value)
            ->update(['status' => TaskStatus::Planned->value]);
    }
};
