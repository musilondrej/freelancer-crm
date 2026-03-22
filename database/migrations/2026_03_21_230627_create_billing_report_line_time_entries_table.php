<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_report_line_time_entries', function (Blueprint $table): void {
            $table->foreignId('billing_report_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_entry_id')->constrained()->cascadeOnDelete();

            $table->primary(['billing_report_line_id', 'time_entry_id']);

            // One time entry can only appear on one billing report (prevents double-billing)
            $table->unique('time_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_report_line_time_entries');
    }
};
