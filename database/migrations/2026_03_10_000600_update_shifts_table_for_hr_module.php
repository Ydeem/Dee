<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->time('start_time');
                $table->time('end_time');
                $table->unsignedInteger('break_duration')->nullable();
                $table->json('working_days');
                $table->string('color')->nullable();
                $table->enum('status', ['Active', 'Inactive'])->default('Active');
                $table->timestamps();
            });
            return;
        }

        Schema::table('shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('shifts', 'break_duration')) {
                $table->unsignedInteger('break_duration')->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('shifts', 'working_days')) {
                $table->json('working_days')->nullable()->after('break_duration');
            }
            if (!Schema::hasColumn('shifts', 'color')) {
                $table->string('color')->nullable()->after('working_days');
            }
            if (!Schema::hasColumn('shifts', 'status')) {
                $table->enum('status', ['Active', 'Inactive'])->default('Active')->after('color');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('shifts')) {
            return;
        }

        Schema::table('shifts', function (Blueprint $table) {
            if (Schema::hasColumn('shifts', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('shifts', 'color')) {
                $table->dropColumn('color');
            }
            if (Schema::hasColumn('shifts', 'working_days')) {
                $table->dropColumn('working_days');
            }
            if (Schema::hasColumn('shifts', 'break_duration')) {
                $table->dropColumn('break_duration');
            }
        });
    }
};
