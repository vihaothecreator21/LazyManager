<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_day_offs', function (Blueprint $table): void {
            $table->id();
            $table->date('off_date');
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['off_date', 'employee_id']);
            $table->index('off_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_day_offs');
    }
};
