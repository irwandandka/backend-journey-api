<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['in_progress', 'submitted', 'abandoned'])->default('in_progress');
            $table->string('locale', 5);
            $table->unsignedSmallInteger('question_count');
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'quiz_id']);
            $table->index(['user_id', 'submitted_at']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX quiz_attempts_one_in_progress_per_quiz
            ON quiz_attempts (user_id, quiz_id)
            WHERE status = 'in_progress'
        SQL);

        Schema::create('quiz_attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_version_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->jsonb('snapshot');

            $table->unique(['quiz_attempt_id', 'position']);
        });

        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_question_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_correct');
            $table->unsignedInteger('time_spent_ms')->nullable();
            $table->timestamp('answered_at');
        });

        Schema::create('attempt_answer_options', function (Blueprint $table) {
            $table->foreignId('quiz_attempt_answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_option_id')->constrained()->restrictOnDelete();

            $table->primary(['quiz_attempt_answer_id', 'question_option_id'], 'attempt_answer_options_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answer_options');
        Schema::dropIfExists('quiz_attempt_answers');
        Schema::dropIfExists('quiz_attempt_questions');
        Schema::dropIfExists('quiz_attempts');
    }
};
