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
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('noteable');
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestampTz('noted_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['owner_id', 'is_pinned']);
            $table->index(['owner_id', 'noted_at']);
            $table->index(['owner_id', 'noteable_type', 'noteable_id']);
            $table->index(['noteable_type', 'noteable_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
