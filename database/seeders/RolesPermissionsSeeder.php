<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view hr dashboard',
            'view employees', 'create employees', 'edit employees', 'delete employees', 'export employees',
            'view departments', 'create departments', 'edit departments', 'delete departments',
            'view designations', 'create designations', 'edit designations', 'delete designations',
            'view attendance', 'create attendance', 'edit attendance', 'delete attendance', 'mark bulk attendance',
            'view leave requests', 'create leave requests', 'approve leave requests', 'reject leave requests', 'delete leave requests', 'manage leave types',
            'view shifts', 'create shifts', 'edit shifts', 'delete shifts', 'assign shifts',
            'view job openings', 'create job openings', 'edit job openings', 'delete job openings',
            'view applicants', 'create applicants', 'edit applicants', 'delete applicants', 'move applicant stage', 'convert to employee',
            'view onboarding', 'manage onboarding',
            'view payroll', 'create payroll', 'process payroll', 'approve payroll', 'delete payroll', 'view payslips', 'edit payslips', 'manage salary structures',
            'view expenses', 'create expenses', 'approve expenses', 'reject expenses', 'delete expenses', 'mark expenses paid',
            'view reports', 'export reports',
            'view hr settings', 'edit hr settings', 'manage roles', 'manage permissions', 'assign roles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'HR Admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions);

        $manager = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'web']);
        $manager->syncPermissions(array_filter($permissions, fn ($permission) =>
            !str_contains($permission, 'delete')
            && !str_contains($permission, 'manage roles')
            && !str_contains($permission, 'manage permissions')
            && $permission !== 'edit hr settings'
        ));

        $payroll = Role::firstOrCreate(['name' => 'Payroll Officer', 'guard_name' => 'web']);
        $payroll->syncPermissions([
            'view hr dashboard', 'view employees',
            'view payroll', 'create payroll', 'process payroll', 'approve payroll', 'view payslips', 'edit payslips',
            'manage salary structures',
            'view expenses', 'approve expenses', 'reject expenses', 'mark expenses paid',
            'view reports', 'export reports',
        ]);

        $recruiter = Role::firstOrCreate(['name' => 'Recruiter', 'guard_name' => 'web']);
        $recruiter->syncPermissions([
            'view hr dashboard', 'view employees',
            'view job openings', 'create job openings', 'edit job openings',
            'view applicants', 'create applicants', 'edit applicants', 'move applicant stage', 'convert to employee',
            'view onboarding', 'manage onboarding', 'view reports',
        ]);

        $supervisor = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);
        $supervisor->syncPermissions([
            'view hr dashboard', 'view employees',
            'view attendance', 'create attendance',
            'view leave requests', 'approve leave requests', 'reject leave requests',
            'view shifts', 'assign shifts',
            'view reports',
        ]);

        $employee = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
        $employee->syncPermissions([
            'view hr dashboard',
            'view employees',
            'view leave requests', 'create leave requests',
            'view attendance',
            'view payslips',
            'create expenses', 'view expenses',
        ]);

        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->syncRoles(['HR Admin']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
