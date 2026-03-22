<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('clients')->restrictOnDelete();
            $table->string('title');
            $table->string('reference')->nullable()->comment('External invoice number, e.g. from Fakturoid');
            $table->char('currency', 3)->default('EUR');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'status']);
            $table->index(['owner_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_reports');
    }
};
