<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_report_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['billing_report_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_report_lines');
    }
};
