<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('onboarding_task_progress')) {
            return;
        }

        Schema::create('onboarding_task_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_onboarding_id')->constrained('employee_onboardings')->cascadeOnDelete();
            $table->foreignId('onboarding_task_id')->constrained('onboarding_tasks')->cascadeOnDelete();
            $table->enum('status', ['Pending', 'In Progress', 'Completed', 'Skipped'])->default('Pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_task_progress');
    }
};

