<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_schedule_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_schedule')->constrained('shift_schedule');
            $table->foreignId('id_employee')->constrained('employees');
            $table->time('hour')->nullable();
            $table->boolean('status_lead')->default(false);
            $table->date('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedule_detail');
    }
};
