<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeActivityLog;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeDocument;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeLeaveHistory;
use App\Models\EmployeePayroll;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = Employee::query()->with(['department:id,name', 'designation:id,name']);

        if ($search = $request->query('search')) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->whereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$search}%"])
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('personal_email', 'like', "%{$search}%")
                    ->orWhere('work_email', 'like', "%{$search}%");
            });
        }

        if ($department = $request->query('department')) {
            $query->whereHas('department', fn ($builder) => $builder->where('name', $department));
        }

        if ($designation = $request->query('designation')) {
            $query->whereHas('designation', fn ($builder) => $builder->where('name', $designation));
        }

        if ($type = $request->query('type')) {
            $query->where('employment_type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('employment_status', $status);
        }

        $sortBy = $request->query('sort_by', 'created_at');
        $sortDirection = $request->query('sort_dir', 'desc');
        if ($sortBy === 'department') {
            $query->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
                ->orderBy('departments.name', $sortDirection === 'asc' ? 'asc' : 'desc')
                ->select('employees.*');
        } elseif (in_array($sortBy, ['first_name', 'join_date', 'created_at'], true)) {
            $query->orderBy($sortBy, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        return response()->json([
            'employees' => $query->paginate(in_array($perPage, [10, 25, 50], true) ? $perPage : 10),
            'filters' => [
                'departments' => Department::orderBy('name')->pluck('name'),
                'designations' => Designation::orderBy('name')->pluck('name'),
            ],
        ]);
    }

    public function options()
    {
        return response()->json([
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'designations' => Designation::orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::orderBy('name')->get(['id', 'name']),
            'managers' => Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateEmployee($request);

        return DB::transaction(function () use ($request, $validated) {
            $employee = new Employee($this->mapEmployeeData($validated));
            $employee->employee_id = $validated['employee_id'] ?? $this->nextEmployeeId();

            if ($request->hasFile('profile_photo')) {
                $employee->profile_photo_path = $request->file('profile_photo')->store('employees/photos', 'public');
            }

            $employee->save();
            $this->syncDocuments($request, $employee);

            EmployeeActivityLog::create([
                'employee_id' => $employee->id,
                'actor_name' => optional($request->user())->name,
                'action' => 'Created',
                'description' => 'Employee record was created.',
            ]);

            return response()->json([
                'message' => 'Employee created successfully.',
                'employee' => $employee->load(['department:id,name', 'designation:id,name']),
            ], 201);
        });
    }

    public function show(int $id)
    {
        $employee = Employee::findOrFail($id);
        $employee->load(['department:id,name', 'designation:id,name', 'manager:id,first_name,last_name,employee_id', 'shift:id,name', 'directReports:id,first_name,last_name,employee_id,profile_photo_path']);

        return response()->json([
            'employee' => $employee,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $employee = Employee::findOrFail($id);
        $validated = $this->validateEmployee($request, $employee->id);

        return DB::transaction(function () use ($request, $employee, $validated) {
            $employee->fill($this->mapEmployeeData($validated));

            if ($request->hasFile('profile_photo')) {
                if ($employee->profile_photo_path) {
                    Storage::disk('public')->delete($employee->profile_photo_path);
                }
                $employee->profile_photo_path = $request->file('profile_photo')->store('employees/photos', 'public');
            }

            $employee->save();
            $this->syncDocuments($request, $employee);

            EmployeeActivityLog::create([
                'employee_id' => $employee->id,
                'actor_name' => optional($request->user())->name,
                'action' => 'Updated',
                'description' => 'Employee record was updated.',
            ]);

            return response()->json([
                'message' => 'Employee updated successfully.',
                'employee' => $employee->load(['department:id,name', 'designation:id,name']),
            ]);
        });
    }

    public function destroy(Request $request, int $id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        EmployeeActivityLog::create([
            'employee_id' => $employee->id,
            'actor_name' => optional($request->user())->name,
            'action' => 'Deleted',
            'description' => 'Employee record was deleted.',
        ]);

        return response()->json(['message' => 'Employee deleted successfully.']);
    }

    public function setStatus(Request $request, int $id)
    {
        $employee = Employee::findOrFail($id);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Active', 'Inactive', 'On Leave', 'Probation'])],
        ]);

        $employee->update(['employment_status' => $validated['status']]);

        EmployeeActivityLog::create([
            'employee_id' => $employee->id,
            'actor_name' => optional($request->user())->name,
            'action' => 'Status Changed',
            'description' => "Status set to {$validated['status']}.",
        ]);

        return response()->json(['message' => 'Status updated successfully.']);
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'action' => ['required', Rule::in(['activate', 'deactivate'])],
        ]);

        $status = $validated['action'] === 'activate' ? 'Active' : 'Inactive';
        Employee::whereIn('id', $validated['employee_ids'])->update(['employment_status' => $status]);

        return response()->json(['message' => 'Bulk action completed successfully.']);
    }

    public function attendance(int $id)
    {
        $employee = Employee::findOrFail($id);
        $records = EmployeeAttendance::where('employee_id', $employee->id)->orderByDesc('date')->get();

        $summary = [
            'present' => $records->where('status', 'Present')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'on_leave' => $records->where('status', 'On Leave')->count(),
        ];

        $calendar = $records->map(fn ($record) => [
            'date' => optional($record->date)->format('Y-m-d'),
            'status' => $record->status,
        ]);

        return response()->json([
            'summary' => $summary,
            'calendar' => $calendar,
            'history' => $records,
        ]);
    }

    public function leave(int $id)
    {
        $employee = Employee::findOrFail($id);
        return response()->json([
            'balances' => EmployeeLeaveBalance::where('employee_id', $employee->id)->get(),
            'history' => EmployeeLeaveHistory::where('employee_id', $employee->id)->orderByDesc('from_date')->get(),
        ]);
    }

    public function payroll(int $id)
    {
        $employee = Employee::findOrFail($id);
        return response()->json([
            'salary' => [
                'basic_salary' => $employee->basic_salary,
                'allowances' => $employee->allowances ?? [],
                'deductions' => 0,
                'net_pay' => (float) $employee->basic_salary + collect($employee->allowances ?? [])->sum('amount'),
            ],
            'history' => EmployeePayroll::where('employee_id', $employee->id)->orderByDesc('pay_month')->get(),
        ]);
    }

    public function documents(int $id)
    {
        $employee = Employee::findOrFail($id);
        return response()->json([
            'documents' => EmployeeDocument::where('employee_id', $employee->id)->latest()->get(),
        ]);
    }

    public function activityLog(int $id)
    {
        $employee = Employee::findOrFail($id);
        return response()->json([
            'activity_log' => EmployeeActivityLog::where('employee_id', $employee->id)->latest()->get(),
        ]);
    }

    public function deleteDocument(int $id, int $documentId)
    {
        $employee = Employee::findOrFail($id);
        $document = EmployeeDocument::findOrFail($documentId);
        if ($document->employee_id !== $employee->id) {
            abort(404);
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document deleted successfully.']);
    }

    private function validateEmployee(Request $request, ?int $employeeId = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'national_id' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'personal_email' => ['required', 'email', 'max:180'],
            'work_email' => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:180'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'employee_id' => ['nullable', 'string', 'max:40', Rule::unique('employees', 'employee_id')->ignore($employeeId)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'employment_type' => ['required', Rule::in(['Full-time', 'Part-time', 'Contract', 'Intern'])],
            'employment_status' => ['nullable', Rule::in(['Active', 'Probation', 'Inactive', 'On Leave'])],
            'join_date' => ['required', 'date'],
            'reporting_manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'work_location' => ['nullable', Rule::in(['Office', 'Remote', 'Hybrid'])],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'pay_frequency' => ['nullable', Rule::in(['Monthly', 'Bi-weekly', 'Weekly'])],
            'bank_name' => ['nullable', 'string', 'max:180'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:180'],
            'tin' => ['nullable', 'string', 'max:120'],
            'ssnit' => ['nullable', 'string', 'max:120'],
            'allowances' => ['nullable', 'array'],
            'allowances.*.type' => ['nullable', 'string', 'max:120'],
            'allowances.*.amount' => ['nullable', 'numeric', 'min:0'],
            'skills' => ['nullable', 'array'],
            'bio' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'profile_photo' => ['nullable', 'image', 'max:5120'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'document_categories' => ['nullable', 'array'],
            'document_categories.*' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function mapEmployeeData(array $validated): array
    {
        return [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
            'phone' => $validated['phone'],
            'personal_email' => $validated['personal_email'],
            'work_email' => $validated['work_email'] ?? null,
            'address' => $validated['address'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'designation_id' => $validated['designation_id'] ?? null,
            'employment_type' => $validated['employment_type'],
            'employment_status' => $validated['employment_status'] ?? 'Active',
            'join_date' => $validated['join_date'],
            'reporting_manager_id' => $validated['reporting_manager_id'] ?? null,
            'work_location' => $validated['work_location'] ?? null,
            'shift_id' => $validated['shift_id'] ?? null,
            'basic_salary' => $validated['basic_salary'] ?? null,
            'pay_frequency' => $validated['pay_frequency'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'account_name' => $validated['account_name'] ?? null,
            'tin' => $validated['tin'] ?? null,
            'ssnit' => $validated['ssnit'] ?? null,
            'allowances' => $validated['allowances'] ?? [],
            'skills' => $validated['skills'] ?? [],
            'bio' => $validated['bio'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function syncDocuments(Request $request, Employee $employee): void
    {
        if (! $request->hasFile('documents')) {
            return;
        }

        $categories = $request->input('document_categories', []);

        foreach ($request->file('documents') as $index => $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('employees/documents', 'public');
            EmployeeDocument::create([
                'employee_id' => $employee->id,
                'category' => $categories[$index] ?? 'Other',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function nextEmployeeId(): string
    {
        $lastNumeric = (int) Employee::query()
            ->selectRaw("MAX(CAST(SUBSTRING(employee_id, 4) AS UNSIGNED)) as seq")
            ->value('seq');

        return 'EMP' . str_pad((string) ($lastNumeric + 1), 5, '0', STR_PAD_LEFT);
    }
}
