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
        Schema::create('shift_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_employee')->constrained('employees');
            $table->foreignId('shift_id')->constrained('master_shift');
            $table->string('status');
            $table->foreignId('publish_by')->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_schedule');
    }
};