<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_onboardings')) {
            return;
        }

        Schema::create('employee_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('onboarding_template_id')->constrained('onboarding_templates')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('expected_end_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->enum('status', ['Not Started', 'In Progress', 'Completed', 'Overdue', 'Cancelled'])->default('Not Started');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_buddy_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_onboardings');
    }
};

