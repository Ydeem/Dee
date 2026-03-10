<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\SalaryStructure;
use Illuminate\Http\Request;

class SalaryStructureController extends Controller
{
    public function index(Request $request)
    {
        $structures = SalaryStructure::with('employee.department')
            ->when($request->search, fn ($q) =>
                $q->whereHas('employee', fn ($sub) =>
                    $sub->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                        ->orWhere('employee_id', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->department, fn ($q) =>
                $q->whereHas('employee.department', fn ($sub) => $sub->where('name', $request->department))
            )
            ->orderBy('created_at', 'desc')
            ->paginate((int) ($request->per_page ?? 15));

        return response()->json(['structures' => $structures]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'basic_salary' => 'required|numeric|min:0',
            'effective_date' => 'required|date',
            'pay_frequency' => 'required|in:Monthly,Bi-weekly,Weekly',
            'allowances' => 'nullable|array',
            'currency' => 'nullable|string|max:10',
        ]);

        $structure = SalaryStructure::updateOrCreate(
            ['employee_id' => $validated['employee_id']],
            [
                'basic_salary' => $validated['basic_salary'],
                'allowances' => $validated['allowances'] ?? [],
                'effective_date' => $validated['effective_date'],
                'currency' => $validated['currency'] ?? 'GHS',
                'pay_frequency' => $validated['pay_frequency'],
            ]
        );

        return response()->json(['structure' => $structure->load('employee.department')], 201);
    }

    public function update(Request $request, int $id)
    {
        $structure = SalaryStructure::findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'basic_salary' => 'nullable|numeric|min:0',
            'effective_date' => 'nullable|date',
            'pay_frequency' => 'nullable|in:Monthly,Bi-weekly,Weekly',
            'allowances' => 'nullable|array',
            'currency' => 'nullable|string|max:10',
        ]);

        $structure->update($validated);
        return response()->json(['structure' => $structure->fresh('employee.department')]);
    }
}

