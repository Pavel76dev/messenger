<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->string('tasks_extract_status', 32)->default('idle')->after('transcript_error');
            $table->text('tasks_extract_error')->nullable()->after('tasks_extract_status');
        });

        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audio_clip_id')->constrained('audio_clips')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('source', 64)->default('audio_clip_transcript');
            $table->timestamps();

            $table->index(['audio_clip_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('task_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_list_id')->constrained('task_lists')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 16)->default('open');
            $table->timestamps();

            $table->index(['task_list_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_items');
        Schema::dropIfExists('task_lists');

        Schema::table('audio_clips', function (Blueprint $table) {
            $table->dropColumn(['tasks_extract_status', 'tasks_extract_error']);
        });
    }
};
