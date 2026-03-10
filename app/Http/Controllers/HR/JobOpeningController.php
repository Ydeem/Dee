<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Applicant;
use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class JobOpeningController extends Controller
{
    public function index(Request $request)
    {
        $query = JobOpening::with(['department', 'designation', 'postedBy'])
            ->withCount('applicants')
            ->when($request->search, fn ($q) =>
                $q->where(function ($sub) use ($request) {
                    $sub->where('title', 'like', '%' . $request->search . '%')
                        ->orWhere('location', 'like', '%' . $request->search . '%');
                })
            )
            ->when($request->department, fn ($q) =>
                $q->whereHas('department', fn ($sub) => $sub->where('name', $request->department))
            )
            ->when($request->type, fn ($q) => $q->where('employment_type', $request->type))
            ->when($request->status, fn ($q) => $q->where('status', $request->status));

        $sortBy = $request->sort_by ?? 'created_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['title', 'created_at', 'deadline', 'vacancies', 'applicants_count', 'status'], true)) {
            $query->orderBy($sortBy, $sortDir);
        }

        $jobs = $query->paginate((int) ($request->per_page ?? 10));

        $summary = [
            'total_open' => JobOpening::where('status', 'Open')->count(),
            'total_draft' => JobOpening::where('status', 'Draft')->count(),
            'total_closed' => JobOpening::where('status', 'Closed')->count(),
            'total_applicants' => Schema::hasTable('applicants') ? Applicant::count() : 0,
        ];

        return response()->json([
            'jobs' => $jobs,
            'summary' => $summary,
            'departments' => Department::pluck('name'),
            'department_options' => Department::orderBy('name')->get(['id', 'name']),
            'designation_options' => Designation::orderBy('name')->get(['id', 'name', 'department_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'employment_type' => 'required|in:Full-time,Part-time,Contract,Intern,Remote',
            'vacancies' => 'required|integer|min:1',
            'status' => 'required|in:Draft,Open,Closed,On Hold',
            'deadline' => 'nullable|date',
            'salary_from' => 'nullable|numeric|min:0',
            'salary_to' => 'nullable|numeric|gte:salary_from',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'location' => 'nullable|string',
            'salary_currency' => 'nullable|string|max:10',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'benefits' => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'education_level' => 'nullable|string|max:100',
        ]);

        $employeeId = auth()->id();
        if ($employeeId && !Employee::whereKey($employeeId)->exists()) {
            $employeeId = null;
        }

        $job = JobOpening::create([
            ...$validated,
            'salary_currency' => $validated['salary_currency'] ?? 'GHS',
            'posted_by' => $employeeId,
        ]);

        return response()->json(['job' => $job->load(['department', 'designation', 'postedBy'])], 201);
    }

    public function show(int $id)
    {
        $job = JobOpening::with([
            'department',
            'designation',
            'postedBy',
            'applicants' => fn ($q) => $q->latest()->limit(5),
        ])->withCount('applicants')->findOrFail($id);

        return response()->json(['job' => $job]);
    }

    public function update(Request $request, int $id)
    {
        $job = JobOpening::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string',
            'employment_type' => 'required|in:Full-time,Part-time,Contract,Intern,Remote',
            'vacancies' => 'required|integer|min:1',
            'status' => 'required|in:Draft,Open,Closed,On Hold',
            'deadline' => 'nullable|date',
            'salary_from' => 'nullable|numeric|min:0',
            'salary_to' => 'nullable|numeric|gte:salary_from',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'location' => 'nullable|string',
            'salary_currency' => 'nullable|string|max:10',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'benefits' => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'education_level' => 'nullable|string|max:100',
        ]);

        $job->update($validated);
        return response()->json(['job' => $job->load(['department', 'designation', 'postedBy'])]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $job = JobOpening::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:Draft,Open,Closed,On Hold',
        ]);

        $job->update(['status' => $validated['status']]);
        return response()->json(['message' => 'Status updated.']);
    }

    public function destroy(int $id)
    {
        $job = JobOpening::withCount('applicants')->findOrFail($id);
        if ($job->applicants_count > 0) {
            return response()->json(['message' => 'Cannot delete a job with existing applicants.'], 422);
        }

        $job->delete();
        return response()->json(['message' => 'Job opening deleted.']);
    }
}

