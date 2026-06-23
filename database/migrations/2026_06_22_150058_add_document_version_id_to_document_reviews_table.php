<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds document_version_id to document_reviews so each review is tied to a specific
     * document version. The unique constraint changes from (document_id, user_id) to
     * (document_version_id, user_id) so a recipient can review each version once.
     */
    public function up(): void
    {
        Schema::table('document_reviews', function (Blueprint $table): void {
            $table->foreignId('document_version_id')
                ->nullable()
                ->after('document_id')
                ->constrained('document_versions')
                ->nullOnDelete();
        });

        // Backfill: link existing reviews to the latest version of their document
        DB::statement('
            UPDATE document_reviews dr
            SET document_version_id = (
                SELECT dv.id
                FROM document_versions dv
                WHERE dv.document_id = dr.document_id
                ORDER BY dv.version_number DESC
                LIMIT 1
            )
            WHERE document_version_id IS NULL
        ');

        // Now that all rows are filled, make it non-nullable
        Schema::table('document_reviews', function (Blueprint $table): void {
            $table->foreignId('document_version_id')->nullable(false)->change();
        });

        // Drop the old unique constraint on (document_id, user_id)
        Schema::table('document_reviews', function (Blueprint $table): void {
            $table->dropUnique(['document_id', 'user_id']);
        });

        // New unique constraint: one review per user per version
        Schema::table('document_reviews', function (Blueprint $table): void {
            $table->unique(['document_version_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_reviews', function (Blueprint $table): void {
            $table->dropUnique(['document_version_id', 'user_id']);
            $table->unique(['document_id', 'user_id']);
            $table->dropConstrainedForeignId('document_version_id');
        });
    }
};
