<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Applicant;
use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\JobOpening;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JobOpeningController extends Controller
{
    public function index(Request $request)
    {
        $query = JobOpening::with([
            'department:id,name',
            'designation:id,name',
        ])
            ->withCount('applicants')
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->department, fn ($q) => $q->whereHas('department', fn ($departmentQuery) => $departmentQuery->where('name', $request->department)))
            ->when($request->type, fn ($q) => $q->where('employment_type', $request->type))
            ->when($request->status, fn ($q) => $q->where('status', $request->status));

        $sortBy = in_array($request->sort_by, [
            'title', 'deadline',
            'created_at', 'applicants_count',
        ], true) ? $request->sort_by : 'created_at';

        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';

        $jobs = $query
            ->orderBy($sortBy, $sortDir)
            ->paginate((int) ($request->per_page ?? 10));

        $jobs->through(function (JobOpening $job) {
            return [
                'id' => $job->id,
                'title' => $job->title,
                'employment_type' => $job->employment_type,
                'vacancies' => $job->vacancies,
                'salary_range' => $job->salary_range,
                'min_salary' => $job->min_salary,
                'max_salary' => $job->max_salary,
                'location' => $job->location,
                'description' => $job->description,
                'requirements' => $job->requirements,
                'responsibilities' => $job->responsibilities,
                'benefits' => $job->benefits,
                'status' => $job->status,
                'status_color' => $job->status_color,
                'is_expired' => $job->is_expired,
                'days_until_deadline' => $job->days_until_deadline,
                'deadline' => $job->deadline
                    ? Carbon::parse($job->deadline)->format('M d, Y')
                    : null,
                'deadline_raw' => $job->deadline
                    ? $job->deadline->format('Y-m-d')
                    : null,
                'created_at' => Carbon::parse($job->created_at)->format('M d, Y'),
                'applicants_count' => $job->applicants_count,
                'department' => $job->department
                    ? ['id' => $job->department->id, 'name' => $job->department->name]
                    : null,
                'designation' => $job->designation
                    ? ['id' => $job->designation->id, 'name' => $job->designation->name]
                    : null,
            ];
        });

        return response()->json([
            'jobs' => $jobs,
            'stats' => [
                'open' => JobOpening::where('status', 'Open')->count(),
                'draft' => JobOpening::where('status', 'Draft')->count(),
                'closed' => JobOpening::where('status', 'Closed')->count(),
                'total_applicants' => Applicant::count(),
            ],
            'filters' => [
                'departments' => Department::active()->orderBy('name')->pluck('name'),
                'department_options' => Department::active()->orderBy('name')->get(['id', 'name']),
                'designations' => Designation::active()->orderBy('name')->get(['id', 'name', 'department_id']),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateJob($request);

        $job = JobOpening::create([
            ...$validated,
            'posted_by' => $this->resolvePostedByEmployeeId(),
        ]);

        return response()->json([
            'job' => $job->load(['department', 'designation']),
            'message' => 'Job posting created.',
        ], 201);
    }

    public function show(int $id)
    {
        $job = JobOpening::with([
            'department',
            'designation',
            'applicants' => fn ($q) => $q->latest()->limit(10),
        ])
            ->withCount('applicants')
            ->findOrFail($id);

        return response()->json(['job' => $job]);
    }

    public function update(Request $request, int $id)
    {
        $job = JobOpening::findOrFail($id);
        $job->update($this->validateJob($request));

        return response()->json([
            'job' => $job->fresh()->load(['department', 'designation']),
            'message' => 'Job posting updated.',
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $job = JobOpening::findOrFail($id);

        $request->validate([
            'status' => 'required|in:Draft,Open,Closed,On Hold',
        ]);

        $job->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status changed to ' . $request->status,
        ]);
    }

    public function duplicate(int $id)
    {
        $job = JobOpening::findOrFail($id);

        $newJob = $job->replicate();
        $newJob->title = 'Copy of ' . $job->title;
        $newJob->status = 'Draft';
        $newJob->posted_by = $this->resolvePostedByEmployeeId();
        $newJob->save();

        return response()->json([
            'job' => $newJob->load(['department', 'designation']),
            'message' => 'Job duplicated as Draft.',
        ], 201);
    }

    public function destroy(int $id)
    {
        $job = JobOpening::withCount('applicants')->findOrFail($id);

        if ($job->applicants_count > 0) {
            return response()->json([
                'message' => 'Cannot delete job with ' . $job->applicants_count . ' applicants. Close it instead.',
            ], 422);
        }

        $job->delete();

        return response()->json([
            'message' => 'Job posting deleted.',
        ]);
    }

    private function validateJob(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:200',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'employment_type' => 'required|in:Full-time,Part-time,Contract,Intern',
            'vacancies' => 'required|integer|min:1',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
            'location' => 'nullable|string',
            'deadline' => 'nullable|date',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'benefits' => 'nullable|string',
            'status' => 'required|in:Draft,Open,Closed,On Hold',
        ]);
    }

    private function resolvePostedByEmployeeId(): ?int
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return Employee::query()
            ->where('work_email', $user->email)
            ->orWhere('personal_email', $user->email)
            ->value('id');
    }
}
