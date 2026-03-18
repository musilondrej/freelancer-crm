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
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_billable_override')->nullable();
            $table->decimal('hourly_rate_override', 10, 2)->nullable();
            $table->boolean('is_invoiced')->default(false);
            $table->string('invoice_reference')->nullable();
            $table->timestampTz('invoiced_at')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unsignedBigInteger('running_owner_unique_key')
                ->nullable()
                ->storedAs('case when deleted_at is null and ended_at is null and started_at is not null then owner_id else null end');

            $table->unique('running_owner_unique_key', 'time_entries_owner_running_unique');
            $table->index(['owner_id', 'started_at']);
            $table->index(['owner_id', 'ended_at']);
            $table->index(['task_id', 'ended_at']);
            $table->index(['owner_id', 'is_invoiced']);
            $table->index(['invoice_reference', 'invoiced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
