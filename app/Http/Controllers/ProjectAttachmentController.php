<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectProgressAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SP3: buka dokumen sokongan kemajuan dari storan private selepas semakan ProjectPolicy@view
 * (agensi projek, Urus Setia, Pengurusan; Superadmin ditolak).
 */
class ProjectAttachmentController extends Controller
{
    public function __invoke(string $projectUuid, string $attachmentUuid): StreamedResponse
    {
        $project = Project::where('uuid', $projectUuid)->firstOrFail();
        Gate::authorize('view', $project);

        $attachment = $project->attachments()->where('uuid', $attachmentUuid)->firstOrFail();
        $disk = Storage::disk(ProjectProgressAttachment::DISK);

        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->response($attachment->file_path, $attachment->file_name, [
            'Content-Type' => 'application/pdf',
        ], 'inline');
    }
}
