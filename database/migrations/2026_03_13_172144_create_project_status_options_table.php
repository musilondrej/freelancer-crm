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
        Schema::create('project_status_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('label', 120);
            $table->string('color', 32)->default('gray');
            $table->string('icon', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_open')->default(true);
            $table->boolean('is_trackable')->default(true);
            $table->timestampsTz();

            $table->unique(['owner_id', 'code']);
            $table->index(['owner_id', 'sort_order']);
            $table->index(['owner_id', 'is_open']);
            $table->index(['owner_id', 'is_trackable']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_status_options');
    }
};
