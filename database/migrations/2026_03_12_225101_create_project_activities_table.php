<?php

use App\Enums\ProjectActivityStatus;
use App\Enums\ProjectActivityType;
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
        Schema::create('project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('activity_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ProjectActivityType::values())->default(ProjectActivityType::Hourly->value);
            $table->string('status', 64)->default(ProjectActivityStatus::InProgress->value);
            $table->boolean('is_running')->default(false);
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_invoiced')->default(false);
            $table->string('invoice_reference')->nullable();
            $table->timestampTz('invoiced_at')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('unit_rate', 10, 2)->nullable();
            $table->decimal('flat_amount', 12, 2)->nullable();
            $table->unsignedInteger('tracked_minutes')->nullable();
            $table->date('due_date')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['owner_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'type']);
            $table->index(['owner_id', 'activity_id']);
            $table->index(['project_id', 'activity_id']);
            $table->index(['owner_id', 'due_date']);
            $table->index(['owner_id', 'finished_at']);
            $table->index(['owner_id', 'is_running']);
            $table->index(['owner_id', 'is_invoiced']);
            $table->index(['owner_id', 'invoiced_at']);

            $table->foreign(['owner_id', 'project_id'])
                ->references(['owner_id', 'id'])
                ->on('projects')
                ->cascadeOnDelete();

            $table->foreign(['owner_id', 'activity_id'])
                ->references(['owner_id', 'id'])
                ->on('activities')
                ->restrictOnDelete();
        });

        DB::statement("
            CREATE UNIQUE INDEX project_activities_owner_running_hourly_unique
                ON project_activities (owner_id)
                WHERE deleted_at IS NULL
                  AND is_running = true
                  AND type = 'hourly'
                  AND finished_at IS NULL
                  AND started_at IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_activities');
    }
};
