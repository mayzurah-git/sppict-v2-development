<div class="space-y-6" wire:key="phase-4-form">
    <div class="border-b pb-2">
        <h3 class="text-lg font-semibold text-gray-700">Fasa 4: Muat Naik Dokumen Sokongan</h3>
        <p class="text-xs text-gray-500">Sila muat naik Kertas Cadangan dan Slaid Pembentangan dalam format PDF (Maksimum 15MB setiap fail).</p>
    </div>

    <x-form.pdf-upload 
        label="1. Kertas Cadangan Projek (Wajib)" 
        wire:model="form.proposal_paper" 
        :file="$form->proposal_paper" 
        :error="$errors->first('form.proposal_paper')" 
        target="form.proposal_paper" 
        required 
    />

    <x-form.pdf-upload 
        label="2. Slaid Pembentangan JTICT / MKK (Pilihan)" 
        wire:model="form.presentation_slide" 
        :file="$form->presentation_slide" 
        :error="$errors->first('form.presentation_slide')" 
        target="form.presentation_slide" 
    />
</div>