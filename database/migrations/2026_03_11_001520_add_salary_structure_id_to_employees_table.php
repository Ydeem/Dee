<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'salary_structure_id')) {
                $table->foreignId('salary_structure_id')->nullable()->constrained('salary_structures')->nullOnDelete();
            }
        });

        if (Schema::hasTable('salary_structures') && Schema::hasColumn('salary_structures', 'employee_id')) {
            $pairs = DB::table('salary_structures')
                ->whereNotNull('employee_id')
                ->select('employee_id', 'id')
                ->get();

            foreach ($pairs as $pair) {
                DB::table('employees')
                    ->where('id', $pair->employee_id)
                    ->update(['salary_structure_id' => $pair->id]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'salary_structure_id')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salary_structure_id');
        });
    }
};

