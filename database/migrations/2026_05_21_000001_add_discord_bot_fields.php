<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_id')->nullable()->unique()->after('username');
        });

        Schema::table('robberies', function (Blueprint $table) {
            $table->string('discord_channel_id')->nullable()->after('finished');
            $table->string('discord_message_id')->nullable()->unique()->after('discord_channel_id');
            $table->timestamp('discord_announced_at')->nullable()->after('discord_message_id');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->string('discord_channel_id')->nullable()->after('deleted_at');
            $table->string('discord_message_id')->nullable()->unique()->after('discord_channel_id');
            $table->timestamp('discord_announced_at')->nullable()->after('discord_message_id');
        });

        DB::table('robberies')->update([
            'discord_announced_at' => now(),
        ]);

        DB::table('news')->update([
            'discord_announced_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn([
                'discord_channel_id',
                'discord_message_id',
                'discord_announced_at',
            ]);
        });

        Schema::table('robberies', function (Blueprint $table) {
            $table->dropColumn([
                'discord_channel_id',
                'discord_message_id',
                'discord_announced_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('discord_id');
        });
    }
};
