<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_reaction_removal_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('robbery_id')->nullable()->constrained('robberies')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('discord_user_id', 32);
            $table->string('discord_channel_id', 32);
            $table->string('discord_message_id', 32);
            $table->string('action', 20);
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['processed_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_reaction_removal_jobs');
    }
};
