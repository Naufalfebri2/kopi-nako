<?php

namespace App\Models;

use App\Models\Employee;
use App\Models\ShiftRequest;
use App\Models\ShiftSchedule;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notification';
    public $timestamps = false;
    protected $fillable = ['id_employee', 'schedule_id', 'request_id', 'status', 'message'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id_employee');
    }

    public function schedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'schedule_id');
    }

    public function shiftRequest()
    {
        return $this->belongsTo(ShiftRequest::class, 'request_id');
    }
}