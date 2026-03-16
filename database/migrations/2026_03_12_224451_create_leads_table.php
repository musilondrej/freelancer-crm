<?php

use App\Enums\LeadPipelineStage;
use App\Enums\LeadStatus;
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
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_source_id')->nullable();
            $table->foreignId('customer_id')->nullable();
            $table->string('full_name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->enum('status', LeadStatus::values())->default(LeadStatus::New->value);
            $table->enum('pipeline_stage', LeadPipelineStage::values())->default(LeadPipelineStage::Inbox->value);
            $table->unsignedTinyInteger('priority')->default(3);
            $table->char('currency', 3)->nullable();
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->timestampTz('contacted_at')->nullable();
            $table->timestampTz('last_activity_at')->nullable();
            $table->text('summary')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('email_unique_key')->nullable()->storedAs('case when deleted_at is null and email is not null then lower(email) else null end');

            $table->unique(['owner_id', 'email_unique_key'], 'leads_owner_email_unique');
            $table->index(['owner_id', 'status']);
            $table->index(['owner_id', 'pipeline_stage']);
            $table->index(['owner_id', 'lead_source_id']);
            $table->index(['owner_id', 'expected_close_date']);
            $table->index(['owner_id', 'last_activity_at']);
            $table->index(['owner_id', 'customer_id']);

            $table->foreign(['owner_id', 'lead_source_id'])
                ->references(['owner_id', 'id'])
                ->on('lead_sources')
                ->restrictOnDelete();

            $table->foreign(['owner_id', 'customer_id'])
                ->references(['owner_id', 'id'])
                ->on('clients')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
