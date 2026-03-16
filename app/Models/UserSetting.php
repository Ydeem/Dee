<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'language',
        'timezone',
        'date_format',
        'currency',
        'email_notifications',
        'sms_notifications',
        'desktop_notifications',
        'notify_leave_approved',
        'notify_leave_rejected',
        'notify_payslip_ready',
        'notify_expense_approved',
        'notify_task_assigned',
        'notify_announcements',
        'show_email_to_colleagues',
        'show_phone_to_colleagues',
        'show_birthday_to_colleagues',
        'show_online_status',
        'allow_profile_search',
        'two_factor_enabled',
        'two_factor_method',
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'desktop_notifications' => 'boolean',
        'notify_leave_approved' => 'boolean',
        'notify_leave_rejected' => 'boolean',
        'notify_payslip_ready' => 'boolean',
        'notify_expense_approved' => 'boolean',
        'notify_task_assigned' => 'boolean',
        'notify_announcements' => 'boolean',
        'show_email_to_colleagues' => 'boolean',
        'show_phone_to_colleagues' => 'boolean',
        'show_birthday_to_colleagues' => 'boolean',
        'show_online_status' => 'boolean',
        'allow_profile_search' => 'boolean',
        'two_factor_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
