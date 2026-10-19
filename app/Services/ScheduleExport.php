<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use App\Services\ScheduleService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Ekspor jadwal shift ke Excel. Isi mengikuti ScheduleService::weeklyRows,
// jadi filter jabatan dan batas lihat Leader sama dengan yang tampil di layar.
class ScheduleExport
{
    private const FIRST_ROW = 3;

    public function __construct(private ScheduleService $schedules)
    {
    }

    public function spreadsheet(string $view, Carbon $date, ?int $jobId, User $viewer, string $periodLabel): Spreadsheet
    {
        $days = $view === 'harian' ? collect([$date->copy()]) : $this->schedules->weekDays($date);
        $rows = $this->schedules->weeklyRows($days, $jobId, $viewer);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadwal Shift');

        $this->put($sheet, 1, 1, 'Jadwal Shift · '.$periodLabel);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headers = ['Karyawan', 'Jabatan'];
        foreach ($days as $day) {
            $headers[] = $day->copy()->locale('id')->translatedFormat('D, j M Y');
        }

        foreach ($headers as $i => $header) {
            $this->put($sheet, $i + 1, self::FIRST_ROW, $header);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = 'A'.self::FIRST_ROW.':'.$lastColumn.self::FIRST_ROW;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EDE7DF');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowNumber = self::FIRST_ROW;
        foreach ($rows as $row) {
            /** @var array{employee: Employee, cells: array<int, ShiftScheduleDetail|null>} $row */
            $rowNumber++;
            $this->put($sheet, 1, $rowNumber, $row['employee']->user->name);
            $this->put($sheet, 2, $rowNumber, (string) $row['employee']->job?->name);

            foreach ($row['cells'] as $i => $detail) {
                $shift = $detail?->schedule->masterShift;
                $this->put($sheet, $i + 3, $rowNumber, $shift ? $shift->name.' '.$shift->time_range : 'Libur');
            }
        }

        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
        $sheet->freezePane('C'.(self::FIRST_ROW + 1));

        return $spreadsheet;
    }

    public function write(Spreadsheet $spreadsheet): void
    {
        (new Xlsx($spreadsheet))->save('php://output');
    }

    // Selalu ditulis sebagai teks agar nama yang diawali "=" tidak dibaca sebagai rumus
    private function put(Worksheet $sheet, int $column, int $row, string $value): void
    {
        $sheet->setCellValueExplicit(
            Coordinate::stringFromColumnIndex($column).$row,
            $value,
            DataType::TYPE_STRING
        );
    }
}
