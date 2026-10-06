<?php

namespace App\Models;

use App\Models\ShiftSchedule;
use Illuminate\Database\Eloquent\Model;

class MasterShift extends Model
{
    protected $table = 'master_shift';
    public $timestamps = false;
    protected $fillable = ['name', 'start_date', 'end_date'];

    public function schedules()
    {
        return $this->hasMany(ShiftSchedule::class, 'shift_id');
    }
}
