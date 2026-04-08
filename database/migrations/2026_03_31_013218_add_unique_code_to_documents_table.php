<?php

use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('unique_code')->nullable();
        });

        DB::table('documents')
            ->select(['id', 'uploader_id', 'created_at'])
            ->orderBy('id')
            ->chunkById(200, function ($documents): void {
                foreach ($documents as $document) {
                    $datePart = Carbon::parse($document->created_at)->format('Ymd');

                    DB::table('documents')
                        ->where('id', $document->id)
                        ->update([
                            'unique_code' => Document::formatUniqueCode((int) $document->uploader_id, $datePart, (int) $document->id),
                        ]);
                }
            });

        Schema::table('documents', function (Blueprint $table): void {
            $table->unique('unique_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropUnique(['unique_code']);
            $table->dropColumn('unique_code');
        });
    }
};
