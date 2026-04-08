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
        Schema::create('revision_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
            $table->foreignId('related_version_id')->nullable()->constrained('document_versions')->onDelete('set null');
            $table->foreignId('commenter_id')->constrained('users')->onDelete('cascade');

            // e.g., SUBMITTED, REQUESTED_REVISION, APPROVED
            $table->string('action_type', 50);
            $table->text('comments')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revision_histories');
    }
};
