<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('robberies', function (Blueprint $table) {
            $table->boolean('with_allies')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('robberies', function (Blueprint $table) {
            $table->dropColumn('with_allies');
        });
    }
};
