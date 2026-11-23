<?php

namespace App\Livewire\MasterData;

use App\Models\ApplicationCategory;

class ApplicationCategoryManagement extends MasterDataManagement
{
    protected function modelClass(): string
    {
        return ApplicationCategory::class;
    }

    protected function label(): string
    {
        return 'Kategori Permohonan';
    }

    protected function description(): string
    {
        return 'Senarai kategori permohonan untuk pilihan dalam borang Permohonan Projek (Fasa 1).';
    }
}
