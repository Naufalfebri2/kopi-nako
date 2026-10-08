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

    // Contoh: "09.00–15.00"
    public function getTimeRangeAttribute(): string
    {
        return $this->formatTime($this->start_date).'–'.$this->formatTime($this->end_date);
    }

    private function formatTime(string $time): string
    {
        return str_replace(':', '.', substr($time, 0, 5));
    }
}
