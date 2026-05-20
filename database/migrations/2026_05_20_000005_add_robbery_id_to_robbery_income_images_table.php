<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('robbery_income_images', function (Blueprint $table) {
            $table->foreignId('robbery_id')
                ->nullable()
                ->after('id')
                ->constrained('robberies')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('robbery_income_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('robbery_id');
        });
    }
};
