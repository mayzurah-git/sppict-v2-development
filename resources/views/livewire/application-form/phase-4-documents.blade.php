<div class="space-y-6" wire:key="phase-4-form">
    <div class="border-b pb-2">
        <h3 class="text-lg font-semibold text-gray-700">Muat Naik Dokumen Sokongan</h3>
        <p class="text-xs text-gray-500">Kertas Cadangan dalam format PDF atau Word (.docx), dan Slaid Pembentangan dalam format PDF sahaja (Maksimum 25MB setiap fail).</p>
    </div>

    <x-form.file-upload
        label="1. Kertas Cadangan Projek (Wajib)"
        wire:model="form.proposal_paper"
        :file="$form->proposal_paper"
        :existing="$this->existingDocuments->get('PROPOSAL_PAPER')"
        :application-uuid="$this->applicationUuid"
        accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        formats="PDF / DOCX"
        :error="$errors->first('form.proposal_paper')"
        target="form.proposal_paper"
        required
    />

    <x-form.file-upload
        label="2. Slaid Pembentangan"
        wire:model="form.presentation_slide"
        :file="$form->presentation_slide"
        :existing="$this->existingDocuments->get('PRESENTATION_SLIDE_JTICT')"
        :application-uuid="$this->applicationUuid"
        accept=".pdf,application/pdf"
        formats="PDF"
        :error="$errors->first('form.presentation_slide')"
        target="form.presentation_slide"
    />
</div>
