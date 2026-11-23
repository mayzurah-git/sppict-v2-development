<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationReview;
use Illuminate\Support\Facades\Auth;

class ApplicationList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    /**
     * Pemohon: penapis kumpulan (butang) — '' Semua, 'tindakan', 'proses', 'selesai'.
     * Urus Setia menggunakan $statusFilter (satu status pada satu masa).
     */
    public string $group = '';

    public const GROUPS = [
        'tindakan' => ['label' => 'Perlu tindakan', 'statuses' => [ApplicationStatus::Draft, ApplicationStatus::NeedsAmendment, ApplicationStatus::PreJtictAmendment]],
        'proses' => ['label' => 'Dalam proses', 'statuses' => [ApplicationStatus::Submitted, ApplicationStatus::Complete, ApplicationStatus::InJtict]],
        'selesai' => ['label' => 'Selesai', 'statuses' => [ApplicationStatus::Closed]],
    ];

    public function mount(): void
    {
        if (Auth::user()?->hasRole('pentadbir_urus_setia')) {
            // Kad tindakan & menu Permohonan memautkan ke ?status=<nilai enum>; lalai Dihantar.
            $this->statusFilter = ApplicationStatus::tryFrom((string) request()->query('status'))?->value
                ?? ApplicationStatus::Submitted->value;
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function setGroup(string $group): void
    {
        $this->group = array_key_exists($group, self::GROUPS) ? $group : '';
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        $isUrusSetia = $user->hasRole('pentadbir_urus_setia');

        // Penapis menggunakan nilai dalaman enum; nilai tidak sah (cth. label lama) diabaikan.
        $statusFilter = ApplicationStatus::tryFrom($this->statusFilter);

        // PP2: Urus Setia melihat satu status pada satu masa (lalai: Dihantar = menunggu semakan).
        // Draf milik pemohon lain tidak dipaparkan kepada Urus Setia; PP3: pilihan Draf hanya
        // memaparkan draf yang didaftarkan oleh Urus Setia itu sendiri (cth. bagi pihak agensi).
        $urusSetiaOptions = ApplicationStatus::cases();
        $urusSetiaStatus = in_array($statusFilter, $urusSetiaOptions, true) ? $statusFilter : ApplicationStatus::Submitted;

        $applications = Application::query()
            ->with(['applicationCategoryMaster', 'agency:id,code,name'])
            ->when($isUrusSetia, function ($query) use ($urusSetiaStatus, $user) {
                $query->where('status', $urusSetiaStatus->value)
                    ->when($urusSetiaStatus === ApplicationStatus::Draft, fn ($drafts) => $drafts->where('applicant_id', $user->id))
                    // PP4.2A: label status terperinci (statusDisplayLabel) tanpa pertanyaan tambahan setiap baris
                    ->with(['latestPreJtictDecision', 'latestJtictDecision'])
                    // "Dihantar semula": pernah ditetapkan Tidak Lengkap sebelum ini (PP2), atau
                    // PP4.2A: dihantar semula selepas Pindaan Pra JTICT (rekod "Dihantar Semula" dalam application_reviews)
                    ->withExists(['reviews as was_returned' => fn ($review) => $review->where(fn ($returned) => $returned
                        ->where('to_status', ApplicationStatus::NeedsAmendment->value)
                        ->orWhere(fn ($resubmitted) => $resubmitted
                            ->where('from_status', ApplicationStatus::PreJtictAmendment->value)
                            ->where('to_status', ApplicationStatus::Submitted->value)))])
                    // Jenis pindaan bagi penghantaran semula terkini (untuk teks tanda)
                    ->addSelect(['last_resubmitted_from' => ApplicationReview::select('from_status')
                        ->whereColumn('application_id', 'applications.id')
                        ->where('to_status', ApplicationStatus::Submitted->value)
                        ->whereIn('from_status', [ApplicationStatus::NeedsAmendment->value, ApplicationStatus::PreJtictAmendment->value])
                        ->latest('id')
                        ->limit(1)]);
            }, function ($query) use ($user) {
                // Permohonan sendiri, atau projek yang dipertanggungjawabkan (termasuk yang diambil alih);
                // keputusan mesyuarat terkini untuk lajur Keputusan.
                $query->forApplicant($user)->with(['latestPreJtictDecision', 'latestJtictDecision']);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reference_number', 'like', '%' . $this->search . '%')
                      ->orWhere('title', 'like', '%' . $this->search . '%');
                });
            })
            ->when($statusFilter && ! $isUrusSetia, function ($query) use ($statusFilter) {
                $query->where('status', $statusFilter->value);
            })
            ->when(! $isUrusSetia && isset(self::GROUPS[$this->group]), function ($query) {
                $query->whereIn('status', array_map(fn ($status) => $status->value, self::GROUPS[$this->group]['statuses']));
            })
            ->latest()
            ->paginate(10);

        // Bilangan bagi setiap butang kumpulan (pemohon sahaja).
        $groupCounts = [];
        if (! $isUrusSetia) {
            $byStatus = Application::query()->forApplicant($user)
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $groupCounts[''] = (int) $byStatus->sum();
            foreach (self::GROUPS as $key => $definition) {
                $groupCounts[$key] = (int) collect($definition['statuses'])->sum(fn ($status) => $byStatus[$status->value] ?? 0);
            }
        }

        return view('livewire.application-list', [
            'applications' => $applications,
            'isUrusSetia' => $isUrusSetia,
            'groupCounts' => $groupCounts,
            'statusOptions' => $isUrusSetia ? $urusSetiaOptions : ApplicationStatus::cases(),
        ]);
    }
}