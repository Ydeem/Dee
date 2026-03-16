<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Expense;
use App\Models\User;
use App\Notifications\HR\LeaveRequestNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    private function transform(Expense $exp): array
    {
        $employee = $exp->employee;

        return [
            'id' => $exp->id,
            'title' => $exp->title,
            'category' => $exp->category,
            'amount' => number_format((float) $exp->amount, 2),
            'amount_raw' => (float) $exp->amount,
            'currency' => $exp->currency,
            'expense_date' => Carbon::parse($exp->expense_date)->format('M d, Y'),
            'expense_date_raw' => $exp->expense_date?->format('Y-m-d'),
            'description' => $exp->description,
            'receipt_url' => $exp->receipt_url,
            'has_receipt' => !is_null($exp->receipt),
            'status' => $exp->status,
            'status_color' => $exp->status_color,
            'can_approve' => $exp->can_approve,
            'can_reject' => $exp->can_reject,
            'can_pay' => $exp->can_pay,
            'rejection_reason' => $exp->rejection_reason,
            'approved_at' => $exp->approved_at
                ? Carbon::parse($exp->approved_at)->format('M d, Y')
                : null,
            'paid_at' => $exp->paid_at
                ? Carbon::parse($exp->paid_at)->format('M d, Y')
                : null,
            'created_at' => Carbon::parse($exp->created_at)->format('M d, Y'),
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                'full_name' => $employee->full_name,
                'emp_id' => $employee->employee_id,
                'avatar' => $employee->avatar_url,
                'avatar_url' => $employee->avatar_url,
                'initials' => strtoupper(substr((string) $employee->first_name, 0, 1) . substr((string) $employee->last_name, 0, 1)),
                'dept' => $employee->department?->name ?? '-',
                'department' => [
                    'name' => $employee->department?->name ?? '-',
                ],
            ] : null,
        ];
    }

    public function index(Request $request)
    {
        $query = Expense::with([
            'employee:id,first_name,last_name,employee_id,avatar,department_id',
            'employee.department:id,name',
        ])
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->department, fn ($q) =>
                $q->whereHas('employee.department', fn ($departmentQuery) =>
                    $departmentQuery->where('name', $request->department)
                )
            )
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->month, fn ($q) => $q->whereMonth('expense_date', $request->month))
            ->when($request->year, fn ($q) => $q->whereYear('expense_date', $request->year))
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id));

        $expenses = $query
            ->orderBy('created_at', 'desc')
            ->paginate((int) ($request->per_page ?? 15));

        $expenses->through(fn ($expense) => $this->transform($expense));

        return response()->json([
            'expenses' => $expenses,
            'stats' => $this->getStats(),
            'filters' => [
                'departments' => Department::active()->orderBy('name')->pluck('name'),
                'categories' => [
                    'Travel',
                    'Meals',
                    'Accommodation',
                    'Equipment',
                    'Training',
                    'Medical',
                    'Communication',
                    'Other',
                ],
            ],
            // Backward-compatible keys used by legacy front-end logic.
            'summary' => [
                'total_submitted' => Expense::where('status', 'Pending')->count(),
                'total_approved' => (float) Expense::where('status', 'Approved')->sum('amount'),
                'total_pending' => (float) Expense::where('status', 'Pending')->sum('amount'),
                'total_paid' => (float) Expense::where('status', 'Paid')->sum('amount'),
            ],
            'departments' => Department::active()->orderBy('name')->pluck('name'),
            'categories' => [
                'Travel',
                'Meals',
                'Accommodation',
                'Equipment',
                'Training',
                'Medical',
                'Communication',
                'Other',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'title' => 'required|string|max:200',
            'category' => 'required|in:Travel,Meals,Accommodation,Equipment,Training,Medical,Communication,Other',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('hr/receipts', 'public');
        }

        $expense = Expense::create([
            ...$request->except('receipt'),
            'receipt' => $receiptPath,
            'currency' => 'GHS',
            'status' => 'Pending',
        ]);

        return response()->json([
            'expense' => $this->transform($expense->load('employee.department')),
            'message' => 'Expense submitted.',
        ], 201);
    }

    public function approve($id)
    {
        $expense = Expense::with('employee')->findOrFail($id);

        if ($expense->status !== 'Pending') {
            return response()->json([
                'message' => 'Only pending expenses can be approved.',
            ], 422);
        }

        $expense->update([
            'status' => 'Approved',
            'approved_by' => $this->resolveActorEmployeeId(),
            'approved_at' => now(),
        ]);

        $recipient = $this->resolveUserForEmployee($expense->employee);
        if ($recipient && $recipient->settings->notify_expense_approved) {
            $recipient->notify(new LeaveRequestNotification(
                message: 'Your expense claim was approved',
                type: 'expense_approved',
                link: '/hr/expenses',
                icon: 'mdi-check-circle',
                color: 'success',
            ));
        }

        return response()->json([
            'message' => 'Expense approved for ' . ($expense->employee?->first_name ?? 'employee') . '.',
        ]);
    }

    public function reject(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);

        if (!in_array($expense->status, ['Pending', 'Approved'], true)) {
            return response()->json([
                'message' => 'Cannot reject this expense.',
            ], 422);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $expense->update([
            'status' => 'Rejected',
            'rejected_by' => $this->resolveActorEmployeeId(),
            'rejection_reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Expense rejected.',
        ]);
    }

    public function markPaid($id)
    {
        $expense = Expense::findOrFail($id);

        if ($expense->status !== 'Approved') {
            return response()->json([
                'message' => 'Only approved expenses can be marked as paid.',
            ], 422);
        }

        $expense->update([
            'status' => 'Paid',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Expense marked as paid.',
        ]);
    }

    public function destroy($id)
    {
        $expense = Expense::findOrFail($id);

        if (in_array($expense->status, ['Approved', 'Paid'], true)) {
            return response()->json([
                'message' => 'Cannot delete an approved or paid expense.',
            ], 422);
        }

        if ($expense->receipt) {
            Storage::disk('public')->delete($expense->receipt);
        }

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted.',
        ]);
    }

    private function getStats(): array
    {
        $month = now()->month;
        $year = now()->year;

        return [
            'awaiting_review' => Expense::where('status', 'Pending')->count(),
            'total_approved' => 'GHS ' . number_format((float) Expense::where('status', 'Approved')->sum('amount'), 2),
            'total_pending' => 'GHS ' . number_format((float) Expense::where('status', 'Pending')->sum('amount'), 2),
            'paid_this_month' => 'GHS ' . number_format((float) Expense::where('status', 'Paid')
                ->whereMonth('paid_at', $month)
                ->whereYear('paid_at', $year)
                ->sum('amount'), 2),
        ];
    }

    private function resolveActorEmployeeId(): ?int
    {
        $userId = auth()->id();
        if (!$userId) {
            return null;
        }

        return Employee::whereKey($userId)->exists() ? (int) $userId : null;
    }

    private function resolveUserForEmployee(?Employee $employee): ?User
    {
        if (! $employee) {
            return null;
        }

        $emails = collect([$employee->work_email, $employee->personal_email])
            ->filter()
            ->values()
            ->all();

        if ($emails === []) {
            return null;
        }

        return User::query()
            ->whereIn('email', $emails)
            ->first();
    }
}
