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
use Illuminate\Support\Facades\Storage;

class ApplicantController extends Controller
{
    public function index(Request $request)
    {
        $query = Applicant::with([
            'jobOpening:id,title,department_id',
            'jobOpening.department:id,name',
        ])
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->job_opening_id, fn ($q) => $q->where('job_opening_id', $request->job_opening_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->source, fn ($q) => $q->where('source', $request->source))
            ->when($request->rating, fn ($q) => $q->where('rating', $request->rating))
            ->when($request->stage, fn ($q) => $q->where('stage', $request->stage));

        $applicants = $query
            ->orderByDesc('created_at')
            ->paginate((int) ($request->per_page ?? 10));

        $applicants->through(function (Applicant $applicant) {
            return [
                'id' => $applicant->id,
                'full_name' => $applicant->full_name,
                'first_name' => $applicant->first_name,
                'last_name' => $applicant->last_name,
                'email' => $applicant->email,
                'phone' => $applicant->phone,
                'initials' => $applicant->initials,
                'source' => $applicant->source,
                'experience_years' => $applicant->experience_years
                    ? $applicant->experience_years . ' yrs'
                    : '-',
                'current_company' => $applicant->current_company,
                'current_position' => $applicant->current_position,
                'expected_salary' => $applicant->expected_salary,
                'stage' => $applicant->stage,
                'stage_label' => $applicant->stage_label,
                'status' => $applicant->status,
                'status_color' => $applicant->status_color,
                'rating' => $applicant->rating,
                'notes' => $applicant->notes,
                'resume_url' => $applicant->resume_url,
                'is_converted' => ! is_null($applicant->converted_employee_id),
                'applied_date' => Carbon::parse($applicant->created_at)->format('M d, Y'),
                'job_opening' => $applicant->jobOpening
                    ? [
                        'id' => $applicant->jobOpening->id,
                        'title' => $applicant->jobOpening->title,
                        'department' => $applicant->jobOpening->department?->name ?? '-',
                    ]
                    : null,
            ];
        });

        return response()->json([
            'applicants' => $applicants,
            'stats' => [
                'total' => Applicant::count(),
                'new' => Applicant::where('status', 'New')->count(),
                'shortlisted' => Applicant::where('status', 'Shortlisted')->count(),
                'interviewed' => Applicant::whereIn('status', ['Interviewed', 'Interview Scheduled'])->count(),
                'hired' => Applicant::where('status', 'Hired')->count(),
            ],
            'job_openings' => JobOpening::orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function pipeline(Request $request)
    {
        $pipeline = [];

        foreach (Applicant::$stages as $stageNum => $stageLabel) {
            $applicants = Applicant::with('jobOpening:id,title')
                ->where('stage', $stageNum)
                ->when($request->job_opening_id, fn ($q) => $q->where('job_opening_id', $request->job_opening_id))
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Applicant $applicant) => [
                    'id' => $applicant->id,
                    'full_name' => $applicant->full_name,
                    'initials' => $applicant->initials,
                    'email' => $applicant->email,
                    'source' => $applicant->source,
                    'rating' => $applicant->rating,
                    'status' => $applicant->status,
                    'status_color' => $applicant->status_color,
                    'experience' => $applicant->experience_years ? $applicant->experience_years . ' yrs' : '-',
                    'applied_date' => Carbon::parse($applicant->created_at)->format('M d, Y'),
                    'job_opening' => $applicant->jobOpening?->title ?? '-',
                    'stage' => $applicant->stage,
                    'is_converted' => ! is_null($applicant->converted_employee_id),
                ])
                ->values();

            $pipeline[] = [
                'stage' => $stageNum,
                'label' => $stageLabel,
                'count' => $applicants->count(),
                'applicants' => $applicants,
                'color' => match ($stageNum) {
                    1 => 'blue',
                    2 => 'orange',
                    3 => 'purple',
                    4 => 'teal',
                    5 => 'green',
                    default => 'grey',
                },
            ];
        }

        return response()->json(['pipeline' => $pipeline]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'job_opening_id' => 'nullable|exists:job_openings,id',
            'source' => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'current_company' => 'nullable|string',
            'current_position' => 'nullable|string',
            'expected_salary' => 'nullable|numeric',
            'cover_letter' => 'nullable|string',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $resumePath = null;
        if ($request->hasFile('resume')) {
            $resumePath = $request->file('resume')->store('hr/resumes', 'public');
        }

        $applicant = Applicant::create([
            ...$request->except('resume'),
            'resume' => $resumePath,
            'stage' => 1,
            'status' => 'New',
        ]);

        return response()->json([
            'applicant' => $applicant->load('jobOpening'),
            'message' => 'Applicant added successfully.',
        ], 201);
    }

    public function show(int $id)
    {
        $applicant = Applicant::with([
            'jobOpening.department',
            'convertedEmployee',
        ])->findOrFail($id);

        return response()->json([
            'applicant' => $applicant,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $applicant = Applicant::findOrFail($id);

        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'stage' => 'integer|between:1,5',
            'status' => 'string',
            'rating' => 'nullable|integer|between:1,5',
        ]);

        if ($request->hasFile('resume')) {
            if ($applicant->resume) {
                Storage::disk('public')->delete($applicant->resume);
            }
            $applicant->resume = $request->file('resume')->store('hr/resumes', 'public');
        }

        $applicant->update($request->except('resume'));

        return response()->json([
            'applicant' => $applicant->fresh()->load('jobOpening'),
            'message' => 'Applicant updated.',
        ]);
    }

    public function moveStage(Request $request, int $id)
    {
        $applicant = Applicant::findOrFail($id);

        $request->validate([
            'stage' => 'required|integer|between:1,5',
        ]);

        $stageStatusMap = [
            1 => 'New',
            2 => 'Reviewing',
            3 => 'Interview Scheduled',
            4 => 'Offer Sent',
            5 => 'Hired',
        ];

        $updates = [
            'stage' => $request->stage,
            'status' => $stageStatusMap[$request->stage] ?? $applicant->status,
        ];

        if ((int) $request->stage === 3) {
            $updates['interviewed_at'] = now();
        }
        if ((int) $request->stage === 5) {
            $updates['hired_at'] = now();
            $updates['status'] = 'Hired';
        }

        $applicant->update($updates);

        return response()->json([
            'message' => 'Moved to ' . Applicant::$stages[$request->stage],
            'applicant' => $applicant->fresh(),
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $applicant = Applicant::findOrFail($id);

        $request->validate([
            'status' => 'required|in:New,Reviewing,Shortlisted,Interview Scheduled,Interviewed,Offer Sent,Hired,Rejected,Withdrawn',
            'reason' => 'nullable|string',
        ]);

        $updates = ['status' => $request->status];

        if ($request->status === 'Rejected') {
            $updates['rejected_reason'] = $request->reason;
        }

        $applicant->update($updates);

        return response()->json([
            'message' => 'Status updated to ' . $request->status,
        ]);
    }

    public function updateRating(Request $request, int $id)
    {
        $applicant = Applicant::findOrFail($id);
        $request->validate([
            'rating' => 'required|integer|between:1,5',
        ]);
        $applicant->update(['rating' => $request->rating]);

        return response()->json(['message' => 'Rating updated.']);
    }

    public function addNote(Request $request, int $id)
    {
        $applicant = Applicant::findOrFail($id);
        $request->validate([
            'notes' => 'required|string|max:1000',
        ]);
        $applicant->update(['notes' => $request->notes]);

        return response()->json(['message' => 'Note saved.']);
    }

    public function convertToEmployee(int $id)
    {
        $applicant = Applicant::with('jobOpening')->findOrFail($id);

        if ($applicant->converted_employee_id) {
            return response()->json([
                'message' => 'Already converted to employee.',
            ], 422);
        }

        $employee = Employee::create([
            'first_name' => $applicant->first_name,
            'last_name' => $applicant->last_name,
            'personal_email' => $applicant->email,
            'phone' => $applicant->phone,
            'department_id' => $applicant->jobOpening?->department_id,
            'designation_id' => $applicant->jobOpening?->designation_id,
            'employment_type' => $applicant->jobOpening?->employment_type ?? 'Full-time',
            'employment_status' => 'Probation',
            'join_date' => now()->toDateString(),
            'basic_salary' => $applicant->expected_salary,
        ]);

        $applicant->update([
            'status' => 'Hired',
            'stage' => 5,
            'hired_at' => now(),
            'converted_employee_id' => $employee->id,
        ]);

        if ($applicant->jobOpening) {
            $hiredCount = Applicant::where('job_opening_id', $applicant->job_opening_id)
                ->where('status', 'Hired')
                ->count();

            if ($hiredCount >= $applicant->jobOpening->vacancies) {
                $applicant->jobOpening->update(['status' => 'Closed']);
            }
        }

        return response()->json([
            'employee' => $employee,
            'message' => $applicant->full_name . ' has been converted to an employee (ID: ' . $employee->employee_id . ').',
        ], 201);
    }

    public function destroy(int $id)
    {
        $applicant = Applicant::findOrFail($id);

        if ($applicant->resume) {
            Storage::disk('public')->delete($applicant->resume);
        }

        $applicant->delete();

        return response()->json(['message' => 'Applicant deleted.']);
    }
}
