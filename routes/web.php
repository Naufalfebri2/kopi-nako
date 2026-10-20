<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MasterEmployeeController;
use App\Http\Controllers\MasterLeaveTypeController;
use App\Http\Controllers\MasterShiftController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleDeliveryController;
use App\Http\Controllers\ShiftRequestController;
use App\Http\Middleware\EnsureRole;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

// Supervisor ke Dashboard, Leader/Head dan Karyawan ke Jadwal Shift. Tamu diarahkan ke login lewat Dashboard.
Route::get('/', function () {
    $user = auth()->user();

    return $user && ! $user->hasRole(Role::SUPERVISOR)
        ? redirect()->route('jadwal.index')
        : redirect()->route('dashboard');
})->name('beranda');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/notifikasi/baca', [NotificationController::class, 'read'])->name('notifikasi.baca');

    // Jadwal Shift dan Pengajuan: semua role (isi datanya dibatasi di controller).
    Route::get('/jadwal', [ScheduleController::class, 'index'])->name('jadwal.index');
    // Ekspor Excel: Leader dan Supervisor (dicek di controller, Karyawan mendapat 403).
    Route::get('/jadwal/ekspor', [ScheduleController::class, 'export'])
        ->middleware('throttle:10,1')
        ->name('jadwal.export');
    Route::post('/jadwal', [ScheduleController::class, 'store'])->name('jadwal.store');
    Route::put('/jadwal/{detail}', [ScheduleController::class, 'update'])->name('jadwal.update');
    Route::delete('/jadwal/{detail}', [ScheduleController::class, 'destroy'])->name('jadwal.destroy');

    Route::get('/pengajuan', [ShiftRequestController::class, 'index'])->name('pengajuan.index');
    Route::post('/pengajuan', [ShiftRequestController::class, 'store'])->name('pengajuan.store');
    Route::post('/pengajuan/setujui-semua', [ShiftRequestController::class, 'approveAll'])->name('pengajuan.approve-all');
    Route::get('/pengajuan/{shiftRequest}', [ShiftRequestController::class, 'show'])->name('pengajuan.show');
    Route::post('/pengajuan/{shiftRequest}/keputusan', [ShiftRequestController::class, 'decide'])->name('pengajuan.decide');

    // Hanya Supervisor.
    Route::middleware(EnsureRole::class.':'.Role::SUPERVISOR)->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::post('/jadwal/kirim', [ScheduleDeliveryController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('jadwal.kirim');

        Route::get('/laporan', [ReportController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/pdf', [ReportController::class, 'pdf'])->name('laporan.pdf');

        Route::post('/karyawan', [EmployeeController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('karyawan.store');
        Route::put('/karyawan/{employee}', [EmployeeController::class, 'update'])->name('karyawan.update');
        Route::delete('/karyawan/{employee}', [EmployeeController::class, 'destroy'])->name('karyawan.destroy');
        Route::patch('/karyawan/{employee}/nonaktif', [EmployeeController::class, 'deactivate'])->name('karyawan.deactivate');
        Route::patch('/karyawan/{employee}/aktif', [EmployeeController::class, 'activate'])->name('karyawan.activate');

        // Master data. Shift dan jenis izin hanya tambah dan ubah, tanpa hapus.
        // Karyawan memakai ulang route karyawan.* di atas.
        Route::prefix('master')->name('master.')->group(function () {
            Route::get('/employee', [MasterEmployeeController::class, 'index'])->name('employee.index');

            Route::get('/shift', [MasterShiftController::class, 'index'])->name('shift.index');
            Route::post('/shift', [MasterShiftController::class, 'store'])->name('shift.store');
            Route::put('/shift/{masterShift}', [MasterShiftController::class, 'update'])->name('shift.update');

            Route::get('/leave-type', [MasterLeaveTypeController::class, 'index'])->name('leave.index');
            Route::post('/leave-type', [MasterLeaveTypeController::class, 'store'])->name('leave.store');
            Route::put('/leave-type/{leaveType}', [MasterLeaveTypeController::class, 'update'])->name('leave.update');
        });
    });
});
