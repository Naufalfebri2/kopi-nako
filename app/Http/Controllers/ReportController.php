<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const MAX_DAYS = 366;

    public function __construct(private ReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->canViewReports(), 403);

        [$from, $to, $periodError] = $this->period($request);
        $jobId = $request->integer('jabatan') ?: null;

        return view('laporan.index', [
            'report' => $this->reports->build($from, $to, $jobId),
            'from' => $from,
            'to' => $to,
            'jobId' => $jobId,
            'jobs' => Job::orderBy('name')->get(),
            'periodError' => $periodError,
            'periodLabel' => $this->periodLabel($from, $to),
            'pdfUrl' => route('laporan.pdf', $this->query($from, $to, $jobId)),
            'weekUrl' => route('laporan.index', $this->query(now()->startOfWeek(), now()->endOfWeek(), $jobId)),
            'monthUrl' => route('laporan.index', $this->query(now()->startOfMonth(), now()->endOfMonth(), $jobId)),
        ]);
    }

    public function pdf(Request $request): Response
    {
        abort_unless($request->user()->canViewReports(), 403);

        [$from, $to] = $this->period($request);
        $jobId = $request->integer('jabatan') ?: null;

        $pdf = Pdf::loadView('laporan.pdf', [
            'report' => $this->reports->build($from, $to, $jobId),
            'periodLabel' => $this->periodLabel($from, $to),
            'generatedBy' => $request->user()->name,
            'generatedAt' => now()->locale('id')->translatedFormat('j F Y, H.i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('laporan-shift-'.$from->toDateString().'-'.$to->toDateString().'.pdf');
    }

    // Rentang tanggal dari query, dengan bulan berjalan sebagai bawaan
    private function period(Request $request): array
    {
        $input = [
            'dari' => $request->query('dari') ?: now()->startOfMonth()->toDateString(),
            'sampai' => $request->query('sampai') ?: now()->endOfMonth()->toDateString(),
        ];

        $validator = Validator::make($input, [
            'dari' => ['date_format:Y-m-d'],
            'sampai' => ['date_format:Y-m-d', 'after_or_equal:dari'],
        ], [
            'dari.date_format' => 'Format tanggal awal tidak valid.',
            'sampai.date_format' => 'Format tanggal akhir tidak valid.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        if ($validator->fails()) {
            return [now()->startOfMonth(), now()->endOfMonth(), $validator->errors()->first()];
        }

        $from = Carbon::createFromFormat('Y-m-d', $input['dari'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $input['sampai'])->endOfDay();

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            return [now()->startOfMonth(), now()->endOfMonth(), 'Rentang tanggal maksimal '.self::MAX_DAYS.' hari.'];
        }

        return [$from, $to, null];
    }

    private function periodLabel(Carbon $from, Carbon $to): string
    {
        return $from->copy()->locale('id')->translatedFormat('j M Y')
            .' – '.$to->copy()->locale('id')->translatedFormat('j M Y');
    }

    private function query(Carbon $from, Carbon $to, ?int $jobId): array
    {
        return array_filter([
            'dari' => $from->toDateString(),
            'sampai' => $to->toDateString(),
            'jabatan' => $jobId,
        ]);
    }
}