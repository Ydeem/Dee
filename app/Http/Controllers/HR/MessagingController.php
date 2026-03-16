<?php

namespace App\Http\Controllers\HR;

use App\Mail\HR\HrMail;
use App\Http\Controllers\Controller;
use App\Models\HR\Applicant;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\HrAnnouncement;
use App\Models\HR\HrMessage;
use App\Models\HR\HrMessageTemplate;
use App\Models\User;
use App\Notifications\HR\LeaveRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MessagingController extends Controller
{
    public function inbox(Request $request)
    {
        $user = $request->user();
        $employee = $this->resolveEmployee($user);
        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $query = HrMessage::with('sender:id,name,email')
            ->where(function ($q) use ($user, $employee) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('recipient_type', User::class)
                        ->where('recipient_id', $user->id);
                });

                if ($employee) {
                    $q->orWhere(function ($inner) use ($employee) {
                        $inner->where('recipient_type', Employee::class)
                            ->where('recipient_id', $employee->id);
                    });
                }
            })
            ->latest();

        $total = (clone $query)->count();
        $unreadCount = (clone $query)->whereNull('read_at')->count();

        $messages = $query->paginate($perPage)->through(function (HrMessage $message) {
            return [
                'id' => $message->id,
                'thread_id' => $message->thread_id,
                'subject' => $message->subject ?? '(no subject)',
                'preview' => Str::limit($message->body, 100),
                'body' => $message->body,
                'type' => $message->type,
                'status' => $message->status,
                'read' => $message->isRead(),
                'sender' => [
                    'id' => $message->sender?->id,
                    'name' => $message->sender?->name ?? 'Unknown',
                    'initials' => $this->initials($message->sender?->name),
                ],
                'recipient_type' => class_basename($message->recipient_type),
                'recipient_id' => $message->recipient_id,
                'time' => $message->created_at->diffForHumans(),
                'created_at' => $message->created_at->format('M d, Y H:i'),
            ];
        });

        return response()->json([
            'messages' => $messages,
            'total' => $total,
            'unread_count' => $unreadCount,
        ]);
    }

    public function sent(Request $request)
    {
        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $messages = HrMessage::with('sender:id,name')
            ->where('sender_id', $request->user()->id)
            ->latest()
            ->paginate($perPage)
            ->through(function (HrMessage $message) {
                return [
                    'id' => $message->id,
                    'thread_id' => $message->thread_id,
                    'subject' => $message->subject ?? '(no subject)',
                    'preview' => Str::limit($message->body, 100),
                    'body' => $message->body,
                    'type' => $message->type,
                    'recipient_type' => class_basename($message->recipient_type),
                    'recipient_id' => $message->recipient_id,
                    'status' => $message->status,
                    'time' => $message->created_at->diffForHumans(),
                    'created_at' => $message->created_at->format('M d, Y H:i'),
                ];
            });

        return response()->json([
            'messages' => $messages,
        ]);
    }

        public function send(Request $request)
    {
        $validated = $request->validate([
            'recipient_type' => ['required', 'in:employee,applicant,user'],
            'recipient_id' => ['required', 'integer'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'in:internal,email,both'],
            'recipient_email' => ['nullable', 'email'],
            'thread_id' => ['nullable', 'string'],
        ]);

        $modelMap = [
            'employee' => Employee::class,
            'applicant' => Applicant::class,
            'user' => User::class,
        ];

        $recipientClass = $modelMap[$validated['recipient_type']];
        $recipient = $recipientClass::query()->findOrFail($validated['recipient_id']);

        $message = HrMessage::create([
            'thread_id' => $validated['thread_id'] ?? (string) Str::uuid(),
            'sender_id' => $request->user()->id,
            'recipient_type' => $recipientClass,
            'recipient_id' => (int) $validated['recipient_id'],
            'subject' => $validated['subject'] ?? 'No Subject',
            'body' => $validated['body'],
            'type' => $validated['type'],
            'status' => 'sent',
        ]);

        $delivered = false;

        if (in_array($validated['type'], ['internal', 'both'], true)) {
            $recipientUser = null;

            if ($recipient instanceof Employee) {
                $recipientUser = User::query()
                    ->where('email', $recipient->work_email)
                    ->orWhere('email', $recipient->personal_email)
                    ->first();
            }

            if ($recipient instanceof User) {
                $recipientUser = $recipient;
            }

            if ($recipientUser) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\HR\\NewMessage',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $recipientUser->id,
                    'data' => json_encode([
                        'message' => $request->user()->name . ' sent you a message: ' . Str::limit($validated['body'], 60),
                        'icon' => 'mdi-message',
                        'color' => 'primary',
                        'link' => '/hr/messages',
                        'title' => 'New Message from ' . $request->user()->name,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $delivered = true;
            }
        }

        if (in_array($validated['type'], ['email', 'both'], true)) {
            $email = $validated['recipient_email'] ?: $this->resolveRecipientEmail($recipient);

            if ($email) {
                Mail::to($email)->queue(
                    new HrMail(
                        emailSubject: $validated['subject'] ?? 'Message from HR',
                        emailBody: $validated['body'],
                        senderName: $request->user()->name,
                        recipientName: $this->resolveRecipientName($recipient),
                        companyName: (string) config('app.name'),
                    )
                );

                $delivered = true;
            }
        }

        if ($delivered) {
            $message->update(['status' => 'delivered']);
        }

        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => $message->fresh(),
            'hr_message' => $message->load('sender:id,name,email'),
        ]);
    }
    public function reply(Request $request, string $threadId)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:internal,email'],
        ]);

        $threadMessages = $this->threadScopeForUser($request, $threadId)
            ->orderBy('created_at')
            ->get();

        if ($threadMessages->isEmpty()) {
            abort(404);
        }

        $lastMessage = $threadMessages->last();
        $recipientType = null;
        $recipientId = null;

        if ($lastMessage->sender_id !== $request->user()->id) {
            $recipientType = User::class;
            $recipientId = $lastMessage->sender_id;
        } else {
            $recipientType = $lastMessage->recipient_type;
            $recipientId = $lastMessage->recipient_id;
        }

        $recipient = $recipientType::query()->findOrFail($recipientId);

        $message = HrMessage::create([
            'thread_id' => $threadId,
            'sender_id' => $request->user()->id,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'subject' => $validated['subject'] ?? $lastMessage->subject,
            'body' => $validated['body'],
            'type' => $validated['type'],
            'status' => 'sent',
        ]);

        if ($validated['type'] === 'email') {
            $email = $this->resolveRecipientEmail($recipient);
            if ($email) {
                Mail::to($email)->queue(
                    new HrMail(
                        emailSubject: $message->subject ?? 'Message from HR',
                        emailBody: $validated['body'],
                        senderName: $request->user()->name,
                        recipientName: $this->resolveRecipientName($recipient),
                        companyName: (string) config('app.name'),
                    )
                );
                $message->update(['status' => 'delivered']);
            }
        }

        return response()->json([
            'message' => 'Reply sent.',
            'hr_message' => $message->load('sender:id,name,email'),
        ]);
    }

    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'audience' => ['required', 'in:all,department,selected'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'send_email' => ['boolean'],
        ]);

        $employees = match ($validated['audience']) {
            'all' => Employee::query()
                ->where('employment_status', 'Active')
                ->get(),
            'department' => Employee::query()
                ->whereIn('department_id', $validated['department_ids'] ?? [])
                ->where('employment_status', 'Active')
                ->get(),
            default => Employee::query()
                ->whereIn('id', $validated['employee_ids'] ?? [])
                ->get(),
        };

        $threadId = (string) Str::uuid();
        $sent = 0;

        foreach ($employees as $employee) {
            HrMessage::create([
                'thread_id' => $threadId,
                'sender_id' => $request->user()->id,
                'recipient_type' => Employee::class,
                'recipient_id' => $employee->id,
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'type' => 'internal',
                'status' => 'sent',
            ]);

            if (($validated['send_email'] ?? false) === true) {
                $email = $employee->work_email ?: $employee->personal_email;
                if ($email) {
                    Mail::to($email)->queue(
                        new HrMail(
                            emailSubject: $validated['subject'],
                            emailBody: $validated['body'],
                            senderName: $request->user()->name,
                            recipientName: $employee->full_name,
                            companyName: (string) config('app.name'),
                        )
                    );
                }
            }

            $linkedUser = User::query()
                ->where('email', $employee->personal_email)
                ->orWhere('email', $employee->work_email)
                ->first();

            $linkedUser?->notify(new LeaveRequestNotification(
                message: 'New message: ' . $validated['subject'],
                type: 'message',
                link: '/hr/messages',
                icon: 'mdi-message-text',
                color: 'primary',
            ));

            $sent++;
        }

        return response()->json([
            'message' => "Sent to {$sent} employee(s).",
            'sent' => $sent,
        ]);
    }

    public function toApplicant(Request $request, int $id)
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'template_id' => ['nullable', 'integer', 'exists:hr_message_templates,id'],
        ]);

        $applicant = Applicant::query()
            ->with('jobOpening:id,title')
            ->findOrFail($id);

        $subject = $validated['subject'] ?? '';
        $body = $validated['body'] ?? '';

        if (! empty($validated['template_id'])) {
            $template = HrMessageTemplate::query()->find($validated['template_id']);
            if ($template) {
                $rendered = $template->render([
                    'name' => $applicant->full_name,
                    'position' => $applicant->jobOpening?->title ?? 'the position',
                    'company' => config('app.name'),
                    'hr_name' => $request->user()->name,
                ]);
                $subject = $rendered['subject'];
                $body = $rendered['body'];
            }
        }

        if (blank($subject) || blank($body)) {
            return response()->json([
                'message' => 'Subject and body are required when no template is selected.',
            ], 422);
        }

        $message = HrMessage::create([
            'thread_id' => (string) Str::uuid(),
            'sender_id' => $request->user()->id,
            'recipient_type' => Applicant::class,
            'recipient_id' => $applicant->id,
            'subject' => $subject,
            'body' => $body,
            'type' => 'email',
            'status' => 'sent',
            'metadata' => [
                'template_id' => $validated['template_id'] ?? null,
            ],
        ]);

        Mail::to($applicant->email)->queue(
            new HrMail(
                emailSubject: $subject,
                emailBody: $body,
                senderName: $request->user()->name,
                recipientName: $applicant->full_name,
                companyName: (string) config('app.name'),
            )
        );

        $message->update(['status' => 'delivered']);

        return response()->json([
            'message' => 'Email sent to ' . $applicant->full_name,
            'hr_message' => $message,
        ]);
    }

    public function templates(Request $request)
    {
        $templates = HrMessageTemplate::query()
            ->where('is_active', true)
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', (string) $request->string('category'));
            })
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $grouped = $templates
            ->groupBy('category')
            ->map(function (Collection $group, string $category) {
                return [
                    'category' => $category,
                    'label' => ucfirst($category),
                    'templates' => $group->values(),
                ];
            })
            ->values();

        return response()->json([
            'templates' => $templates,
            'grouped' => $grouped,
        ]);
    }

    public function thread(Request $request, string $threadId)
    {
        $messages = $this->threadScopeForUser($request, $threadId)
            ->with('sender:id,name,email')
            ->orderBy('created_at')
            ->get()
            ->map(function (HrMessage $message) use ($request) {
                return [
                    'id' => $message->id,
                    'thread_id' => $message->thread_id,
                    'subject' => $message->subject,
                    'body' => $message->body,
                    'type' => $message->type,
                    'status' => $message->status,
                    'read' => $message->isRead(),
                    'mine' => $message->sender_id === $request->user()->id,
                    'sender' => [
                        'id' => $message->sender?->id,
                        'name' => $message->sender?->name ?? 'Unknown',
                        'initials' => $this->initials($message->sender?->name),
                    ],
                    'recipient_type' => class_basename($message->recipient_type),
                    'recipient_id' => $message->recipient_id,
                    'time' => $message->created_at->diffForHumans(),
                    'created_at' => $message->created_at->format('M d, Y H:i'),
                ];
            });

        return response()->json([
            'messages' => $messages,
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        $message = $this->messageScopeForUser($request)
            ->whereKey($id)
            ->firstOrFail();

        $message->markRead();

        return response()->json([
            'message' => 'Marked as read.',
        ]);
    }

    public function announcements(Request $request)
    {
        $employee = $this->resolveEmployee($request->user());

        $query = HrAnnouncement::query()
            ->with('createdBy:id,name')
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) use ($employee) {
                $q->where('audience', 'all');

                if ($employee?->department_id) {
                    $q->orWhere(function ($inner) use ($employee) {
                        $inner->where('audience', 'department')
                            ->whereJsonContains('target_departments', $employee->department_id);
                    });
                }

                if ($employee?->id) {
                    $q->orWhere(function ($inner) use ($employee) {
                        $inner->where('audience', 'specific')
                            ->whereJsonContains('target_employees', $employee->id);
                    });
                }
            })
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', (string) $request->string('type'));
        }

        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));
        $announcements = $query->paginate($perPage)->through(function (HrAnnouncement $announcement) {
            return [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'type' => $announcement->type,
                'type_icon' => $announcement->type_icon,
                'priority' => $announcement->priority,
                'priority_color' => $announcement->priority_color,
                'audience' => $announcement->audience,
                'author' => $announcement->createdBy?->name ?? 'HR',
                'published_at' => $announcement->published_at?->format('M d, Y'),
                'expires_at' => $announcement->expires_at?->format('M d, Y'),
                'time_ago' => $announcement->published_at?->diffForHumans(),
            ];
        });

        return response()->json([
            'announcements' => $announcements,
        ]);
    }

    public function createAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', 'in:general,urgent,event,policy'],
            'audience' => ['required', 'in:all,department,specific'],
            'target_departments' => ['nullable', 'array'],
            'target_departments.*' => ['integer'],
            'target_employees' => ['nullable', 'array'],
            'target_employees.*' => ['integer'],
            'priority' => ['nullable', 'in:normal,high,urgent'],
            'send_email' => ['boolean'],
            'send_notification' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'publish_now' => ['boolean'],
        ]);

        $publishNow = (bool) ($validated['publish_now'] ?? false);

        $announcement = HrAnnouncement::create([
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'type' => $validated['type'],
            'audience' => $validated['audience'],
            'target_departments' => $validated['target_departments'] ?? null,
            'target_employees' => $validated['target_employees'] ?? null,
            'priority' => $validated['priority'] ?? 'normal',
            'send_email' => (bool) ($validated['send_email'] ?? false),
            'send_notification' => (bool) ($validated['send_notification'] ?? true),
            'expires_at' => $validated['expires_at'] ?? null,
            'status' => $publishNow ? 'published' : 'draft',
            'published_at' => $publishNow ? now() : null,
        ]);

        if ($publishNow) {
            $employees = $this->targetEmployees($announcement);

            foreach ($employees as $employee) {
                $linkedUser = User::query()
                    ->where('email', $employee->personal_email)
                    ->orWhere('email', $employee->work_email)
                    ->first();

                if ($announcement->send_notification) {
                    $linkedUser?->notify(new LeaveRequestNotification(
                        message: 'Announcement: ' . $announcement->title,
                        type: 'announcement',
                        link: '/hr/announcements',
                        icon: 'mdi-bullhorn',
                        color: $announcement->priority_color,
                    ));
                }

                if ($announcement->send_email) {
                    $email = $employee->work_email ?: $employee->personal_email;
                    if ($email) {
                        Mail::to($email)->queue(
                            new HrMail(
                                emailSubject: $announcement->title,
                                emailBody: $announcement->body,
                                senderName: $request->user()->name,
                                recipientName: $employee->full_name,
                                companyName: (string) config('app.name'),
                            )
                        );
                    }
                }
            }
        }

        return response()->json([
            'message' => $publishNow ? 'Announcement published.' : 'Announcement saved as draft.',
            'announcement' => $announcement,
        ]);
    }

        public function recipients(Request $request)
    {
        $query = trim((string) $request->input('q', ''));
        $departmentId = $request->input('department_id');

        if ($departmentId) {
            $employees = Employee::query()
                ->with(['designation:id,name', 'department:id,name'])
                ->where('department_id', $departmentId)
                ->where('employment_status', 'Active')
                ->get()
                ->map(function (Employee $employee) {
                    return [
                        'id' => $employee->id,
                        'name' => $employee->full_name,
                        'email' => $employee->work_email ?? $employee->personal_email,
                        'designation' => $employee->designation?->name,
                        'type' => 'employee',
                        'initials' => $employee->initials,
                    ];
                });

            return response()->json([
                'recipients' => $employees->values(),
            ]);
        }

        $searchAll = mb_strtolower($query) === 'all';

        $employees = Employee::query()
            ->with(['designation:id,name', 'department:id,name'])
            ->where('employment_status', 'Active')
            ->when($query !== '' && ! $searchAll, function ($employeeQuery) use ($query) {
                $employeeQuery->where(function ($q) use ($query) {
                    $q->where('first_name', 'like', '%' . $query . '%')
                        ->orWhere('last_name', 'like', '%' . $query . '%')
                        ->orWhere('work_email', 'like', '%' . $query . '%')
                        ->orWhere('employee_id', 'like', '%' . $query . '%');
                });
            })
            ->limit($searchAll ? 50 : 8)
            ->get()
            ->map(function (Employee $employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'email' => $employee->work_email ?? $employee->personal_email,
                    'designation' => $employee->designation?->name,
                    'department' => $employee->department?->name,
                    'type' => 'employee',
                    'initials' => $employee->initials,
                ];
            });

        $departments = Department::query()
            ->withCount([
                'employees as member_count' => function ($employeeQuery) {
                    $employeeQuery->where('employment_status', 'Active');
                },
            ])
            ->when($query !== '' && ! $searchAll, function ($departmentQuery) use ($query) {
                $departmentQuery->where('name', 'like', '%' . $query . '%');
            })
            ->limit(4)
            ->get()
            ->map(function (Department $department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'member_count' => (int) ($department->member_count ?? 0),
                    'type' => 'department',
                    'initials' => $department->initials,
                ];
            });

        $results = $employees
            ->concat($departments)
            ->values();

        return response()->json([
            'recipients' => $results,
            'employees' => $employees->values(),
            'departments' => $departments->values(),
        ]);
    }
    private function resolveEmployee(User $user): ?Employee
    {
        return Employee::query()
            ->where(function ($query) use ($user) {
                $query->where('personal_email', $user->email)
                    ->orWhere('work_email', $user->email);
            })
            ->first();
    }

    private function resolveRecipientEmail(mixed $recipient): ?string
    {
        return match (true) {
            $recipient instanceof Employee => $recipient->work_email ?: $recipient->personal_email,
            $recipient instanceof Applicant => $recipient->email,
            $recipient instanceof User => $recipient->email,
            default => null,
        };
    }

    private function resolveRecipientName(mixed $recipient): string
    {
        return match (true) {
            $recipient instanceof Employee => $recipient->full_name ?? '',
            $recipient instanceof Applicant => $recipient->full_name ?? '',
            $recipient instanceof User => $recipient->name ?? '',
            default => '',
        };
    }

    private function targetEmployees(HrAnnouncement $announcement): Collection
    {
        return match ($announcement->audience) {
            'all' => Employee::query()
                ->where('employment_status', 'Active')
                ->get(),
            'department' => Employee::query()
                ->whereIn('department_id', $announcement->target_departments ?? [])
                ->where('employment_status', 'Active')
                ->get(),
            default => Employee::query()
                ->whereIn('id', $announcement->target_employees ?? [])
                ->get(),
        };
    }

    private function initials(?string $name): string
    {
        if (blank($name)) {
            return 'U';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $word) => Str::substr($word, 0, 1))
            ->implode('');

        return strtoupper($initials ?: 'U');
    }

    private function messageScopeForUser(Request $request)
    {
        $user = $request->user();
        $employee = $this->resolveEmployee($user);

        return HrMessage::query()->where(function ($query) use ($user, $employee) {
            $query->where('sender_id', $user->id)
                ->orWhere(function ($recipientQuery) use ($user) {
                    $recipientQuery->where('recipient_type', User::class)
                        ->where('recipient_id', $user->id);
                });

            if ($employee) {
                $query->orWhere(function ($recipientQuery) use ($employee) {
                    $recipientQuery->where('recipient_type', Employee::class)
                        ->where('recipient_id', $employee->id);
                });
            }
        });
    }

    private function threadScopeForUser(Request $request, string $threadId)
    {
        return $this->messageScopeForUser($request)->where('thread_id', $threadId);
    }
}


