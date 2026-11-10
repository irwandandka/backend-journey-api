<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmaps', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'position']);
        });

        Schema::create('roadmap_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 160);
            $table->text('description')->nullable();

            $table->unique(['roadmap_id', 'locale']);
        });

        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 80)->unique();
            $table->string('abbreviation', 3);
            $table->char('color_dark', 7);
            $table->char('color_light', 7);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['roadmap_id', 'position']);
        });

        Schema::create('track_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 160);
            $table->text('description')->nullable();

            $table->unique(['track_id', 'locale']);
        });

        Schema::create('journeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 100)->unique();
            $table->enum('tier', ['foundation', 'intermediate', 'advanced', 'deep'])->default('foundation');
            $table->enum('unlock_rule', ['sequential', 'tier', 'open'])->default('sequential');
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedTinyInteger('pass_threshold')->default(70);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['track_id', 'tier', 'position']);
        });

        Schema::create('journey_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 160);
            $table->text('description')->nullable();

            $table->unique(['journey_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_translations');
        Schema::dropIfExists('journeys');
        Schema::dropIfExists('track_translations');
        Schema::dropIfExists('tracks');
        Schema::dropIfExists('roadmap_translations');
        Schema::dropIfExists('roadmaps');
    }
};
