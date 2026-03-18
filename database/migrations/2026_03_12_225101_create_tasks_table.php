<?php

use App\Enums\Priority;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
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
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id');
            $table->foreignId('activity_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('billing_model', TaskBillingModel::values())->default(TaskBillingModel::Hourly->value);
            $table->string('status', 64)->default(TaskStatus::InProgress->value);
            $table->unsignedTinyInteger('priority')->default(Priority::Normal->value);
            $table->boolean('is_billable')->default(true);
            $table->boolean('track_time')->default(true);
            $table->boolean('is_invoiced')->default(false);
            $table->string('invoice_reference')->nullable();
            $table->timestampTz('invoiced_at')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('hourly_rate_override', 10, 2)->nullable();
            $table->decimal('fixed_price', 12, 2)->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->date('due_date')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['owner_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'billing_model']);
            $table->index(['owner_id', 'activity_id']);
            $table->index(['project_id', 'activity_id']);
            $table->index(['owner_id', 'due_date']);
            $table->index(['owner_id', 'completed_at']);
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
