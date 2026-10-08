<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\ShiftRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LogRequest extends Model
{
    protected $table = 'log_request';
    protected $fillable = ['id_shiftrequest', 'approve_by', 'status'];
    protected $casts = ['status' => RequestStatus::class];

    public function shiftRequest()
    {
        return $this->belongsTo(ShiftRequest::class, 'id_shiftrequest');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approve_by');
    }
}