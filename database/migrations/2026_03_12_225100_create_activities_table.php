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
            $table->unsignedBigInteger('project_unique_scope_key')->storedAs('coalesce(project_id, 0)');
            $table->string('name_unique_key', 160)->nullable()->storedAs('case when deleted_at is null then lower(name) else null end');

            $table->unique(['owner_id', 'id']);
            $table->unique(['owner_id', 'project_unique_scope_key', 'name_unique_key'], 'activities_owner_project_name_unique');
            $table->index(['owner_id', 'project_id']);
            $table->index(['owner_id', 'is_active']);
            $table->index(['owner_id', 'sort_order']);

            $table->foreign(['owner_id', 'project_id'])
                ->references(['owner_id', 'id'])
                ->on('projects')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
