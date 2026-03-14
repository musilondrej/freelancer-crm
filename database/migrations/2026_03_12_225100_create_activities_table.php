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
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->decimal('default_hourly_rate', 10, 2)->nullable();
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['owner_id', 'id']);
            $table->index(['owner_id', 'project_id']);
            $table->index(['owner_id', 'is_active']);
            $table->index(['owner_id', 'sort_order']);

            $table->foreign(['owner_id', 'project_id'])
                ->references(['owner_id', 'id'])
                ->on('projects')
                ->cascadeOnDelete();
        });

        DB::statement('
            CREATE UNIQUE INDEX activities_owner_project_name_unique
                ON activities (owner_id, COALESCE(project_id, 0), lower(name))
                WHERE deleted_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS activities_owner_project_name_unique');
        Schema::dropIfExists('activities');
    }
};
