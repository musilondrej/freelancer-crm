<?php

use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Enums\ProjectStatus;
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
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id');
            $table->foreignId('primary_contact_id')->nullable();
            $table->string('name');
            $table->string('status', 64)->default(ProjectStatus::Planning->value);
            $table->enum('pipeline_stage', ProjectPipelineStage::values())->default(ProjectPipelineStage::New->value);
            $table->enum('pricing_model', ProjectPricingModel::values())->default(ProjectPricingModel::Fixed->value);
            $table->unsignedTinyInteger('priority')->default(3);
            $table->date('start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->date('closed_date')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('fixed_price', 12, 2)->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->decimal('actual_value', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->timestampTz('last_activity_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['owner_id', 'id']);
            $table->index(['owner_id', 'status']);
            $table->index(['owner_id', 'pipeline_stage']);
            $table->index(['owner_id', 'pricing_model']);
            $table->index(['owner_id', 'currency']);
            $table->index(['owner_id', 'target_end_date']);
            $table->index(['owner_id', 'last_activity_at']);
            $table->index(['customer_id', 'status']);
            $table->index('primary_contact_id');

            $table->foreign(['owner_id', 'customer_id'])
                ->references(['owner_id', 'id'])
                ->on('clients')
                ->cascadeOnDelete();

            $table->foreign(['owner_id', 'primary_contact_id'])
                ->references(['owner_id', 'id'])
                ->on('client_contacts')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
