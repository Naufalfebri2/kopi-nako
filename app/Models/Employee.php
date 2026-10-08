<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Models\Notification;
use App\Models\ShiftRequest;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employees';
    public $timestamps = false;
    protected $fillable = ['nik', 'id_user', 'no_phone', 'status'];
    protected $casts = ['status' => EmployeeStatus::class];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function schedules()
    {
        return $this->hasMany(ShiftSchedule::class, 'id_employee');
    }

    public function requests()
    {
        return $this->hasMany(ShiftRequest::class, 'employee_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'id_employee');
    }
}
