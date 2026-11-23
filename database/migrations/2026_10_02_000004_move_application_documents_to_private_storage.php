<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pindahkan dokumen sokongan dari disk 'public' (boleh diakses tanpa log masuk)
 * ke disk 'local' (private): applications/{uuid permohonan}/documents/{nama fail}.
 * Termasuk dokumen yang telah di-soft delete (rekod audit).
 */
return new class extends Migration
{
    public function up(): void
    {
        $documents = DB::table('application_documents')
            ->join('applications', 'applications.id', '=', 'application_documents.application_id')
            ->where('application_documents.file_path', 'like', 'documents/%')
            ->get(['application_documents.id', 'application_documents.file_path', 'applications.uuid as application_uuid']);

        foreach ($documents as $document) {
            $newPath = "applications/{$document->application_uuid}/documents/" . basename($document->file_path);
            $this->moveFile('public', $document->file_path, 'local', $newPath);

            DB::table('application_documents')->where('id', $document->id)->update(['file_path' => $newPath]);
        }
    }

    public function down(): void
    {
        $documents = DB::table('application_documents')
            ->where('file_path', 'like', 'applications/%')
            ->get(['id', 'document_type', 'file_path']);

        foreach ($documents as $document) {
            $folder = $document->document_type === 'PROPOSAL_PAPER' ? 'documents/proposals' : 'documents/slides';
            $oldPath = $folder . '/' . basename($document->file_path);
            $this->moveFile('local', $document->file_path, 'public', $oldPath);

            DB::table('application_documents')->where('id', $document->id)->update(['file_path' => $oldPath]);
        }
    }

    private function moveFile(string $fromDisk, string $fromPath, string $toDisk, string $toPath): void
    {
        $source = Storage::disk($fromDisk);

        if (! $source->exists($fromPath)) {
            return;
        }

        Storage::disk($toDisk)->writeStream($toPath, $source->readStream($fromPath));
        $source->delete($fromPath);
    }
};
