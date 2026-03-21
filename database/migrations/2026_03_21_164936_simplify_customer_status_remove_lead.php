<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE clients SET status = 'active' WHERE status = 'lead'");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_status_check');
            DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_status_check CHECK (status IN ('active', 'inactive'))");
            DB::statement("ALTER TABLE clients ALTER COLUMN status SET DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_status_check');
            DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_status_check CHECK (status IN ('lead', 'active', 'inactive'))");
            DB::statement("ALTER TABLE clients ALTER COLUMN status SET DEFAULT 'lead'");
        }
    }
};
