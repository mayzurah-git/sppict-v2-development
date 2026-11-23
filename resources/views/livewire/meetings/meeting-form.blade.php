<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">{{ $meeting ? 'Kemaskini Mesyuarat' : 'Daftar Mesyuarat' }}</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                @if ($meeting)
                    {{ $meeting->label() }} · Semua medan bertanda <span class="text-red-500">*</span> wajib diisi.
                @else
                    Semua medan bertanda <span class="text-red-500">*</span> wajib diisi.
                @endif
            </p>
        </div>
        <a href="{{ route('meetings.index') }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
            ← Senarai Mesyuarat
        </a>
    </div>

    <form wire:submit="save" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-5">
        {{-- Nama, Bil. & Tahun --}}
        <x-form.select label="Nama Mesyuarat" wire:model.live="meeting_name_id" :error="$errors->first('meeting_name_id')" required>
            <option value="">-- Pilih Nama Mesyuarat --</option>
            @foreach ($meetingNames as $meetingName)
                <option value="{{ $meetingName->id }}">{{ $meetingName->code }} – {{ $meetingName->name }}{{ $meetingName->is_active ? '' : ' (Tidak Aktif)' }}</option>
            @endforeach
        </x-form.select>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <x-form.select label="Bil. Mesyuarat" wire:model="meeting_number_id" :error="$errors->first('meeting_number_id')" required>
                <option value="">-- Pilih Bil. --</option>
                @foreach ($meetingNumbers as $meetingNumber)
                    <option value="{{ $meetingNumber->id }}">{{ $meetingNumber->label() }}{{ $meetingNumber->is_active ? '' : ' (Tidak Aktif)' }}</option>
                @endforeach
            </x-form.select>

            <x-form.select label="Tahun" wire:model.live="meeting_year_id" :error="$errors->first('meeting_year_id')" required>
                <option value="">-- Pilih Tahun --</option>
                @foreach ($meetingYears as $meetingYear)
                    <option value="{{ $meetingYear->id }}">{{ $meetingYear->year }}{{ $meetingYear->is_active ? '' : ' (Tidak Aktif)' }}</option>
                @endforeach
            </x-form.select>
        </div>

        @if ($meeting_name_id && $meeting_year_id)
            <p class="-mt-3 text-[11px] text-slate-500">
                @if ($lastRegistered)
                    Bil. terakhir didaftarkan: <span class="font-bold text-slate-700">{{ $lastRegistered->label() }}</span>
                    ({{ $lastRegistered->meeting_date->format('d/m/Y') }}).
                    @unless ($meeting)
                        Bil. seterusnya dicadangkan secara automatik.
                    @endunless
                @else
                    Belum ada mesyuarat didaftarkan bagi Nama & Tahun ini.
                @endif
            </p>
        @endif

        {{-- Tarikh, Masa & Tempat --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <x-form.date-picker label="Tarikh" wire:model="meeting_date" :error="$errors->first('meeting_date')" required />
            <x-form.time-picker label="Masa" wire:model="meeting_time" :error="$errors->first('meeting_time')" required />
        </div>

        <x-form.input label="Tempat" wire:model="venue" placeholder="Contoh: Bilik Mesyuarat Utama, Wisma Negeri" :error="$errors->first('venue')" required />

        {{-- Status --}}
        <div>
            <span class="block text-xs font-medium text-gray-700">Status Mesyuarat <span class="text-red-500">*</span></span>
            <div class="mt-2 flex items-center gap-6">
                <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                    <input type="radio" wire:model="is_active" value="1" class="border-gray-300 text-blue-600 focus:ring-blue-500">
                    Aktif
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                    <input type="radio" wire:model="is_active" value="0" class="border-gray-300 text-blue-600 focus:ring-blue-500">
                    Tidak Aktif
                </label>
            </div>
            <span class="text-[10px] text-gray-400 block mt-1">Bagi mesyuarat yang menerima permohonan (JTICT), tempoh permohonan dibuka apabila mesyuarat berstatus Aktif dan ditutup selepas Tarikh Tutup Permohonan Projek.</span>
            @error('is_active') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
        </div>

        {{-- Tarikh tutup & kemajuan --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <x-form.date-picker label="Tarikh Tutup Permohonan Projek" wire:model="submission_closes_at" :min-date="$meeting ? null : today()->toDateString()" :error="$errors->first('submission_closes_at')" required />
            <x-form.date-picker label="Tarikh Status Kemaskini Kemajuan Projek" wire:model="progress_update_deadline" :error="$errors->first('progress_update_deadline')" required />
        </div>

        {{-- Minit --}}
        <div class="p-4 bg-gray-50 border rounded-lg space-y-3">
            <div class="flex justify-between items-center">
                <label class="block text-xs font-medium text-gray-700">
                    Muat Naik Minit Mesyuarat Terdahulu <span class="text-red-500">*</span>
                </label>
                <span class="text-[11px] bg-slate-100 text-slate-600 font-medium px-2 py-0.5 rounded">PDF · Maks. 25MB</span>
            </div>

            @if ($meeting?->hasMinutes())
                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-md text-xs gap-3">
                    <span class="text-green-600 font-bold">✓ Minit telah dimuat naik</span>
                    <a href="{{ route('meetings.minutes', $meeting->uuid) }}" target="_blank" class="px-2.5 py-1 bg-white border border-gray-200 text-blue-600 hover:bg-blue-50 font-bold rounded">Lihat</a>
                </div>
                <p class="text-[11px] text-gray-500 italic">Pilih fail baharu di bawah sekiranya anda ingin menggantikan minit sedia ada.</p>
            @endif

            @if (is_object($minutes))
                <div class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-md text-xs gap-3">
                    <div class="min-w-0">
                        <span class="text-blue-700 font-bold">{{ $meeting?->hasMinutes() ? '↻ Akan Menggantikan:' : '✓ Fail Dipilih:' }}</span>
                        <span class="font-semibold text-gray-800 break-all">{{ $minutes->getClientOriginalName() }}</span>
                    </div>
                    <button type="button" wire:click="$set('minutes', null)" class="px-2.5 py-1 bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 font-bold rounded shrink-0">Batal</button>
                </div>
            @endif

            <input type="file" accept=".pdf,application/pdf" wire:model="minutes"
                   class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
            <div wire:loading wire:target="minutes" class="text-xs text-blue-600 font-medium">⏳ Memuat naik & mengesahkan fail...</div>
            @error('minutes') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
        </div>

        {{-- Agenda --}}
        <x-form.rich-text label="Agenda Mesyuarat" wire:model="agenda" :error="$errors->first('agenda')" required />

        {{-- Makluman Emel --}}
        <div class="p-4 bg-blue-50/60 border border-blue-100 rounded-lg">
            <span class="block text-xs font-medium text-gray-700">Makluman Emel</span>
            <label class="mt-2 flex items-start gap-2 text-xs text-gray-700">
                <input type="checkbox" wire:model="send_notification" class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                <span>
                    Klik Untuk Hantar E-mel Notifikasi Kepada Ahli Mesyuarat
                    <span class="block text-[10px] text-gray-500">Dihantar kepada semua pengguna, pengurusan dan urus setia yang aktif.</span>
                </span>
            </label>
            @if ($meeting?->notified_at)
                <p class="mt-2 text-[11px] text-gray-500 italic">Notifikasi terakhir dihantar pada {{ $meeting->notified_at->format('d/m/Y g.i') }} {{ $meeting->notified_at->hour < 12 ? 'pagi' : 'petang' }}.</p>
            @endif
        </div>

        @if ($sequenceWarnings)
            <div class="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-xl space-y-3">
                <p class="text-xs font-bold text-amber-800">⚠ Sila semak sebelum menyimpan:</p>
                <ul class="list-disc pl-5 text-xs text-amber-800 space-y-1">
                    @foreach ($sequenceWarnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
                <p class="text-[11px] text-amber-700">Jika ini Mesyuarat Khas atau susunan ini memang betul, tekan <span class="font-bold">Teruskan Simpan</span>.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('sequenceWarnings', [])" class="px-4 py-2 bg-white border border-amber-200 hover:bg-amber-100 text-amber-800 text-xs font-bold rounded-xl">Semak Semula</button>
                    <button type="button" wire:click="save(true)" wire:loading.attr="disabled" wire:target="save" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl">Teruskan Simpan</button>
                </div>
            </div>
        @endif

        <div class="flex justify-end gap-2 pt-4 border-t">
            <a href="{{ route('meetings.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl">Batal</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save,minutes" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl">
                <span wire:loading.remove wire:target="save">{{ $meeting ? 'Simpan Kemaskini' : 'Daftar Mesyuarat' }}</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
