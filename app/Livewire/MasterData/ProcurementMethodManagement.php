<?php

namespace App\Livewire\MasterData;

use App\Models\ProcurementMethod;

class ProcurementMethodManagement extends MasterDataManagement
{
    protected function modelClass(): string
    {
        return ProcurementMethod::class;
    }

    protected function label(): string
    {
        return 'Kaedah Perolehan';
    }

    protected function description(): string
    {
        return 'Senarai kaedah perolehan untuk pilihan dalam borang Permohonan Projek (Fasa 2).';
    }
}
