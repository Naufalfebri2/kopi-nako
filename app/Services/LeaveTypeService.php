<?php

namespace App\Services;

use App\Models\LeaveType;

class LeaveTypeService
{
    public function create(string $name): LeaveType
    {
        return LeaveType::create(['name' => $name]);
    }

    public function update(LeaveType $type, string $name): void
    {
        $type->update(['name' => $name]);
    }
}