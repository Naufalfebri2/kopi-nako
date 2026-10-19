<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // Menandai semua notifikasi milik karyawan yang login sebagai sudah dibaca.
    public function read(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;

        if ($employee) {
            $employee->notifications()->where('status', 'belum_dibaca')->update(['status' => 'dibaca']);
        }

        return back();
    }
}
