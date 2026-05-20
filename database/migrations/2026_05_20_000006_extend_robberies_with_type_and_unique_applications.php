<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('robberies', function (Blueprint $table) {
            $table->string('name')->default('Rablás')->after('created_by');
            $table->string('type', 20)->default('ATM')->after('name');
        });

        Schema::table('robbery_income_images', function (Blueprint $table) {
            $table->unsignedInteger('drilled_count')->default(0)->after('amount');
            $table->unsignedInteger('fee_amount')->default(0)->after('drilled_count');
            $table->unsignedInteger('net_amount')->default(0)->after('fee_amount');
        });

        Schema::create('robbery_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('robbery_id')->constrained('robberies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['robbery_id', 'user_id']);
        });

        Schema::create('robbery_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('robbery_id')->constrained('robberies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['robbery_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('robbery_payout_requests');
        Schema::dropIfExists('robbery_participants');

        Schema::table('robbery_income_images', function (Blueprint $table) {
            $table->dropColumn(['drilled_count', 'fee_amount', 'net_amount']);
        });

        Schema::table('robberies', function (Blueprint $table) {
            $table->dropColumn(['name', 'type']);
        });
    }
};
