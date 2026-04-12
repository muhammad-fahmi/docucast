<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $connection = DB::getDriverName();

        if ($connection === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json');

            return;
        }

        if ($connection === 'mysql') {
            DB::statement('ALTER TABLE notifications MODIFY data JSON');

            return;
        }

        // SQLite doesn't require migration - JSON is handled at application level
        if ($connection === 'sqlite') {
            // No action needed for SQLite
            return;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $connection = DB::getDriverName();

        if ($connection === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');

            return;
        }

        if ($connection === 'mysql') {
            DB::statement('ALTER TABLE notifications MODIFY data LONGTEXT');

            return;
        }

        // SQLite doesn't require migration
        if ($connection === 'sqlite') {
            // No action needed for SQLite
            return;
        }
    }
};
