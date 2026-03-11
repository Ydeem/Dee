<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_openings')) {
            return;
        }

        Schema::table('job_openings', function (Blueprint $table) {
            if (! Schema::hasColumn('job_openings', 'min_salary')) {
                $table->decimal('min_salary', 12, 2)->nullable()->after('vacancies');
            }

            if (! Schema::hasColumn('job_openings', 'max_salary')) {
                $table->decimal('max_salary', 12, 2)->nullable()->after('min_salary');
            }

            if (! Schema::hasColumn('job_openings', 'responsibilities')) {
                $table->text('responsibilities')->nullable()->after('requirements');
            }

            if (! Schema::hasColumn('job_openings', 'benefits')) {
                $table->text('benefits')->nullable()->after('responsibilities');
            }
        });

        if (Schema::hasColumn('job_openings', 'salary_from')) {
            DB::statement('UPDATE job_openings SET min_salary = salary_from WHERE min_salary IS NULL AND salary_from IS NOT NULL');
        }

        if (Schema::hasColumn('job_openings', 'salary_to')) {
            DB::statement('UPDATE job_openings SET max_salary = salary_to WHERE max_salary IS NULL AND salary_to IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('job_openings')) {
            return;
        }

        Schema::table('job_openings', function (Blueprint $table) {
            foreach (['responsibilities', 'max_salary', 'min_salary'] as $column) {
                if (Schema::hasColumn('job_openings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
