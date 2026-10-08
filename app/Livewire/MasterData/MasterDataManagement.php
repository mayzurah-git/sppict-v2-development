<?php

namespace App\Livewire\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Asas selenggara master data Permohonan Projek (Urus Setia sahaja).
 * Setiap subkelas hanya menentukan model dan label; aliran CRUD mengikut pola
 * MeetingNameManagement: modal tambah/kemaskini, aktif/nyahaktif, soft delete jika belum digunakan.
 */
abstract class MasterDataManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = ''; // '' | 'active' | 'inactive'
    public bool $showModal = false;
    public ?string $recordUuid = null;
    public string $code = '';
    public string $name = '';
    public int $sort_order = 0;
    public bool $is_active = true;
    public bool $codeLocked = false;

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * Nama master data untuk tajuk dan mesej, contoh: "Kategori Projek".
     */
    abstract protected function label(): string;

    abstract protected function description(): string;

    /**
     * Peraturan tambahan bagi sort_order (contoh: had A–Z untuk Kategori Projek).
     */
    protected function sortOrderRules(): array
    {
        return ['min:0'];
    }

    protected function sortOrderHint(): ?string
    {
        return null;
    }

    public function mount(): void
    {
        $this->ensureUrusSetia();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openModal(?string $uuid = null): void
    {
        $this->ensureUrusSetia();
        $this->resetErrorBag();
        $this->recordUuid = $uuid;

        if ($uuid) {
            $record = $this->findRecord($uuid);
            $this->code = $record->code;
            $this->name = $record->name;
            $this->sort_order = $record->sort_order;
            $this->is_active = $record->is_active;
            $this->codeLocked = $record->isInUse();
        } else {
            $this->reset(['code', 'name', 'recordUuid', 'codeLocked']);
            $this->sort_order = (int) $this->modelClass()::max('sort_order') + 1;
            $this->is_active = true;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['code', 'name', 'sort_order', 'is_active', 'recordUuid', 'codeLocked']);
    }

    public function save(): void
    {
        $this->ensureUrusSetia();

        $record = $this->recordUuid ? $this->findRecord($this->recordUuid) : null;

        $this->name = trim($this->name);
        $this->code = Str::lower(trim($this->code));

        // Kod dijana daripada nama jika dibiarkan kosong (rekod baharu sahaja).
        if ($this->code === '' && ! $record) {
            $this->code = Str::slug($this->name, '_');
        }

        $table = (new ($this->modelClass()))->getTable();

        $this->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(_[a-z0-9]+)*$/',
                Rule::unique($table, 'code')->ignore($this->recordUuid, 'uuid'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique($table, 'name')->ignore($this->recordUuid, 'uuid')->whereNull('deleted_at'),
            ],
            'sort_order' => ['required', 'integer', ...$this->sortOrderRules()],
            'is_active' => ['boolean'],
        ], [
            'code.regex' => 'Kod hanya boleh mengandungi huruf kecil, nombor dan garis bawah (contoh: projek_baharu).',
            'code.unique' => 'Kod ini telah wujud.',
            'name.unique' => 'Nama ini telah wujud.',
        ], [
            'code' => 'Kod',
            'name' => 'Nama',
            'sort_order' => 'Susunan',
        ]);

        // Kod ialah pengecam stabil: tidak boleh ditukar selepas digunakan oleh transaksi.
        if ($record && $record->code !== $this->code && $record->isInUse()) {
            $this->addError('code', 'Kod tidak boleh ditukar kerana telah digunakan oleh rekod permohonan.');

            return;
        }

        $record ??= new ($this->modelClass());
        $record->fill([
            'code' => $this->code,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ]);
        $record->save();

        session()->flash('message', $this->recordUuid
            ? "{$this->label()} berjaya dikemaskini!"
            : "{$this->label()} baharu berjaya didaftarkan!");

        $this->closeModal();
    }

    public function setStatus(string $uuid, bool $active): void
    {
        $this->ensureUrusSetia();

        $this->findRecord($uuid)->update(['is_active' => $active]);

        session()->flash('message', $active
            ? "{$this->label()} berjaya diaktifkan."
            : "{$this->label()} berjaya dinyahaktifkan.");
    }

    public function delete(string $uuid): void
    {
        $this->ensureUrusSetia();

        $record = $this->findRecord($uuid);

        if ($record->isInUse()) {
            session()->flash('error', "{$this->label()} tidak boleh dipadam kerana telah digunakan oleh rekod permohonan. Sila nyahaktifkan sahaja.");

            return;
        }

        $record->delete();

        session()->flash('message', "{$this->label()} berjaya dipadamkan secara soft delete.");
    }

    protected function findRecord(string $uuid): Model
    {
        return $this->modelClass()::where('uuid', $uuid)->firstOrFail();
    }

    protected function ensureUrusSetia(): void
    {
        abort_unless(auth()->user()?->hasRole('pentadbir_urus_setia'), 403);
    }

    public function render()
    {
        $records = $this->modelClass()::query()
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('code', 'like', '%' . $this->search . '%')
                        ->orWhere('name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->ordered()
            ->paginate(10);

        return view('livewire.master-data.management', [
            'records' => $records,
            'label' => $this->label(),
            'description' => $this->description(),
            'sortOrderHint' => $this->sortOrderHint(),
        ])->layout('layouts.app');
    }
}
