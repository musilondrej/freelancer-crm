<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('time_entries')
            ->whereNull('invoice_reference')
            ->update(['invoice_reference' => '']);

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->string('invoice_reference')->default('')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->string('invoice_reference')->nullable()->default(null)->change();
        });
    }
};
