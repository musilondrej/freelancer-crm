<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Merge project-scoped activities into global ones.
        // For each project-scoped activity, find or create a global (project_id IS NULL)
        // activity with the same owner_id and name. Reassign FK references.
        $projectActivities = DB::table('activities')
            ->whereNotNull('project_id')
            ->whereNull('deleted_at')
            ->orderBy('owner_id')
            ->orderBy('name')
            ->get();

        foreach ($projectActivities as $projectActivity) {
            $globalActivity = DB::table('activities')
                ->where('owner_id', $projectActivity->owner_id)
                ->whereNull('project_id')
                ->whereNull('deleted_at')
                ->whereRaw('lower(name) = ?', [mb_strtolower((string) $projectActivity->name)])
                ->first();

            if ($globalActivity !== null) {
                // Reassign tasks and backlog_items to the global activity
                DB::table('tasks')
                    ->where('activity_id', $projectActivity->id)
                    ->update(['activity_id' => $globalActivity->id]);

                DB::table('backlog_items')
                    ->where('activity_id', $projectActivity->id)
                    ->update(['activity_id' => $globalActivity->id]);

                // Soft-delete the project-scoped duplicate
                DB::table('activities')
                    ->where('id', $projectActivity->id)
                    ->update(['deleted_at' => now()]);
            } else {
                // No global equivalent — promote this activity to global
                DB::table('activities')
                    ->where('id', $projectActivity->id)
                    ->update(['project_id' => null]);
            }
        }

        // Step 2: Drop constraints, columns, and add new unique
        Schema::table('activities', function (Blueprint $table): void {
            // Drop composite FK using columns (SQLite-compatible)
            $table->dropForeign(['owner_id', 'project_id']);
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropUnique('activities_owner_project_name_unique');
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropIndex('activities_owner_id_project_id_index');
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropColumn(['project_unique_scope_key', 'project_id']);
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->unique(['owner_id', 'name_unique_key'], 'activities_owner_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropUnique('activities_owner_name_unique');

            $table->foreignId('project_id')->nullable()->after('owner_id');

            $table->unsignedBigInteger('project_unique_scope_key')
                ->storedAs('coalesce(project_id, 0)')
                ->after('sort_order');

            $table->unique(
                ['owner_id', 'project_unique_scope_key', 'name_unique_key'],
                'activities_owner_project_name_unique',
            );
            $table->index(['owner_id', 'project_id']);

            $table->foreign(['owner_id', 'project_id'])
                ->references(['owner_id', 'id'])
                ->on('projects')
                ->cascadeOnDelete();
        });
    }
};
