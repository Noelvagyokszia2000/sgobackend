<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            $table->string('name')->default('Ismeretlen jármű');

            $table->string('vin')->unique();

            $table->string('plate')->unique();

            $table->string('image')->nullable();

            $table->unsignedTinyInteger('warns')
                ->default(0);

            $table->unsignedTinyInteger('max_keys')
                ->default(5);

            $table->boolean('all_members')
                ->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
