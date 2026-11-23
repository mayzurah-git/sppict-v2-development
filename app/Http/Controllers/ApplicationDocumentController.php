<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\ApplicationSubmissionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationDocumentController extends Controller
{
    /**
     * Buka dokumen sokongan dari storan private selepas semakan kebenaran.
     * PDF dipaparkan dalam pelayar; Word dimuat turun.
     */
    public function __invoke(string $applicationUuid, string $documentUuid): StreamedResponse
    {
        $application = Application::where('uuid', $applicationUuid)->firstOrFail();
        Gate::authorize('view', $application);

        $document = $application->documents()->where('uuid', $documentUuid)->firstOrFail();
        $disk = Storage::disk(ApplicationSubmissionService::DOCUMENT_DISK);

        abort_unless($disk->exists($document->file_path), 404);

        $disposition = $document->mime_type === 'application/pdf' ? 'inline' : 'attachment';

        return $disk->response($document->file_path, $document->file_name, [
            'Content-Type' => $document->mime_type,
        ], $disposition);
    }
}
