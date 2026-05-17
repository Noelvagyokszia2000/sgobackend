<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vehicles', 'max_keys')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_keys')
                ->default(5)
                ->after('warns');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('vehicles', 'max_keys')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('max_keys');
        });
    }
};
