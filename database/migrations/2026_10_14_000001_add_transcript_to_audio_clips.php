<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->string('transcript_status', 32)->default('idle')->after('duration_ms');
            $table->text('transcript_text')->nullable()->after('transcript_status');
            $table->text('transcript_error')->nullable()->after('transcript_text');
        });
    }

    public function down(): void
    {
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->dropColumn(['transcript_status', 'transcript_text', 'transcript_error']);
        });
    }
};
