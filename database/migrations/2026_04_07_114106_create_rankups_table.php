<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rankups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('issued_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('previous_rank_id')
                ->nullable()
                ->constrained('ranks')
                ->nullOnDelete();

            $table->foreignId('next_rank_id')
                ->constrained('ranks')
                ->onDelete('cascade');

            $table->timestamp('issued_at_site')->useCurrent();
            $table->timestamp('issued_at_game')->nullable();

            $table->boolean('completed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rankups');
    }
};