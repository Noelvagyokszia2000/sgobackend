<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('robberies', function (Blueprint $table) {
            $table->boolean('activity_excluded')->default(false)->after('finished');
        });
    }

    public function down(): void
    {
        Schema::table('robberies', function (Blueprint $table) {
            $table->dropColumn('activity_excluded');
        });
    }
};
