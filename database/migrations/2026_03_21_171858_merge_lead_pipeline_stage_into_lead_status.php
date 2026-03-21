<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand status constraint to include 'discovery' and 'negotiation', drop 'contacted'
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_status_check');
            DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_status_check CHECK (status IN ('new', 'discovery', 'qualified', 'proposal', 'negotiation', 'won', 'lost', 'archived'))");
        }

        DB::statement("UPDATE leads SET status = 'discovery' WHERE status = 'contacted'");

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'pipeline_stage']);
            $table->dropColumn('pipeline_stage');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->enum('pipeline_stage', ['inbox', 'discovery', 'qualification', 'proposal', 'negotiation', 'closed'])
                ->default('inbox')
                ->after('status');
            $table->index(['owner_id', 'pipeline_stage']);
        });

        DB::statement("UPDATE leads SET status = 'contacted' WHERE status = 'discovery'");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_status_check');
            DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_status_check CHECK (status IN ('new', 'contacted', 'qualified', 'proposal', 'won', 'lost', 'archived'))");
        }
    }
};
