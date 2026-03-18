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
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->morphs('invoiceable');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_rate', 12, 2)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedSmallInteger('line_order')->default(1);
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['owner_id', 'id']);
            $table->index(['invoice_id', 'line_order']);
            $table->index(['owner_id', 'invoiceable_type', 'invoiceable_id'], 'invoice_items_owner_invoiceable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
