<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->enum('kind', ['quiz', 'review'])->default('quiz')->after('quiz_id');
            $table->foreignId('quiz_id')->nullable()->change();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX quiz_attempts_one_in_progress_review
            ON quiz_attempts (user_id)
            WHERE kind = 'review' AND status = 'in_progress'
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS quiz_attempts_one_in_progress_review');

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('kind');
            $table->foreignId('quiz_id')->nullable(false)->change();
        });
    }
};
