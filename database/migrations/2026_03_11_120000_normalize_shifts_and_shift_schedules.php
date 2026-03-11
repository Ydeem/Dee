<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shifts')) {
            Schema::table('shifts', function (Blueprint $table) {
                if (! Schema::hasColumn('shifts', 'color')) {
                    $table->string('color')->nullable()->after('end_time');
                }

                if (! Schema::hasColumn('shifts', 'working_days')) {
                    $table->json('working_days')->nullable()->after('color');
                }

                if (! Schema::hasColumn('shifts', 'break_duration')) {
                    $table->unsignedInteger('break_duration')->default(60)->after('working_days');
                }

                if (! Schema::hasColumn('shifts', 'description')) {
                    $table->text('description')->nullable()->after('break_duration');
                }

                if (! Schema::hasColumn('shifts', 'status')) {
                    $table->string('status')->default('Active')->after('description');
                }
            });

            DB::table('shifts')
                ->whereNull('break_duration')
                ->update(['break_duration' => 60]);

            DB::table('shifts')
                ->whereNull('working_days')
                ->update(['working_days' => json_encode(['Mon', 'Tue', 'Wed', 'Thu', 'Fri'])]);

            DB::table('shifts')
                ->whereNull('status')
                ->update(['status' => 'Active']);
        }

        if (Schema::hasTable('shift_schedules')) {
            Schema::table('shift_schedules', function (Blueprint $table) {
                if (! Schema::hasColumn('shift_schedules', 'status')) {
                    $table->string('status')->default('Active')->after('effective_to');
                }

                if (! Schema::hasColumn('shift_schedules', 'assigned_by')) {
                    $table->foreignId('assigned_by')->nullable()->after('status')->constrained('employees')->nullOnDelete();
                }

                if (! Schema::hasColumn('shift_schedules', 'note')) {
                    $table->text('note')->nullable()->after('assigned_by');
                }
            });

            DB::table('shift_schedules')
                ->whereNull('status')
                ->update(['status' => 'Active']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shift_schedules')) {
            Schema::table('shift_schedules', function (Blueprint $table) {
                if (Schema::hasColumn('shift_schedules', 'assigned_by')) {
                    $table->dropConstrainedForeignId('assigned_by');
                }

                if (Schema::hasColumn('shift_schedules', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }

        if (Schema::hasTable('shifts')) {
            Schema::table('shifts', function (Blueprint $table) {
                if (Schema::hasColumn('shifts', 'description')) {
                    $table->dropColumn('description');
                }
            });
        }
    }
};
