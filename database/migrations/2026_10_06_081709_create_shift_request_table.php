<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shift_request', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('schedule_id')->constrained('shift_schedule');
            $table->foreignId('leave_id')->nullable()->constrained('leave_type');
            $table->foreignId('partner_id')->nullable()->constrained('employees');
            $table->foreignId('partner_schedule_id')->nullable()->constrained('shift_schedule');
            $table->string('status');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_request');
    }
};