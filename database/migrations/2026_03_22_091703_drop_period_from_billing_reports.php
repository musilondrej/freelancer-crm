<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_reports', function (Blueprint $table): void {
            $table->dropColumn(['period_from', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::table('billing_reports', function (Blueprint $table): void {
            $table->date('period_from')->nullable()->after('currency');
            $table->date('period_to')->nullable()->after('period_from');
        });
    }
};
