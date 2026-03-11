<?php

namespace App\Models;

class EmployeeDocument extends \App\Models\HR\EmployeeDocument
{
    protected $appends = ['file_url', 'url'];

    public function getUrlAttribute(): string
    {
        return $this->file_url;
    }
}
