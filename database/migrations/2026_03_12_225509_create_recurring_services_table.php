<?php

use App\Enums\RecurringServiceBillingModel;
use App\Enums\RecurringServiceCadenceUnit;
use App\Enums\RecurringServiceStatus;
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
        Schema::create('recurring_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->string('name');
            $table->foreignId('service_type_id');
            $table->enum('billing_model', RecurringServiceBillingModel::values())->default(RecurringServiceBillingModel::Fixed->value);
            $table->char('currency', 3)->nullable();
            $table->decimal('fixed_amount', 12, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('included_quantity', 10, 2)->nullable();
            $table->enum('cadence_unit', RecurringServiceCadenceUnit::values())->default(RecurringServiceCadenceUnit::Month->value);
            $table->unsignedSmallInteger('cadence_interval')->default(1);
            $table->date('starts_on');
            $table->date('next_due_on')->nullable();
            $table->date('last_reminded_for_due_on')->nullable();
            $table->timestampTz('last_reminded_at')->nullable();
            $table->date('last_invoiced_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->enum('status', RecurringServiceStatus::values())->default(RecurringServiceStatus::Active->value);
            $table->jsonb('remind_days_before')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['owner_id', 'status']);
            $table->index(['owner_id', 'next_due_on']);
            $table->index(['owner_id', 'cadence_unit', 'cadence_interval']);
            $table->index(['owner_id', 'service_type_id']);
            $table->index(['customer_id', 'status']);
            $table->index(['project_id', 'status']);

            $table->foreign(['owner_id', 'service_type_id'])
                ->references(['owner_id', 'id'])
                ->on('recurring_service_types')
                ->restrictOnDelete();

            $table->foreign(['owner_id', 'customer_id'])
                ->references(['owner_id', 'id'])
                ->on('clients')
                ->restrictOnDelete();

            $table->foreign(['owner_id', 'project_id'])
                ->references(['owner_id', 'id'])
                ->on('projects')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_services');
    }
};
