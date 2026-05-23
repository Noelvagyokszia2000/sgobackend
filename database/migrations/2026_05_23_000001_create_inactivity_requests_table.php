<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inactivity_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->date('endingReminderSentFor')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
            $table->index(['user_id', 'status']);
            $table->index(['end_date', 'endingReminderSentFor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inactivity_requests');
    }
};
