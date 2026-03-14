<?php

use App\Enums\CustomerStatus;
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
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('company_id')->nullable();
            $table->string('vat_id')->nullable();
            $table->string('dic')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('timezone')->nullable();
            $table->char('billing_currency', 3)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->enum('status', CustomerStatus::values())->default(CustomerStatus::Lead->value);
            $table->string('source')->nullable();
            $table->timestampTz('last_contacted_at')->nullable();
            $table->timestampTz('next_follow_up_at')->nullable();
            $table->text('internal_summary')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['owner_id', 'id']);
            $table->index(['owner_id', 'status']);
            $table->index(['owner_id', 'next_follow_up_at']);
            $table->index(['owner_id', 'name']);
            $table->index(['owner_id', 'last_contacted_at']);
            $table->index(['owner_id', 'billing_currency']);
        });

        DB::statement('
            CREATE UNIQUE INDEX clients_owner_email_unique
                ON clients (owner_id, lower(email))
                WHERE deleted_at IS NULL
                  AND email IS NOT NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX clients_owner_vat_id_unique
                ON clients (owner_id, upper(vat_id))
                WHERE deleted_at IS NULL
                  AND vat_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
