<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingMinutesController extends Controller
{
    /**
     * Buka Minit Mesyuarat Terdahulu (PDF) dari storan private. Akses dikawal oleh middleware laluan.
     */
    public function __invoke(string $uuid): StreamedResponse
    {
        $meeting = Meeting::where('uuid', $uuid)->firstOrFail();
        $disk = Storage::disk(Meeting::MINUTES_DISK);

        abort_unless($meeting->hasMinutes() && $disk->exists($meeting->minutes_file_path), 404);

        $fileName = 'Minit Mesyuarat Terdahulu - ' . str_replace('/', '-', $meeting->label()) . '.pdf';

        return $disk->response($meeting->minutes_file_path, $fileName, [
            'Content-Type' => 'application/pdf',
        ], 'inline');
    }
}
