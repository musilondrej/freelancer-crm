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
        if (Schema::hasColumn('tasks', 'activity_id')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropForeign(['owner_id', 'activity_id']);
                $table->dropIndex(['owner_id', 'activity_id']);
                $table->dropIndex(['project_id', 'activity_id']);
                $table->dropColumn('activity_id');
            });
        }

        Schema::dropIfExists('activities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty — activities feature has been removed.
    }
};
