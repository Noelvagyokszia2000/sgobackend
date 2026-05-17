<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_keys', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('status', 24)->default('approved');

            $table->timestamps();

            $table->unique(
                ['vehicle_id', 'user_id'],
                'vehicle_keys_vehicle_id_user_id_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_keys');
    }
};
