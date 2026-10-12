<?php

namespace App\Models;

use App\Mail\NotificationMail;
use App\Models\Employee;
use App\Models\ShiftRequest;
use App\Models\ShiftSchedule;
use App\Services\WhatsAppGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Notification extends Model
{
    protected $table = 'notification';
    public $timestamps = false;
    protected $fillable = ['id_employee', 'schedule_id', 'request_id', 'status', 'message'];

    // Setiap notifikasi di aplikasi juga dikirim lewat email dan WhatsApp ke karyawan penerima.
    protected static function booted(): void
    {
        static::created(function (self $notification) {
            DB::afterCommit(function () use ($notification) {
                $notification->sendEmail();
                $notification->sendWhatsApp();
            });
        });
    }

    public function sendEmail(): void
    {
        try {
            $email = $this->employee?->user?->email;

            if (! $email) {
                return;
            }

            Mail::to($email)->send(new NotificationMail($this->message));
        } catch (Throwable $e) {
            // Email gagal tidak boleh menggagalkan proses utama. Hanya nama kelas yang dicatat.
            Log::warning('Email notifikasi gagal dikirim.', ['exception' => $e::class]);
        }
    }

    public function sendWhatsApp(): void
    {
        app(WhatsAppGateway::class)->send($this->employee?->no_phone, $this->message);
    }

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