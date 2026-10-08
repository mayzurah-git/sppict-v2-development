<?php

namespace App\Livewire\MasterData;

use App\Models\ProjectCategory;
use Illuminate\Validation\Rule;

class ProjectCategoryManagement extends MasterDataManagement
{
    protected function modelClass(): string
    {
        return ProjectCategory::class;
    }

    protected function label(): string
    {
        return 'Kategori Projek';
    }

    protected function description(): string
    {
        return 'Senarai kategori projek untuk pilihan dalam Perincian Projek (Fasa 3).';
    }

    /**
     * Susunan menjana prefix A–Z, jadi mesti antara 1–26 dan tidak berulang.
     */
    protected function sortOrderRules(): array
    {
        return [
            'min:1',
            'max:26',
            Rule::unique('project_categories', 'sort_order')->ignore($this->recordUuid, 'uuid')->whereNull('deleted_at'),
        ];
    }

    protected function sortOrderHint(): ?string
    {
        return 'Susunan menentukan prefix paparan: 1 = A, 2 = B, 3 = C, dan seterusnya.';
    }
}
