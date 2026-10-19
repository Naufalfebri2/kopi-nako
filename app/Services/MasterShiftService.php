<?php

namespace App\Services;

use App\Models\MasterShift;

class MasterShiftService
{
    public function create(array $data): MasterShift
    {
        return MasterShift::create($this->attributes($data));
    }

    public function update(MasterShift $shift, array $data): void
    {
        $shift->update($this->attributes($data));
    }

    // Jam disimpan sebagai H:i:s, seragam dengan data yang sudah ada
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'start_date' => $data['start_date'].':00',
            'end_date' => $data['end_date'].':00',
        ];
    }
}