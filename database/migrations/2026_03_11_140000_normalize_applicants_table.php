<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applicants')) {
            return;
        }

        Schema::table('applicants', function (Blueprint $table) {
            if (! Schema::hasColumn('applicants', 'resume')) {
                $table->string('resume')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('applicants', 'current_company')) {
                $table->string('current_company')->nullable()->after('experience_years');
            }
            if (! Schema::hasColumn('applicants', 'current_position')) {
                $table->string('current_position')->nullable()->after('current_company');
            }
            if (! Schema::hasColumn('applicants', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('applicants', 'interviewed_at')) {
                $table->timestamp('interviewed_at')->nullable()->after('rejected_reason');
            }
            if (! Schema::hasColumn('applicants', 'hired_at')) {
                $table->timestamp('hired_at')->nullable()->after('interviewed_at');
            }
            if (! Schema::hasColumn('applicants', 'converted_employee_id')) {
                $table->foreignId('converted_employee_id')->nullable()->after('hired_at')->constrained('employees')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('applicants', 'resume_path')) {
            DB::statement('UPDATE applicants SET resume = resume_path WHERE resume IS NULL AND resume_path IS NOT NULL');
        }
        if (Schema::hasColumn('applicants', 'current_employer')) {
            DB::statement('UPDATE applicants SET current_company = current_employer WHERE current_company IS NULL AND current_employer IS NOT NULL');
        }
        if (Schema::hasColumn('applicants', 'current_role')) {
            DB::statement('UPDATE applicants SET current_position = current_role WHERE current_position IS NULL AND current_role IS NOT NULL');
        }
        if (Schema::hasColumn('applicants', 'rejection_reason')) {
            DB::statement('UPDATE applicants SET rejected_reason = rejection_reason WHERE rejected_reason IS NULL AND rejection_reason IS NOT NULL');
        }
        if (Schema::hasColumn('applicants', 'interview_date')) {
            DB::statement('UPDATE applicants SET interviewed_at = interview_date WHERE interviewed_at IS NULL AND interview_date IS NOT NULL');
        }
        if (Schema::hasColumn('applicants', 'offer_date')) {
            DB::statement("UPDATE applicants SET hired_at = offer_date WHERE hired_at IS NULL AND status = 'Hired' AND offer_date IS NOT NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('applicants')) {
            return;
        }

        Schema::table('applicants', function (Blueprint $table) {
            if (Schema::hasColumn('applicants', 'converted_employee_id')) {
                $table->dropConstrainedForeignId('converted_employee_id');
            }
        });
    }
};
