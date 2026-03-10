<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    use HasFactory;

    protected $table = 'designations';

    protected $fillable = [
        'name',
        'department_id',
        'description',
        'level',
        'status'
    ];

    public function department()
    {
        return $this->belongsTo(\App\Models\HR\Department::class, 'department_id');
    }

    public function employees()
    {
        return $this->hasMany(\App\Models\HR\Employee::class, 'designation_id');
    }
}
