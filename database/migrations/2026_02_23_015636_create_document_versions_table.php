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
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');

            $table->integer('version_number');
            $table->string('file_storage_path');
            $table->string('original_filename')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');

            $table->timestamps();

            // Prevents duplicate version numbers for the same document
            $table->unique(['document_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
