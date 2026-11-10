<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_journey_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['available', 'in_progress', 'completed'])->default('available');
            $table->unsignedTinyInteger('best_score')->nullable();
            $table->unsignedSmallInteger('attempts_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'journey_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('user_daily_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('activity_date');
            $table->unsignedSmallInteger('questions_answered')->default(0);
            $table->unsignedSmallInteger('correct_answers')->default(0);
            $table->unsignedInteger('xp_earned')->default(0);

            $table->unique(['user_id', 'activity_date']);
        });

        Schema::create('user_xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->integer('amount');
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('icon', 40);
            $table->jsonb('rule');
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('achievement_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 120);
            $table->text('description')->nullable();

            $table->unique(['achievement_id', 'locale']);
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at');

            $table->primary(['user_id', 'achievement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievement_translations');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('user_xp_events');
        Schema::dropIfExists('user_daily_activity');
        Schema::dropIfExists('user_journey_progress');
    }
};
