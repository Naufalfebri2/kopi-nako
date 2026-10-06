<?php

namespace App\Models;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\LogRequest;
use App\Models\ShiftSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ShiftRequest extends Model
{
    protected $table = 'shift_request';
    public $timestamps = false;
    protected $fillable = [
        'employee_id', 'schedule_id', 'leave_id', 'partner_id',
        'partner_schedule_id', 'status', 'reason', 'created_by',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function schedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'schedule_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_id');
    }

    public function partner()
    {
        return $this->belongsTo(Employee::class, 'partner_id');
    }

    public function partnerSchedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'partner_schedule_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(LogRequest::class, 'id_shiftrequest');
    }
}
