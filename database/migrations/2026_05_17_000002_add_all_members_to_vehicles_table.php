<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vehicles', 'all_members')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('all_members')
                ->default(false)
                ->after('max_keys');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('vehicles', 'all_members')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('all_members');
        });
    }
};
