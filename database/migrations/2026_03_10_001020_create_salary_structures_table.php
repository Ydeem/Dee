<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('basic_salary', 12, 2);
            $table->json('allowances')->nullable();
            $table->date('effective_date');
            $table->string('currency')->default('GHS');
            $table->enum('pay_frequency', ['Monthly', 'Bi-weekly', 'Weekly'])->default('Monthly');
            $table->timestamps();

            $table->unique('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structures');
    }
};

