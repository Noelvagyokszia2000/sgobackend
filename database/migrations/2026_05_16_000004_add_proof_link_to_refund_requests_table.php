<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('refund_requests', 'proof_link')) {
            return;
        }

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->string('proof_link', 2048)->after('amount');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('refund_requests', 'proof_link')) {
            return;
        }

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropColumn('proof_link');
        });
    }
};
