<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\ScheduleDelivery;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleDeliveryController extends Controller
{
    public function __construct(private ScheduleDelivery $delivery)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(Role::SUPERVISOR), 403);

        $data = $request->validate([
            'periode' => ['required', 'in:mingguan,bulanan'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
        ], [
            'periode.required' => 'Pilih periode jadwal.',
            'periode.in' => 'Periode tidak valid.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date_format' => 'Format tanggal tidak valid.',
        ]);

        $result = $this->delivery->send(
            $data['periode'],
            Carbon::createFromFormat('Y-m-d', $data['tanggal'])->startOfDay(),
        );

        if ($result['sent'] === 0 && $result['failed'] === 0) {
            return back()->with('error', 'Belum ada jadwal pada periode ini, jadi tidak ada yang dikirim.');
        }

        $notice = "Jadwal dikirim ke {$result['sent']} karyawan.";

        if ($result['failed'] > 0) {
            $notice .= " {$result['failed']} gagal (email kosong atau pengiriman bermasalah).";
        }

        return back()->with('notice', $notice);
    }
}
