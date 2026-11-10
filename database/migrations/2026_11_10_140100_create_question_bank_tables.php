<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['single_choice', 'multi_choice', 'true_false'])->default('single_choice');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['track_id', 'is_active']);
            $table->index(['difficulty', 'is_active']);
        });

        Schema::create('question_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('code_language', 24)->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'version']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')->on('question_versions')
                ->nullOnDelete();
        });

        Schema::create('question_version_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_version_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('stem_md');
            $table->text('code_snippet')->nullable();
            $table->text('explanation_md')->nullable();

            $table->unique(['question_version_id', 'locale']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('is_correct')->default(false);

            $table->unique(['question_version_id', 'position']);
        });

        Schema::create('question_option_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_option_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('label_md');
            $table->text('option_explanation_md')->nullable();

            $table->unique(['question_option_id', 'locale']);
        });

        Schema::create('question_tag', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['question_id', 'tag_id']);
            $table->index(['tag_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_tag');
        Schema::dropIfExists('question_option_translations');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('question_version_translations');
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('question_versions');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('tags');
    }
};
