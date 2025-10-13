<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('predictions') && Schema::hasColumn('predictions', 'model_version')) {
            // Increase column size to store longer model version strings
            DB::statement("ALTER TABLE `predictions` MODIFY `model_version` VARCHAR(64)");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('predictions') && Schema::hasColumn('predictions', 'model_version')) {
            // Revert to original size
            DB::statement("ALTER TABLE `predictions` MODIFY `model_version` VARCHAR(20)");
        }
    }
};
