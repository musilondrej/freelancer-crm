<?php

use App\Enums\Priority;
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
        DB::table('tasks')
            ->whereNull('priority')
            ->update(['priority' => Priority::Normal->value]);

        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('priority')->default(Priority::Normal->value)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('priority')->nullable()->default(null)->change();
        });
    }
};
