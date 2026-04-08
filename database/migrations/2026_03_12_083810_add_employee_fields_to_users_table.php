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
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_no')->nullable()->unique()->after('id');
            $table->string('job_title')->nullable()->after('name');
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete()->after('job_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropColumn(['employee_no', 'job_title', 'division_id']);
        });
    }
};
