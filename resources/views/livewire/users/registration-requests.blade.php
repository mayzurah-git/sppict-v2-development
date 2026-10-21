<div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Permohonan Pendaftaran Pengguna</h2>
            <p class="text-xs text-slate-500 mt-0.5">Semakan pendaftaran kendiri pegawai Jabatan/Agensi. {{ $pendingCount }} permohonan menunggu kelulusan.</p>
        </div>
        <a href="{{ route('users.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition">
            + Daftar Pengguna
        </a>
    </div>

    @if (session()->has('message'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 text-xs rounded-r-xl">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-xs rounded-r-xl">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden space-y-4 p-4">
        <div class="flex flex-col md:flex-row gap-3">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari Nama / E-mel / No. KP..." class="w-full md:w-1/3 text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <select wire:model.live="statusFilter" class="w-full md:w-1/4 text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="pending">Menunggu Kelulusan</option>
                <option value="rejected">Ditolak</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100 uppercase">
                    <tr>
                        <th class="p-3.5">Nama & E-mel</th>
                        <th class="p-3.5">No. KP</th>
                        <th class="p-3.5">Jabatan/Agensi</th>
                        <th class="p-3.5">Jawatan / Gred</th>
                        <th class="p-3.5">Tarikh Mohon</th>
                        <th class="p-3.5 text-center">Status</th>
                        <th class="p-3.5 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($requests as $applicant)
                        <tr class="hover:bg-slate-50/80 transition" wire:key="request-{{ $applicant->uuid }}">
                            <td class="p-3.5">
                                <p class="font-bold text-slate-800">{{ $applicant->name }}</p>
                                <p class="text-[10px] text-slate-400">{{ $applicant->email }}</p>
                            </td>
                            <td class="p-3.5 font-mono text-slate-600">{{ \App\Livewire\Users\RegistrationRequests::maskIc($applicant->no_kad_pengenalan) }}</td>
                            <td class="p-3.5 text-slate-600">{{ $applicant->agency?->name ?? '—' }}</td>
                            <td class="p-3.5 text-slate-600">{{ $applicant->positionRelation?->title ?? '—' }} / {{ $applicant->grade?->code ?? '—' }}</td>
                            <td class="p-3.5 text-slate-600">{{ $applicant->created_at?->format('d/m/Y h:i A') }}</td>
                            <td class="p-3.5 text-center">
                                @if ($applicant->isPending())
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 font-bold rounded-full text-[10px]">{{ $applicant->status->label() }}</span>
                                @else
                                    <span class="px-2.5 py-1 bg-red-50 text-red-700 font-bold rounded-full text-[10px]">{{ $applicant->status->label() }}</span>
                                @endif
                            </td>
                            <td class="p-3.5 text-center">
                                <button wire:click="showDetail('{{ $applicant->uuid }}')" class="px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 rounded-lg font-bold text-xs transition">
                                    Semak
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-slate-400">Tiada permohonan dijumpai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $requests->links() }}</div>
    </div>

    @if ($viewing)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" wire:key="detail-{{ $viewing->uuid }}">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-base font-bold text-slate-800 border-b pb-2">Butiran Permohonan Pendaftaran</h3>

                <dl class="grid grid-cols-3 gap-x-3 gap-y-2 text-xs">
                    <dt class="text-slate-500">Nama</dt><dd class="col-span-2 font-semibold text-slate-800">{{ $viewing->name }}</dd>
                    <dt class="text-slate-500">No. KP</dt><dd class="col-span-2 font-mono text-slate-800">{{ $viewing->no_kad_pengenalan ?? '—' }}</dd>
                    <dt class="text-slate-500">E-mel Rasmi</dt><dd class="col-span-2 text-slate-800">{{ $viewing->email }}</dd>
                    <dt class="text-slate-500">Jabatan/Agensi</dt><dd class="col-span-2 text-slate-800">{{ $viewing->agency?->name ?? '—' }}</dd>
                    <dt class="text-slate-500">Jawatan</dt><dd class="col-span-2 text-slate-800">{{ $viewing->positionRelation?->title ?? '—' }}</dd>
                    <dt class="text-slate-500">Skim</dt>
                    <dd class="col-span-2 text-slate-800">
                        @if ($scheme = $viewing->positionRelation?->serviceScheme){{ $scheme->code }} — {{ $scheme->name }}@else—@endif
                    </dd>
                    <dt class="text-slate-500">Gred</dt><dd class="col-span-2 text-slate-800">{{ $viewing->grade?->code ?? '—' }}</dd>
                    <dt class="text-slate-500">Tel. Pejabat</dt><dd class="col-span-2 text-slate-800">{{ $viewing->phone_office ?? '—' }}</dd>
                    <dt class="text-slate-500">Tel. HP</dt><dd class="col-span-2 text-slate-800">{{ $viewing->phone_mobile ?? '—' }}</dd>
                    <dt class="text-slate-500">Tarikh Mohon</dt><dd class="col-span-2 text-slate-800">{{ $viewing->created_at?->format('d/m/Y h:i A') }}</dd>
                    <dt class="text-slate-500">Status</dt><dd class="col-span-2 font-semibold text-slate-800">{{ $viewing->status->label() }}</dd>
                </dl>

                @if ($viewing->isPending() && ! $viewing->is(auth()->user()))
                    {{-- UM9: Tahap Capaian ditetapkan semasa kelulusan (disahkan semula di server). --}}
                    <div class="pt-3 border-t">
                        <x-form.select label="Tahap Capaian" wire:model.live="approvalRole" required>
                            @foreach ($approvalRoleOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-form.select>
                        <span class="text-[10px] text-slate-500 block mt-0.5">Peranan ini diberikan apabila permohonan diluluskan. Tiada perubahan peranan jika permohonan ditolak.</span>
                        @error('approvalRole') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                @endif

                <div class="flex justify-end space-x-2 pt-4 border-t">
                    <button type="button" wire:click="closeDetail" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Tutup</button>
                    @if ($viewing->isPending() && ! $viewing->is(auth()->user()))
                        <button type="button" wire:click="reject('{{ $viewing->uuid }}')"
                            wire:confirm="Tolak permohonan pendaftaran {{ $viewing->name }}? Tindakan ini tidak boleh dibatalkan."
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white text-xs font-bold rounded-xl transition">Tolak</button>
                        <button type="button" wire:click="approve('{{ $viewing->uuid }}')"
                            wire:confirm="Luluskan permohonan pendaftaran {{ $viewing->name }} dengan Tahap Capaian: {{ $approvalRoleOptions[$approvalRole] ?? $approvalRole }}? Akaun akan diaktifkan."
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-xs font-bold rounded-xl transition">Lulus</button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
