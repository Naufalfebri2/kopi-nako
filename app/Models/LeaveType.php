<?php

namespace App\Models;

use App\Models\ShiftRequest;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $table = 'leave_type';
    public $timestamps = false;
    protected $fillable = ['name'];

    public function requests()
    {
        return $this->hasMany(ShiftRequest::class, 'leave_id');
    }
}
