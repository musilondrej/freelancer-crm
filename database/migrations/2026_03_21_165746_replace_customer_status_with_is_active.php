<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('status');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE clients SET is_active = (status = 'active')");
        } else {
            DB::statement("UPDATE clients SET is_active = CASE WHEN status = 'active' THEN 1 ELSE 0 END");
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'status']);
            $table->dropColumn('status');
            $table->index(['owner_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('is_active');
        });

        DB::statement("UPDATE clients SET status = CASE WHEN is_active THEN 'active' ELSE 'inactive' END");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_status_check CHECK (status IN ('active', 'inactive'))");
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'is_active']);
            $table->dropColumn('is_active');
            $table->index(['owner_id', 'status']);
        });
    }
};
