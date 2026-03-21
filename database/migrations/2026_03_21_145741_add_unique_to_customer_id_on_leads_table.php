<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'customer_id']);
            $table->unique(['owner_id', 'customer_id'], 'leads_owner_customer_unique');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropUnique('leads_owner_customer_unique');
            $table->index(['owner_id', 'customer_id']);
        });
    }
};
