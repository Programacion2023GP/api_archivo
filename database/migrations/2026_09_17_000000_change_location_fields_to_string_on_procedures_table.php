<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE procedures MODIFY location_building VARCHAR(255) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE procedures MODIFY location_furniture VARCHAR(255) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE procedures MODIFY location_position VARCHAR(255) NULL DEFAULT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE procedures MODIFY location_building INT NULL DEFAULT 0");
        DB::statement("ALTER TABLE procedures MODIFY location_furniture INT NULL DEFAULT 0");
        DB::statement("ALTER TABLE procedures MODIFY location_position INT NULL DEFAULT 0");
    }
};
