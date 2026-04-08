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
        Schema::table('documents', function (Blueprint $table): void {
            $table->index('uploader_id');
            $table->index('status');
            $table->index(['uploader_id', 'status']);
        });

        Schema::table('document_recipients', function (Blueprint $table): void {
            $table->index(['user_id', 'document_id']);
        });

        Schema::table('document_reviews', function (Blueprint $table): void {
            $table->index(['document_id', 'status']);
        });
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
