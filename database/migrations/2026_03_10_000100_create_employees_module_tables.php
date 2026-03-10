<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['department_id', 'name']);
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('national_id')->nullable();
            $table->string('phone');
            $table->string('personal_email');
            $table->string('work_email')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employment_type')->default('Full-time');
            $table->string('employment_status')->default('Active');
            $table->date('join_date')->nullable();
            $table->foreignId('reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('work_location')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('profile_photo_path')->nullable();
            $table->decimal('basic_salary', 14, 2)->nullable();
            $table->string('pay_frequency')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('tin')->nullable();
            $table->string('ssnit')->nullable();
            $table->json('allowances')->nullable();
            $table->json('skills')->nullable();
            $table->text('bio')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('hours', 5, 2)->nullable();
            $table->string('status')->default('Present');
            $table->timestamps();
        });

        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type');
            $table->unsignedInteger('used_days')->default(0);
            $table->unsignedInteger('total_days')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_leave_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type');
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedInteger('days');
            $table->string('status')->default('Pending');
            $table->string('approved_by')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('pay_month');
            $table->decimal('gross', 14, 2)->default(0);
            $table->decimal('deductions', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->string('status')->default('Processed');
            $table->string('payslip_path')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('action');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_activity_logs');
        Schema::dropIfExists('employee_payrolls');
        Schema::dropIfExists('employee_leave_history');
        Schema::dropIfExists('employee_leave_balances');
        Schema::dropIfExists('employee_attendance');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }
};
