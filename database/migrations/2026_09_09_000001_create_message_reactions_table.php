<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction_key', 32);
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'reaction_key']);
            $table->index(['message_id', 'reaction_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
