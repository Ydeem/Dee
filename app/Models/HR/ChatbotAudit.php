<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotAudit extends Model
{
    protected $table = 'hr_chatbot_audits';

    protected $fillable = [
        'user_id',
        'user_name',
        'role_label',
        'roles',
        'permissions_count',
        'message',
        'topic',
        'blocked',
        'block_reason',
        'response_excerpt',
        'actions_count',
        'response_time_ms',
        'meta',
    ];

    protected $casts = [
        'roles' => 'array',
        'blocked' => 'boolean',
        'permissions_count' => 'integer',
        'actions_count' => 'integer',
        'response_time_ms' => 'integer',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
