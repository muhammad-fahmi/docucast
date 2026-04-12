<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to documents table if columns exist
        if (
            Schema::hasTable('documents') &&
            Schema::hasColumn('documents', 'uploader_id') &&
            Schema::hasColumn('documents', 'status')
        ) {
            Schema::table('documents', function (Blueprint $table): void {
                $table->index('uploader_id');
                $table->index('status');
                $table->index(['uploader_id', 'status']);
            });
        }

        // Add indexes to document_recipients table
        if (
            Schema::hasTable('document_recipients') &&
            Schema::hasColumn('document_recipients', 'user_id') &&
            Schema::hasColumn('document_recipients', 'document_id')
        ) {
            Schema::table('document_recipients', function (Blueprint $table): void {
                $table->index(['user_id', 'document_id']);
            });
        }

        // Add indexes to document_reviews table
        if (
            Schema::hasTable('document_reviews') &&
            Schema::hasColumn('document_reviews', 'document_id') &&
            Schema::hasColumn('document_reviews', 'status')
        ) {
            Schema::table('document_reviews', function (Blueprint $table): void {
                $table->index(['document_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_reviews', function (Blueprint $table): void {
            $table->dropIndex(['document_id', 'status']);
        });

        Schema::table('document_recipients', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'document_id']);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex(['uploader_id', 'status']);
            $table->dropIndex(['status']);
            $table->dropIndex(['uploader_id']);
        });
    }
};
