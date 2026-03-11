<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            if (! Schema::hasColumn('designations', 'min_salary')) {
                $table->decimal('min_salary', 12, 2)->nullable()->after('status');
            }

            if (! Schema::hasColumn('designations', 'max_salary')) {
                $table->decimal('max_salary', 12, 2)->nullable()->after('min_salary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            if (Schema::hasColumn('designations', 'max_salary')) {
                $table->dropColumn('max_salary');
            }

            if (Schema::hasColumn('designations', 'min_salary')) {
                $table->dropColumn('min_salary');
            }
        });
    }
};
