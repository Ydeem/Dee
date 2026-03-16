<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrAnnouncement extends Model
{
    use SoftDeletes;

    protected $table = 'hr_announcements';

    protected $fillable = [
        'created_by',
        'title',
        'body',
        'type',
        'audience',
        'target_departments',
        'target_employees',
        'status',
        'priority',
        'send_email',
        'send_notification',
        'attachments',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'target_departments' => 'array',
        'target_employees' => 'array',
        'attachments' => 'array',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'send_email' => 'boolean',
        'send_notification' => 'boolean',
    ];

    protected $appends = [
        'priority_color',
        'type_icon',
    ];

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'error',
            'high' => 'warning',
            default => 'primary',
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'urgent' => 'mdi-alert-circle',
            'event' => 'mdi-calendar-star',
            'policy' => 'mdi-file-document-outline',
            default => 'mdi-bullhorn',
        };
    }
}
