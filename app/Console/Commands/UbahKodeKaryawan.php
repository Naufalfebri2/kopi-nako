<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\EmployeeCode;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UbahKodeKaryawan extends Command
{
    protected $signature = 'karyawan:ubah-kode
        {--periode= : Bulan untuk kode lama dalam format YYMM, bawaan bulan berjalan}
        {--jalankan : Terapkan perubahan. Tanpa opsi ini hanya pratinjau}';

    protected $description = 'Mengubah kode karyawan lama (angka) menjadi format YYMM01B001';

    public function handle(EmployeeCode $codes): int
    {
        $period = (string) ($this->option('periode') ?: now()->format('ym'));

        if (! preg_match('/^\d{4}$/', $period)) {
            $this->error('Format --periode harus 4 digit YYMM, contoh 2610.');

            return self::FAILURE;
        }

        // Nomor urut melanjutkan kode terbesar pada periode itu, lalu mengikuti urutan id.
        $prefix = $codes->prefix($period);
        $sequence = $codes->lastSequence($prefix);
        $plan = [];

        try {
            foreach (Employee::with('user')->orderBy('id')->get() as $employee) {
                if ($codes->isValid($employee->nik)) {
                    continue;
                }

                $plan[] = [
                    'id' => $employee->id,
                    'name' => $employee->user->name,
                    'code' => $codes->format($prefix, ++$sequence),
                ];
            }
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($plan === []) {
            $this->info('Semua karyawan sudah memakai format baru.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nama', 'Kode baru'],
            array_map(fn (array $row) => [$row['id'], $row['name'], $row['code']], $plan)
        );

        if (! $this->option('jalankan')) {
            $this->warn('Pratinjau saja, belum ada yang diubah. Ekspor cadangan tabel employees, lalu jalankan lagi dengan --jalankan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $row) {
                Employee::whereKey($row['id'])->update(['nik' => $row['code']]);
            }
        });

        $this->info(count($plan).' kode karyawan diubah.');

        return self::SUCCESS;
    }
}