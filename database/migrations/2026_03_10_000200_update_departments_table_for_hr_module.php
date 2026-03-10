<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'code')) {
                $table->string('code')->nullable()->unique()->after('name');
            }
            if (!Schema::hasColumn('departments', 'description')) {
                $table->text('description')->nullable()->after('code');
            }
            if (!Schema::hasColumn('departments', 'manager_id')) {
                $table->foreignId('manager_id')->nullable()->after('description')->constrained('employees')->nullOnDelete();
            }
            if (!Schema::hasColumn('departments', 'status')) {
                $table->enum('status', ['Active', 'Inactive'])->default('Active')->after('manager_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'manager_id')) {
                $table->dropConstrainedForeignId('manager_id');
            }
            if (Schema::hasColumn('departments', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('departments', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('departments', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
