<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Applicant;
use App\Models\HR\Employee;
use App\Models\HR\Interview;
use App\Models\HR\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplicantController extends Controller
{
    public function index(Request $request)
    {
        $query = Applicant::with(['jobOpening.department', 'reviewedBy'])
            ->when($request->search, fn ($q) =>
                $q->where(function ($sub) use ($request) {
                    $sub->where('first_name', 'like', '%' . $request->search . '%')
                        ->orWhere('last_name', 'like', '%' . $request->search . '%')
                        ->orWhere('email', 'like', '%' . $request->search . '%');
                })
            )
            ->when($request->job_opening_id, fn ($q) => $q->where('job_opening_id', $request->job_opening_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->stage, fn ($q) => $q->where('stage', $request->stage))
            ->when($request->source, fn ($q) => $q->where('source', $request->source))
            ->when($request->rating, fn ($q) => $q->where('rating', $request->rating));

        $sortBy = $request->sort_by ?? 'created_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['created_at', 'first_name', 'stage', 'status', 'rating'], true)) {
            $query->orderBy($sortBy, $sortDir);
        }

        $applicants = $query->paginate((int) ($request->per_page ?? 10));

        $summary = [
            'total' => Applicant::count(),
            'new' => Applicant::where('status', 'New')->count(),
            'shortlisted' => Applicant::where('status', 'Shortlisted')->count(),
            'interviewed' => Applicant::where('status', 'Interviewed')->count(),
            'hired' => Applicant::where('status', 'Hired')->count(),
        ];

        return response()->json([
            'applicants' => $applicants,
            'summary' => $summary,
            'job_openings' => JobOpening::where('status', 'Open')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'job_opening_id' => 'required|exists:job_openings,id',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'location' => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'education_level' => 'nullable|string',
            'current_employer' => 'nullable|string',
            'current_role' => 'nullable|string',
            'expected_salary' => 'nullable|numeric|min:0',
            'notice_period' => 'nullable|string',
            'cover_letter' => 'nullable|string',
            'source' => 'nullable|string',
            'status' => 'required|in:New,Reviewing,Shortlisted,Interview Scheduled,Interviewed,Offer Sent,Hired,Rejected,Withdrawn',
            'rating' => 'nullable|integer|min:1|max:5',
            'stage' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $resumePath = null;
        if ($request->hasFile('resume')) {
            $resumePath = $request->file('resume')->store('hr/resumes', 'public');
        }

        $reviewerId = auth()->id();
        if ($reviewerId && !Employee::whereKey($reviewerId)->exists()) {
            $reviewerId = null;
        }

        $applicant = Applicant::create([
            ...$validated,
            'stage' => $validated['stage'] ?? $this->defaultStageForStatus($validated['status']),
            'resume_path' => $resumePath,
            'reviewed_by' => $reviewerId,
        ]);

        return response()->json(['applicant' => $applicant->load(['jobOpening.department', 'reviewedBy'])], 201);
    }

    public function show(int $id)
    {
        $applicant = Applicant::with(['jobOpening.department', 'reviewedBy', 'interviews.interviewer'])->findOrFail($id);
        return response()->json(['applicant' => $applicant]);
    }

    public function update(Request $request, int $id)
    {
        $applicant = Applicant::findOrFail($id);

        $validated = $request->validate([
            'job_opening_id' => 'required|exists:job_openings,id',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'location' => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'education_level' => 'nullable|string',
            'current_employer' => 'nullable|string',
            'current_role' => 'nullable|string',
            'expected_salary' => 'nullable|numeric|min:0',
            'notice_period' => 'nullable|string',
            'cover_letter' => 'nullable|string',
            'source' => 'nullable|string',
            'status' => 'required|in:New,Reviewing,Shortlisted,Interview Scheduled,Interviewed,Offer Sent,Hired,Rejected,Withdrawn',
            'stage' => 'nullable|integer|min:1|max:5',
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($request->hasFile('resume')) {
            if ($applicant->resume_path) {
                Storage::disk('public')->delete($applicant->resume_path);
            }
            $validated['resume_path'] = $request->file('resume')->store('hr/resumes', 'public');
        }

        $validated['stage'] = $validated['stage'] ?? $this->defaultStageForStatus($validated['status']);
        $applicant->update($validated);

        return response()->json(['applicant' => $applicant->load(['jobOpening.department', 'reviewedBy'])]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $applicant = Applicant::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:New,Reviewing,Shortlisted,Interview Scheduled,Interviewed,Offer Sent,Hired,Rejected,Withdrawn',
            'stage' => 'nullable|integer|min:1|max:5',
            'rejection_reason' => 'required_if:status,Rejected|nullable|string',
            'offer_date' => 'required_if:status,Offer Sent|nullable|date',
            'interview_date' => 'nullable|date',
            'interviewer_id' => 'nullable|exists:employees,id',
            'interview_type' => 'nullable|string',
            'location_or_link' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $applicant->update([
            'status' => $validated['status'],
            'stage' => $validated['stage'] ?? $this->defaultStageForStatus($validated['status']),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'offer_date' => $validated['offer_date'] ?? null,
            'interview_date' => $validated['interview_date'] ?? $applicant->interview_date,
        ]);

        if (($validated['status'] ?? null) === 'Interview Scheduled' && !empty($validated['interview_date'])) {
            Interview::create([
                'applicant_id' => $applicant->id,
                'interviewer_id' => $validated['interviewer_id'] ?? null,
                'scheduled_at' => $validated['interview_date'],
                'type' => $validated['interview_type'] ?? null,
                'location_or_link' => $validated['location_or_link'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Status updated.',
            'convert_prompt' => $validated['status'] === 'Hired',
        ]);
    }

    public function updateRating(Request $request, int $id)
    {
        $validated = $request->validate(['rating' => 'required|integer|min:1|max:5']);
        $applicant = Applicant::findOrFail($id);
        $applicant->update(['rating' => $validated['rating']]);
        return response()->json(['message' => 'Rating updated.']);
    }

    public function addNote(Request $request, int $id)
    {
        $validated = $request->validate(['notes' => 'required|string']);
        $applicant = Applicant::findOrFail($id);
        $applicant->update(['notes' => $validated['notes']]);
        return response()->json(['message' => 'Note saved.']);
    }

    public function convertToEmployee(Request $request, int $id)
    {
        $applicant = Applicant::with('jobOpening')->findOrFail($id);
        if ($applicant->status !== 'Hired') {
            return response()->json(['message' => 'Only hired applicants can be converted.'], 422);
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'employment_type' => 'nullable|string',
        ]);

        $employeeId = 'EMP' . str_pad((string) (Employee::count() + 1), 4, '0', STR_PAD_LEFT);
        $newId = DB::table('employees')->insertGetId([
            'first_name' => $applicant->first_name,
            'last_name' => $applicant->last_name,
            'employee_id' => $employeeId,
            'personal_email' => $applicant->email,
            'phone' => $applicant->phone,
            'employment_status' => 'Active',
            'employment_type' => $validated['employment_type'] ?? ($applicant->jobOpening?->employment_type ?? 'Full-time'),
            'department_id' => $validated['department_id'] ?? $applicant->jobOpening?->department_id,
            'designation_id' => $validated['designation_id'] ?? $applicant->jobOpening?->designation_id,
            'join_date' => $validated['start_date'] ?? now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Applicant converted to employee.',
            'employee_id' => $newId,
        ]);
    }

    public function destroy(int $id)
    {
        $applicant = Applicant::findOrFail($id);
        if ($applicant->resume_path) {
            Storage::disk('public')->delete($applicant->resume_path);
        }
        $applicant->delete();
        return response()->json(['message' => 'Applicant deleted.']);
    }

    private function defaultStageForStatus(string $status): int
    {
        return match ($status) {
            'New', 'Reviewing' => 1,
            'Shortlisted' => 2,
            'Interview Scheduled', 'Interviewed' => 3,
            'Offer Sent' => 4,
            'Hired', 'Rejected', 'Withdrawn' => 5,
            default => 1,
        };
    }
}

