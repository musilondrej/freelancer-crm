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
        if (Schema::hasColumn('clients', 'registration_number')) {
            return;
        }

        if (! Schema::hasColumn('clients', 'company_id')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->renameColumn('company_id', 'registration_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('clients', 'company_id')) {
            return;
        }

        if (! Schema::hasColumn('clients', 'registration_number')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->renameColumn('registration_number', 'company_id');
        });
    }
};
