<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_has_roles', static function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_roles', 'assigned_by')) {
                $table->unsignedBigInteger('assigned_by')->nullable()->after('model_id');
                $table->index('assigned_by');
            }

            if (!Schema::hasColumn('model_has_roles', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            }

            if (!Schema::hasColumn('model_has_roles', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('assigned_at');
            }

            if (!Schema::hasColumn('model_has_roles', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('model_has_roles', static function (Blueprint $table) {
            if (Schema::hasColumn('model_has_roles', 'updated_at')) {
                $table->dropColumn('updated_at');
            }

            if (Schema::hasColumn('model_has_roles', 'created_at')) {
                $table->dropColumn('created_at');
            }

            if (Schema::hasColumn('model_has_roles', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }

            if (Schema::hasColumn('model_has_roles', 'assigned_by')) {
                $table->dropIndex(['assigned_by']);
                $table->dropColumn('assigned_by');
            }
        });
    }
};
