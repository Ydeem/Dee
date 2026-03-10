<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    use HasFactory;

    protected $table = 'interviews';

    protected $fillable = [
        'applicant_id',
        'interviewer_id',
        'scheduled_at',
        'type',
        'location_or_link',
        'notes',
        'result',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function interviewer()
    {
        return $this->belongsTo(Employee::class, 'interviewer_id');
    }
}

