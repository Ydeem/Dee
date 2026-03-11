<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_types', 'description')) {
                $table->text('description')->nullable()->after('color');
            }

            if (! Schema::hasColumn('leave_types', 'max_carry_forward_days')) {
                $table->unsignedInteger('max_carry_forward_days')->nullable()->after('carry_forward');
            }

            if (! Schema::hasColumn('leave_types', 'applicable_gender')) {
                $table->string('applicable_gender')->nullable()->after('max_carry_forward_days');
            }
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_requests', 'rejected_by')) {
                $table->foreignId('rejected_by')
                    ->nullable()
                    ->after('approved_by')
                    ->constrained('employees')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (Schema::hasColumn('leave_requests', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }
        });

        Schema::table('leave_types', function (Blueprint $table) {
            foreach (['description', 'max_carry_forward_days', 'applicable_gender'] as $column) {
                if (Schema::hasColumn('leave_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
