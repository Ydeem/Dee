<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'avatar')) {
                $table->string('avatar')->nullable()->after('emergency_contact_phone');
            }
        });

        DB::statement('UPDATE employees SET avatar = profile_photo_path WHERE avatar IS NULL AND profile_photo_path IS NOT NULL');

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('personal_email');
            $table->unique('work_email');
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_documents', 'name')) {
                $table->string('name')->nullable()->after('employee_id');
            }
            if (! Schema::hasColumn('employee_documents', 'type')) {
                $table->string('type')->nullable()->after('name');
            }
            if (! Schema::hasColumn('employee_documents', 'file_size')) {
                $table->unsignedInteger('file_size')->nullable()->after('file_path');
            }
            if (! Schema::hasColumn('employee_documents', 'uploaded_by')) {
                $table->foreignId('uploaded_by')->nullable()->after('mime_type')->constrained('employees')->nullOnDelete();
            }
        });

        DB::statement('UPDATE employee_documents SET name = COALESCE(name, file_name)');
        DB::statement('UPDATE employee_documents SET type = COALESCE(type, category)');
        DB::statement('UPDATE employee_documents SET file_size = COALESCE(file_size, size)');
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            if (Schema::hasColumn('employee_documents', 'uploaded_by')) {
                $table->dropConstrainedForeignId('uploaded_by');
            }
            if (Schema::hasColumn('employee_documents', 'file_size')) {
                $table->dropColumn('file_size');
            }
            if (Schema::hasColumn('employee_documents', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('employee_documents', 'name')) {
                $table->dropColumn('name');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['personal_email']);
            $table->dropUnique(['work_email']);
            if (Schema::hasColumn('employees', 'avatar')) {
                $table->dropColumn('avatar');
            }
        });
    }
};
