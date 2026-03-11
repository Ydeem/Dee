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
            $table->foreignId('template_id')->nullable()->constrained('onboarding_templates')->nullOnDelete();
            $table->foreignId('buddy_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('start_date');
            $table->date('expected_end_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('Not Started');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_onboardings');
    }
};
