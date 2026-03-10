<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_openings')) {
            return;
        }

        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->enum('employment_type', ['Full-time', 'Part-time', 'Contract', 'Intern', 'Remote'])->default('Full-time');
            $table->string('location')->nullable();
            $table->unsignedInteger('vacancies')->default(1);
            $table->decimal('salary_from', 10, 2)->nullable();
            $table->decimal('salary_to', 10, 2)->nullable();
            $table->string('salary_currency')->default('GHS');
            $table->longText('description')->nullable();
            $table->longText('requirements')->nullable();
            $table->longText('benefits')->nullable();
            $table->unsignedInteger('experience_years')->nullable();
            $table->string('education_level')->nullable();
            $table->enum('status', ['Draft', 'Open', 'Closed', 'On Hold'])->default('Draft');
            $table->date('deadline')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_openings');
    }
};

