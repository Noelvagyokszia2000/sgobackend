<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('robberies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('participants_count')->default(0);
            $table->unsignedInteger('applicants_count')->default(0);
            $table->boolean('finished')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('robberies');
    }
};
