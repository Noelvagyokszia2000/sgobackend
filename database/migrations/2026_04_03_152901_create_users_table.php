<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('IgName');
            $table->date('createdAt')->nullable();
            $table->integer('warn')->default(0);
            $table->date('weeklyPay')->nullable();
            $table->boolean('isAdmin')->default(false);
            $table->foreignId('rank_id')->nullable()->constrained('ranks')->nullOnDelete();
            $table->timestamp('lastRankup')->nullable();
            $table->string('profileImage')->nullable();
            $table->unsignedInteger('successfulCassettes')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
