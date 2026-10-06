<?php

namespace App\Models;

use App\Models\Employee;
use App\Models\MasterShift;
use App\Models\ShiftRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    protected $table = 'shift_schedule';
    public $timestamps = false;
    protected $fillable = ['id_employee', 'shift_id', 'status', 'publish_by'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id_employee');
    }

    public function masterShift()
    {
        return $this->belongsTo(MasterShift::class, 'shift_id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publish_by');
    }

    public function requests()
    {
        return $this->hasMany(ShiftRequest::class, 'schedule_id');
    }
}
