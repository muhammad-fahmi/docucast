<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('file_path');
                $table->string('file_name');
                $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
                $table->enum('status', ['pending', 'in_review', 'approved'])->default('pending');
                $table->timestamps();
            });

            return;
        }

        $hasDescription = Schema::hasColumn('documents', 'description');
        $hasFilePath = Schema::hasColumn('documents', 'file_path');
        $hasFileName = Schema::hasColumn('documents', 'file_name');
        $hasUploaderId = Schema::hasColumn('documents', 'uploader_id');
        $hasStatus = Schema::hasColumn('documents', 'status');

        if (!$hasDescription || !$hasFilePath || !$hasFileName || !$hasUploaderId || !$hasStatus) {
            Schema::table('documents', function (Blueprint $table) use ($hasDescription, $hasFilePath, $hasFileName, $hasUploaderId, $hasStatus): void {
                if (!$hasDescription) {
                    $table->text('description')->nullable();
                }

                if (!$hasFilePath) {
                    $table->string('file_path')->nullable();
                }

                if (!$hasFileName) {
                    $table->string('file_name')->nullable();
                }

                if (!$hasUploaderId) {
                    $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
                }

                if (!$hasStatus) {
                    $table->enum('status', ['pending', 'in_review', 'approved'])->default('pending');
                }
            });
        }

        if (Schema::hasColumn('documents', 'initiator_id') && Schema::hasColumn('documents', 'uploader_id')) {
            DB::table('documents')
                ->whereNull('uploader_id')
                ->update([
                    'uploader_id' => DB::raw('initiator_id'),
                ]);
        }

        if (Schema::hasColumn('documents', 'overall_status') && Schema::hasColumn('documents', 'status')) {
            DB::statement(<<<'SQL'
                UPDATE documents
                SET status = CASE overall_status
                    WHEN 'APPROVED' THEN 'approved'
                    WHEN 'PENDING_REVIEW' THEN 'in_review'
                    WHEN 'NEEDS_REVISION' THEN 'in_review'
                    ELSE 'pending'
                END
                WHERE status IS NULL OR status = 'pending'
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
