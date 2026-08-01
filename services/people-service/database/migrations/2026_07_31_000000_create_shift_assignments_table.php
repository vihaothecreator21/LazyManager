<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->date('work_date');
            $table->string('shift_type', 32);
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['work_date', 'shift_type', 'employee_id']);
            $table->index(['work_date', 'shift_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
