<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', static function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'description')) {
                $table->string('description')->nullable()->after('name');
            }

            if (!Schema::hasColumn('roles', 'color')) {
                $table->string('color', 50)->nullable()->after('description');
            }

            if (!Schema::hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('color');
            }
        });

        Schema::table('permissions', static function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'module')) {
                $table->string('module', 100)->nullable()->after('name');
            }

            if (!Schema::hasColumn('permissions', 'label')) {
                $table->string('label')->nullable()->after('module');
            }
        });

        DB::table('roles')
            ->whereIn('name', ['HR Admin', 'HR Manager', 'Payroll Officer', 'Recruiter', 'Supervisor', 'Employee'])
            ->update(['is_system' => true]);

        foreach ([
            'HR Admin' => 'primary',
            'HR Manager' => 'cyan',
            'Payroll Officer' => 'success',
            'Recruiter' => 'purple',
            'Supervisor' => 'warning',
            'Employee' => 'secondary',
        ] as $name => $color) {
            DB::table('roles')
                ->where('name', $name)
                ->whereNull('color')
                ->update(['color' => $color]);
        }

        DB::table('permissions')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunkById(200, function ($permissions): void {
                foreach ($permissions as $permission) {
                    DB::table('permissions')
                        ->where('id', $permission->id)
                        ->update([
                            'module' => $this->inferModule((string) $permission->name),
                            'label' => Str::title((string) $permission->name),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('permissions', static function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'label')) {
                $table->dropColumn('label');
            }

            if (Schema::hasColumn('permissions', 'module')) {
                $table->dropColumn('module');
            }
        });

        Schema::table('roles', static function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'is_system')) {
                $table->dropColumn('is_system');
            }

            if (Schema::hasColumn('roles', 'color')) {
                $table->dropColumn('color');
            }

            if (Schema::hasColumn('roles', 'description')) {
                $table->dropColumn('description');
            }
        });
    }

    private function inferModule(string $permissionName): string
    {
        return match (true) {
            str_contains($permissionName, 'dashboard') => 'dashboard',
            str_contains($permissionName, 'employees') => 'employees',
            str_contains($permissionName, 'departments') => 'departments',
            str_contains($permissionName, 'designations') => 'designations',
            str_contains($permissionName, 'attendance') => 'attendance',
            str_contains($permissionName, 'leave') => 'leave_management',
            str_contains($permissionName, 'shifts') => 'shifts',
            str_contains($permissionName, 'job openings'),
            str_contains($permissionName, 'applicants'),
            str_contains($permissionName, 'onboarding'),
            str_contains($permissionName, 'employee') => 'recruitment',
            str_contains($permissionName, 'payroll'),
            str_contains($permissionName, 'payslips'),
            str_contains($permissionName, 'salary structures') => 'payroll',
            str_contains($permissionName, 'expenses') => 'expenses',
            str_contains($permissionName, 'reports') => 'reports',
            str_contains($permissionName, 'settings'),
            str_contains($permissionName, 'roles'),
            str_contains($permissionName, 'permissions') => 'settings',
            default => 'general',
        };
    }
};
