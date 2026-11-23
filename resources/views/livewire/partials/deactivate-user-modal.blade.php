{{-- UM11: modal Nyahaktif Akaun (sebab wajib + pengesahan). Memerlukan $deactivating, $inactiveReasonOptions. --}}
@if ($deactivating)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" wire:key="deactivate-{{ $deactivating->uuid }}">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
            <h3 class="text-base font-bold text-slate-800 border-b pb-2">Nyahaktif Akaun Pengguna</h3>

            <p class="text-xs text-slate-600">
                <span class="font-semibold text-slate-800">{{ $deactivating->name }}</span> ({{ $deactivating->email }})
                tidak akan dapat log masuk atau menggunakan sistem selepas akaun dinyahaktifkan.
            </p>

            <div>
                <x-form.select label="Sebab Tidak Aktif" wire:model.live="inactiveReason" required>
                    <option value="">-- Pilih Sebab --</option>
                    @foreach ($inactiveReasonOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-form.select>
                @error('inactiveReason') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="flex justify-end space-x-2 pt-4 border-t">
                <button type="button" wire:click="closeDeactivate" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</button>
                <button type="button" wire:click="deactivate" wire:loading.attr="disabled" wire:target="deactivate"
                    wire:confirm="Nyahaktifkan akaun {{ $deactivating->name }}{{ $inactiveReason !== '' && isset($inactiveReasonOptions[$inactiveReason]) ? ' atas sebab ' . $inactiveReasonOptions[$inactiveReason] : '' }}? Akses ke sistem akan disekat serta-merta."
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white text-xs font-bold rounded-xl transition">Nyahaktif</button>
            </div>
        </div>
    </div>
@endif
