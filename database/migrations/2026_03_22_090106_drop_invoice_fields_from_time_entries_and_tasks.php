<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'is_invoiced']);
            $table->dropIndex(['invoice_reference', 'invoiced_at']);
            $table->dropColumn(['is_invoiced', 'invoice_reference', 'invoiced_at']);
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'is_invoiced']);
            $table->dropIndex(['owner_id', 'invoiced_at']);
            $table->dropColumn(['is_invoiced', 'invoice_reference', 'invoiced_at']);
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->boolean('is_invoiced')->default(false)->after('is_billable_override');
            $table->string('invoice_reference')->nullable()->after('is_invoiced');
            $table->timestampTz('invoiced_at')->nullable()->after('invoice_reference');
            $table->index(['owner_id', 'is_invoiced']);
            $table->index(['invoice_reference', 'invoiced_at']);
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->boolean('is_invoiced')->default(false)->after('is_billable');
            $table->string('invoice_reference')->nullable()->after('is_invoiced');
            $table->timestampTz('invoiced_at')->nullable()->after('invoice_reference');
        });
    }
};
