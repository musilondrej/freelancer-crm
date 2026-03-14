<?php

use App\Enums\BacklogItemStatus;
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
        Schema::create('backlog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('activity_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', BacklogItemStatus::values())->default(BacklogItemStatus::Todo->value);
            $table->unsignedTinyInteger('priority')->default(3);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('converted_to_worklog_id')->nullable()->constrained('project_activities')->nullOnDelete();
            $table->timestampTz('converted_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['owner_id', 'status']);
            $table->index(['owner_id', 'project_id']);
            $table->index(['owner_id', 'due_date']);
            $table->index(['owner_id', 'converted_at']);

            $table->foreign(['owner_id', 'project_id'])
                ->references(['owner_id', 'id'])
                ->on('projects')
                ->cascadeOnDelete();

            $table->foreign(['owner_id', 'activity_id'])
                ->references(['owner_id', 'id'])
                ->on('activities')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlog_items');
    }
};
