<?php

namespace App\Models;

use App\Models\Employee;
use App\Models\ShiftSchedule;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $id_schedule
 * @property int $id_employee
 * @property string|null $hour
 * @property bool $status_lead
 * @property \Illuminate\Support\Carbon $date
 */
class ShiftScheduleDetail extends Model
{
    protected $table = 'shift_schedule_detail';
    public $timestamps = false;
    protected $fillable = ['id_schedule', 'id_employee', 'hour', 'status_lead', 'date'];
    protected $casts = [
        'status_lead' => 'boolean',
        'date' => 'date',
    ];

    public function schedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'id_schedule');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id_employee');
    }
}