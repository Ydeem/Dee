<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('onboarding_templates')) {
            Schema::table('onboarding_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('onboarding_templates', 'days_to_complete')) {
                    $table->integer('days_to_complete')->default(30);
                }
            });
        }

        if (Schema::hasTable('onboarding_tasks')) {
            Schema::table('onboarding_tasks', function (Blueprint $table) {
                if (! Schema::hasColumn('onboarding_tasks', 'template_id')) {
                    $table->unsignedBigInteger('template_id')->nullable()->index();
                }
                if (! Schema::hasColumn('onboarding_tasks', 'required')) {
                    $table->boolean('required')->default(true);
                }
            });

            if (Schema::hasColumn('onboarding_tasks', 'onboarding_template_id')) {
                DB::table('onboarding_tasks')->update([
                    'template_id' => DB::raw('onboarding_template_id'),
                ]);
            }
            if (Schema::hasColumn('onboarding_tasks', 'is_required')) {
                DB::table('onboarding_tasks')->update([
                    'required' => DB::raw('is_required'),
                ]);
            }
        }

        if (Schema::hasTable('employee_onboardings')) {
            Schema::table('employee_onboardings', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_onboardings', 'template_id')) {
                    $table->unsignedBigInteger('template_id')->nullable()->index();
                }
                if (! Schema::hasColumn('employee_onboardings', 'buddy_id')) {
                    $table->unsignedBigInteger('buddy_id')->nullable()->index();
                }
                if (! Schema::hasColumn('employee_onboardings', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
            });

            if (Schema::hasColumn('employee_onboardings', 'onboarding_template_id')) {
                DB::table('employee_onboardings')->update([
                    'template_id' => DB::raw('onboarding_template_id'),
                ]);
            }
            if (Schema::hasColumn('employee_onboardings', 'assigned_buddy_id')) {
                DB::table('employee_onboardings')->update([
                    'buddy_id' => DB::raw('assigned_buddy_id'),
                ]);
            }
            if (Schema::hasColumn('employee_onboardings', 'completed_date')) {
                DB::table('employee_onboardings')->update([
                    'completed_at' => DB::raw('completed_date'),
                ]);
            }
        }

        if (Schema::hasTable('onboarding_task_progress')) {
            Schema::table('onboarding_task_progress', function (Blueprint $table) {
                if (! Schema::hasColumn('onboarding_task_progress', 'onboarding_id')) {
                    $table->unsignedBigInteger('onboarding_id')->nullable()->index();
                }
                if (! Schema::hasColumn('onboarding_task_progress', 'task_id')) {
                    $table->unsignedBigInteger('task_id')->nullable()->index();
                }
            });

            if (Schema::hasColumn('onboarding_task_progress', 'employee_onboarding_id')) {
                DB::table('onboarding_task_progress')->update([
                    'onboarding_id' => DB::raw('employee_onboarding_id'),
                ]);
            }
            if (Schema::hasColumn('onboarding_task_progress', 'onboarding_task_id')) {
                DB::table('onboarding_task_progress')->update([
                    'task_id' => DB::raw('onboarding_task_id'),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data-alignment migration: intentionally no destructive rollback.
    }
};

