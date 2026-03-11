<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hr_settings')) {
            return;
        }

        $columnsToDrop = [];
        foreach (['label', 'type', 'options'] as $column) {
            if (Schema::hasColumn('hr_settings', $column)) {
                $columnsToDrop[] = $column;
            }
        }

        if (!empty($columnsToDrop)) {
            Schema::table('hr_settings', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('hr_settings')) {
            return;
        }

        Schema::table('hr_settings', function (Blueprint $table): void {
            if (!Schema::hasColumn('hr_settings', 'label')) {
                $table->string('label')->nullable();
            }
            if (!Schema::hasColumn('hr_settings', 'type')) {
                $table->string('type')->default('text');
            }
            if (!Schema::hasColumn('hr_settings', 'options')) {
                $table->json('options')->nullable();
            }
        });
    }
};

