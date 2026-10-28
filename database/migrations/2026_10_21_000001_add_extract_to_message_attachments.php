<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_attachments', function (Blueprint $table) {
            $table->string('extract_status', 16)->default('idle')->after('size');
            $table->longText('extracted_text')->nullable()->after('extract_status');
            $table->text('extract_error')->nullable()->after('extracted_text');
        });
    }

    public function down(): void
    {
        Schema::table('message_attachments', function (Blueprint $table) {
            $table->dropColumn(['extract_status', 'extracted_text', 'extract_error']);
        });
    }
};
