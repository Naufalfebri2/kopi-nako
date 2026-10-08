<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Menunggu = 'Menunggu';
    case Disetujui = 'Disetujui';
    case Ditolak = 'Ditolak';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
