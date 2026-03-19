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
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('owner_id')->constrained('projects')->cascadeOnDelete();
        });

        DB::statement('
            UPDATE time_entries
            SET project_id = tasks.project_id
            FROM tasks
            WHERE time_entries.task_id = tasks.id
        ');

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable(false)->change();
            $table->foreignId('task_id')->nullable()->change();
            $table->index(['project_id', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropIndex('time_entries_project_id_ended_at_index');
            $table->dropConstrainedForeignId('project_id');
            $table->foreignId('task_id')->nullable(false)->change();
        });
    }
};
