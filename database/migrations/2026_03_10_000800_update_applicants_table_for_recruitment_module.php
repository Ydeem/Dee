<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('applicants')) {
            Schema::create('applicants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_opening_id')->constrained('job_openings')->cascadeOnDelete();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->string('location')->nullable();
                $table->unsignedInteger('experience_years')->nullable();
                $table->string('education_level')->nullable();
                $table->string('current_employer')->nullable();
                $table->string('current_role')->nullable();
                $table->decimal('expected_salary', 10, 2)->nullable();
                $table->string('notice_period')->nullable();
                $table->longText('cover_letter')->nullable();
                $table->string('resume_path')->nullable();
                $table->string('source')->nullable();
                $table->enum('status', ['New', 'Reviewing', 'Shortlisted', 'Interview Scheduled', 'Interviewed', 'Offer Sent', 'Hired', 'Rejected', 'Withdrawn'])->default('New');
                $table->unsignedTinyInteger('stage')->default(1);
                $table->unsignedTinyInteger('rating')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
                $table->timestamp('interview_date')->nullable();
                $table->date('offer_date')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('applicants', function (Blueprint $table) {
            if (!Schema::hasColumn('applicants', 'first_name')) {
                $table->string('first_name')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'last_name')) {
                $table->string('last_name')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'location')) {
                $table->string('location')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'experience_years')) {
                $table->unsignedInteger('experience_years')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'education_level')) {
                $table->string('education_level')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'current_employer')) {
                $table->string('current_employer')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'current_role')) {
                $table->string('current_role')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'expected_salary')) {
                $table->decimal('expected_salary', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('applicants', 'notice_period')) {
                $table->string('notice_period')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'cover_letter')) {
                $table->longText('cover_letter')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'resume_path')) {
                $table->string('resume_path')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'source')) {
                $table->string('source')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'stage')) {
                $table->unsignedTinyInteger('stage')->default(1);
            }
            if (!Schema::hasColumn('applicants', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
            }
            if (!Schema::hasColumn('applicants', 'interview_date')) {
                $table->timestamp('interview_date')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'offer_date')) {
                $table->date('offer_date')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non-destructive rollback for additive migration.
    }
};

