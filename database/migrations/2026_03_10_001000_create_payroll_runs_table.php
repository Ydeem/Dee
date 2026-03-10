<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->tinyInteger('period_month');
            $table->smallInteger('period_year');
            $table->date('pay_date');
            $table->enum('status', ['Draft', 'Processing', 'Pending Approval', 'Approved', 'Paid', 'Cancelled'])->default('Draft');
            $table->decimal('total_gross', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_net', 12, 2)->default(0);
            $table->integer('employee_count')->default(0);
            $table->foreignId('processed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['period_month', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};

