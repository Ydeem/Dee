<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            if (!Schema::hasColumn('designations', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('designations', 'level')) {
                $table->string('level')->nullable()->after('description');
            }
            if (!Schema::hasColumn('designations', 'status')) {
                $table->enum('status', ['Active', 'Inactive'])->default('Active')->after('level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            if (Schema::hasColumn('designations', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('designations', 'level')) {
                $table->dropColumn('level');
            }
            if (Schema::hasColumn('designations', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
