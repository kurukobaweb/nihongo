<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('display_order');
            $table->index('is_active');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('title');
            $table->text('prompt_text');
            $table->string('difficulty', 20);
            $table->string('question_format', 50);
            $table->integer('recommended_duration_seconds')->default(60);
            $table->boolean('has_model_answer')->default(false);
            $table->text('model_answer_text')->nullable();
            $table->boolean('is_published')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('category_id');
            $table->index('difficulty');
            $table->index('question_format');
            $table->index(['category_id', 'is_published', 'display_order']);
            $table->index(['difficulty', 'question_format', 'is_published']);
        });
        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_difficulty_check CHECK (difficulty IN ('beginner', 'intermediate', 'advanced'))");

        Schema::create('question_tag', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();

            $table->primary(['question_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('question_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('audio_path', 500);
            $table->integer('audio_size_bytes')->nullable();
            $table->decimal('audio_duration_seconds', 8, 2)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('user_id');
            $table->index('question_id');
            $table->index('status');
            $table->index(['user_id', 'status']);
        });
        DB::statement("ALTER TABLE submissions ADD CONSTRAINT submissions_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_id')->unique();
            $table->text('transcript')->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->integer('characters_per_minute')->nullable();
            $table->string('speed_assessment', 20)->nullable();
            $table->jsonb('pronunciation_result')->nullable();
            $table->jsonb('fluency_result')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->text('comment')->nullable();
            $table->string('azure_request_id')->nullable();
            $table->jsonb('raw_azure_response')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('submission_id')->references('id')->on('submissions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index('speed_assessment');
            $table->index('created_at');
        });
        DB::statement("ALTER TABLE evaluations ADD CONSTRAINT evaluations_speed_assessment_check CHECK (speed_assessment IS NULL OR speed_assessment IN ('slow', 'appropriate', 'fast'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('question_tag');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
