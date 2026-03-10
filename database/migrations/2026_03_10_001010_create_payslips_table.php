<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('basic_salary', 12, 2);
            $table->json('allowances')->nullable();
            $table->json('deductions')->nullable();
            $table->decimal('gross_salary', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('ssnit_employee', 12, 2)->default(0);
            $table->decimal('ssnit_employer', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2);
            $table->enum('payment_method', ['Bank Transfer', 'Cash', 'Cheque', 'Mobile Money'])->default('Bank Transfer');
            $table->enum('payment_status', ['Pending', 'Paid', 'Failed'])->default('Pending');
            $table->date('payment_date')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};

