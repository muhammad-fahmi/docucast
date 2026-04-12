<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration handles schema compatibility between the old and new document table structures.
     * Old schema used: initiator_id, overall_status
     * New schema uses: uploader_id, status, file_path, file_name, description
     */
    public function up(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        // Ensure all required columns from the new schema exist
        Schema::table('documents', function (Blueprint $table): void {
            if (!Schema::hasColumn('documents', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('documents', 'file_path')) {
                $table->string('file_path')->nullable();
            }
            if (!Schema::hasColumn('documents', 'file_name')) {
                $table->string('file_name')->nullable();
            }
            if (!Schema::hasColumn('documents', 'status')) {
                $table->enum('status', ['pending', 'in_review', 'approved'])->default('pending');
            }
            if (!Schema::hasColumn('documents', 'uploader_id') && Schema::hasColumn('documents', 'initiator_id')) {
                // For SQLite, we can't easily rename columns with constraints
                // So we'll add a new column and copy data
                $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        // Migrate data from old schema to new schema
        if (Schema::hasColumn('documents', 'initiator_id') && Schema::hasColumn('documents', 'uploader_id')) {
            // Copy initiator_id to uploader_id where uploader_id is null
            DB::table('documents')
                ->whereNull('uploader_id')
                ->update(['uploader_id' => DB::raw('initiator_id')]);
        }

        // Migrate status values
        if (Schema::hasColumn('documents', 'overall_status') && Schema::hasColumn('documents', 'status')) {
            $connection = DB::getDriverName();

            if ($connection === 'sqlite') {
                // SQLite safe update
                DB::table('documents')
                    ->where('status', 'pending')
                    ->whereNotNull('overall_status')
                    ->update([
                        'status' => DB::raw(
                            "CASE overall_status " .
                            "WHEN 'APPROVED' THEN 'approved' " .
                            "WHEN 'PENDING_REVIEW' THEN 'in_review' " .
                            "WHEN 'NEEDS_REVISION' THEN 'in_review' " .
                            "ELSE 'pending' END"
                        )
                    ]);
            } elseif ($connection === 'pgsql') {
                DB::statement(<<<'SQL'
                    UPDATE documents
                    SET status = CASE overall_status
                        WHEN 'APPROVED' THEN 'approved'
                        WHEN 'PENDING_REVIEW' THEN 'in_review'
                        WHEN 'NEEDS_REVISION' THEN 'in_review'
                        ELSE 'pending'
                    END
                    WHERE status = 'pending' AND overall_status IS NOT NULL
                    SQL);
            } else {
                // MySQL
                DB::statement(<<<'SQL'
                    UPDATE documents
                    SET status = CASE overall_status
                        WHEN 'APPROVED' THEN 'approved'
                        WHEN 'PENDING_REVIEW' THEN 'in_review'
                        WHEN 'NEEDS_REVISION' THEN 'in_review'
                        ELSE 'pending'
                    END
                    WHERE status = 'pending' AND overall_status IS NOT NULL
                    SQL);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is about compatibility
        // Reversing would risk data loss, so we don't reverse it
    }
};
