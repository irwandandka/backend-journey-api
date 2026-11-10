<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('key', 80)->nullable()->unique()->after('id');
        });

        Schema::table('question_versions', function (Blueprint $table) {
            $table->char('content_hash', 64)->nullable()->after('code_language');
        });
    }

    public function down(): void
    {
        Schema::table('question_versions', function (Blueprint $table) {
            $table->dropColumn('content_hash');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }
};
