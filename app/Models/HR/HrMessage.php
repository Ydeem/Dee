<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class HrMessage extends Model
{
    use SoftDeletes;

    protected $table = 'hr_messages';

    protected $fillable = [
        'thread_id',
        'sender_id',
        'recipient_type',
        'recipient_id',
        'subject',
        'body',
        'type',
        'status',
        'read_at',
        'attachments',
        'metadata',
    ];

    protected $casts = [
        'attachments' => 'array',
        'metadata' => 'array',
        'read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if (blank($message->thread_id)) {
                $message->thread_id = (string) Str::uuid();
            }
        });
    }

    public function sender()
    {
        return $this->belongsTo(\App\Models\User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->morphTo();
    }

    public function isRead(): bool
    {
        return ! is_null($this->read_at);
    }

    public function markRead(): void
    {
        if (! $this->read_at) {
            $this->update([
                'read_at' => now(),
                'status' => 'read',
            ]);
        }
    }
}
