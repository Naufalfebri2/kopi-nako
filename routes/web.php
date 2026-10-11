<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleDeliveryController;
use App\Http\Controllers\ShiftRequestController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/jadwal', [ScheduleController::class, 'index'])->name('jadwal.index');
    Route::post('/jadwal', [ScheduleController::class, 'store'])->name('jadwal.store');
    Route::post('/jadwal/kirim', [ScheduleDeliveryController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('jadwal.kirim');
    Route::put('/jadwal/{detail}', [ScheduleController::class, 'update'])->name('jadwal.update');
    Route::delete('/jadwal/{detail}', [ScheduleController::class, 'destroy'])->name('jadwal.destroy');

    Route::get('/pengajuan', [ShiftRequestController::class, 'index'])->name('pengajuan.index');
    Route::post('/pengajuan', [ShiftRequestController::class, 'store'])->name('pengajuan.store');
    Route::post('/pengajuan/setujui-semua', [ShiftRequestController::class, 'approveAll'])->name('pengajuan.approve-all');
    Route::get('/pengajuan/{shiftRequest}', [ShiftRequestController::class, 'show'])->name('pengajuan.show');
    Route::post('/pengajuan/{shiftRequest}/keputusan', [ShiftRequestController::class, 'decide'])->name('pengajuan.decide');

    Route::get('/laporan', [ReportController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/pdf', [ReportController::class, 'pdf'])->name('laporan.pdf');

    Route::post('/karyawan', [EmployeeController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('karyawan.store');
    Route::put('/karyawan/{employee}', [EmployeeController::class, 'update'])->name('karyawan.update');
    Route::delete('/karyawan/{employee}', [EmployeeController::class, 'destroy'])->name('karyawan.destroy');
    Route::patch('/karyawan/{employee}/nonaktif', [EmployeeController::class, 'deactivate'])->name('karyawan.deactivate');
});
