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
        if (! Schema::hasColumn('time_entries', 'project_id')) {
            Schema::table('time_entries', function (Blueprint $table): void {
                $table->foreignId('project_id')->nullable()->after('owner_id')->constrained('projects')->cascadeOnDelete();
            });
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('
                UPDATE time_entries
                INNER JOIN tasks ON tasks.id = time_entries.task_id
                SET time_entries.project_id = tasks.project_id
            ');
        } else {
            DB::statement('
                UPDATE time_entries
                SET project_id = tasks.project_id
                FROM tasks
                WHERE time_entries.task_id = tasks.id
            ');
        }

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable(false)->change();
            $table->foreignId('task_id')->nullable()->change();
        });

        if (! $this->hasProjectEndedAtIndex()) {
            Schema::table('time_entries', function (Blueprint $table): void {
                $table->index(['project_id', 'ended_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasProjectEndedAtIndex()) {
            Schema::table('time_entries', function (Blueprint $table): void {
                $table->dropIndex('time_entries_project_id_ended_at_index');
            });
        }

        if (Schema::hasColumn('time_entries', 'project_id')) {
            Schema::table('time_entries', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('project_id');
            });
        }

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('task_id')->nullable(false)->change();
        });
    }

    private function hasProjectEndedAtIndex(): bool
    {
        return collect(Schema::getIndexes('time_entries'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === 'time_entries_project_id_ended_at_index');
    }
};
