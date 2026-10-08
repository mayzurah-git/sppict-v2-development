<?php

namespace App\Livewire\MasterData;

use App\Models\ProcurementType;

class ProcurementTypeManagement extends MasterDataManagement
{
    protected function modelClass(): string
    {
        return ProcurementType::class;
    }

    protected function label(): string
    {
        return 'Jenis Perolehan';
    }

    protected function description(): string
    {
        return 'Senarai jenis perolehan untuk pilihan dalam borang Permohonan Projek (Fasa 2).';
    }
}
