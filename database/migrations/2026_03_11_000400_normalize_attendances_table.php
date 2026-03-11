<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance') && ! Schema::hasTable('attendances')) {
            Schema::rename('attendance', 'attendances');
        }

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'marked_by')) {
                $table->foreignId('marked_by')->nullable()->after('note')->constrained('employees')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('attendances')) {
            return;
        }

        try {
            Schema::table('attendances', function (Blueprint $table) {
                $table->unique(['employee_id', 'date']);
            });
        } catch (\Throwable $exception) {
            // Ignore if the unique index already exists.
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'marked_by')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropConstrainedForeignId('marked_by');
            });
        }

        if (Schema::hasTable('attendances') && ! Schema::hasTable('attendance')) {
            Schema::rename('attendances', 'attendance');
        }
    }
};
