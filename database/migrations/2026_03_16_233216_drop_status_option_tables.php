<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_activity_status_options');
        Schema::dropIfExists('project_status_options');
    }

    public function down(): void
    {
        // These tables are no longer needed — statuses are now enums.
    }
};
