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
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('reference', 64)->nullable();
            $table->timestampTz('issued_at');
            $table->char('currency', 3)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['owner_id', 'id']);
            $table->index(['owner_id', 'reference']);
            $table->index(['owner_id', 'issued_at']);
            $table->index(['customer_id', 'issued_at']);
            $table->index(['project_id', 'issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
