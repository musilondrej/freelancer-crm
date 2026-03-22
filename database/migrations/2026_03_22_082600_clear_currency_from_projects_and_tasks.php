<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Currency is now managed exclusively at the customer level.
 * Any per-project or per-task currency overrides are removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('projects')->whereNotNull('currency')->update(['currency' => null]);
        DB::table('tasks')->whereNotNull('currency')->update(['currency' => null]);
    }

    public function down(): void
    {
        // Data cannot be restored after clearing.
    }
};
