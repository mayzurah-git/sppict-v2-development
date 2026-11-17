<div class="grid grid-cols-1 md:grid-cols-2 gap-4" wire:key="phase-4-form">
    <x-form.file-upload
        label="1. Kertas Cadangan Projek"
        wire:model="form.proposal_paper"
        :file="$form->proposal_paper"
        :existing="$this->existingDocuments->get('PROPOSAL_PAPER')"
        :application-uuid="$this->applicationUuid"
        accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        formats="PDF / DOCX"
        :error="$errors->first('form.proposal_paper')"
        target="form.proposal_paper"
        :link-class="$theme['link']"
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
        :link-class="$theme['link']"
    />
</div>
