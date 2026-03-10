<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';

    protected $fillable = [
        'name',
        'code',
        'description',
        'manager_id',
        'status',
    ];

    public function employees()
    {
        return $this->hasMany(\App\Models\HR\Employee::class, 'department_id');
    }

    public function manager()
    {
        return $this->belongsTo(\App\Models\HR\Employee::class, 'manager_id');
    }
}
