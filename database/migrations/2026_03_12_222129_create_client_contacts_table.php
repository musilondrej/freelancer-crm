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
        Schema::create('client_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id');
            $table->string('full_name');
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_billing_contact')->default(false);
            $table->timestampTz('last_contacted_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['owner_id', 'id']);
            $table->index(['client_id', 'is_primary']);
            $table->index(['client_id', 'last_contacted_at']);
            $table->index(['owner_id', 'client_id']);
            $table->index(['owner_id', 'full_name']);
            $table->index(['owner_id', 'is_billing_contact']);
            $table->index('email');

            $table->foreign(['owner_id', 'client_id'])
                ->references(['owner_id', 'id'])
                ->on('clients')
                ->cascadeOnDelete();
        });

        DB::statement('
            CREATE UNIQUE INDEX client_contacts_owner_client_email_unique
                ON client_contacts (owner_id, client_id, lower(email))
                WHERE deleted_at IS NULL
                  AND email IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
