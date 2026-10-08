<?php

namespace App\Models;

use App\Models\MasterShift;
use App\Models\ShiftRequest;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    protected $table = 'shift_schedule';
    public $timestamps = false;
    protected $fillable = ['shift_id', 'status', 'publish_by'];
    protected $casts = ['created_at' => 'datetime'];

    public function masterShift()
    {
        return $this->belongsTo(MasterShift::class, 'shift_id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publish_by');
    }

    public function details()
    {
        return $this->hasMany(ShiftScheduleDetail::class, 'id_schedule');
    }

    public function requests()
    {
        return $this->hasMany(ShiftRequest::class, 'schedule_id');
    }
}
