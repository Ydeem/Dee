<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $table = 'expenses';

    protected $fillable = [
        'employee_id',
        'title',
        'category',
        'amount',
        'currency',
        'expense_date',
        'description',
        'receipt',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejection_reason',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected $appends = [
        'status_color',
        'receipt_url',
        'can_approve',
        'can_reject',
        'can_pay',
    ];

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Pending' => 'warning',
            'Approved' => 'success',
            'Rejected' => 'error',
            'Paid' => 'primary',
            default => 'default',
        };
    }

    public function getReceiptUrlAttribute(): ?string
    {
        return $this->receipt
            ? asset('storage/' . $this->receipt)
            : null;
    }

    public function getCanApproveAttribute(): bool
    {
        return $this->status === 'Pending';
    }

    public function getCanRejectAttribute(): bool
    {
        return in_array($this->status, ['Pending', 'Approved'], true);
    }

    public function getCanPayAttribute(): bool
    {
        return $this->status === 'Approved';
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(Employee::class, 'rejected_by');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhereHas('employee', fn ($employeeQuery) =>
                    $employeeQuery->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                );
        });
    }
}
