<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\ProjectProgressAttachment;
use App\Models\ProjectProgressReport;
use App\Services\ProjectProgressService;
use App\Services\ProjectReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * SP3: laporan kemajuan bulanan (audit SP0 F.4, F.5, G). Pegawai aktif menyunting Draf, memuat naik dokumen
 * dan menghantar laporan (dikunci). Lain-lain pengguna yang boleh melihat projek: baca sahaja.
 */
#[Layout('layouts.app')]
class ProgressReportPage extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $projectUuid;

    #[Locked]
    public string $reportUuid;

    public string $work_summary = '';

    public string $issues_and_challenges = '';

    public string $corrective_action_plan = '';

    public $upload = null;

    public function mount(string $project, string $report): void
    {
        $this->projectUuid = $project;
        $this->reportUuid = $report;
        $this->authorize('view', $this->project);

        $this->fillFromReport();
    }

    #[Computed]
    public function project(): Project
    {
        return Project::with(['application:id,uuid,reference_number,title', 'agency:id,code,name'])
            ->where('uuid', $this->projectUuid)->firstOrFail();
    }

    #[Computed]
    public function report(): ProjectProgressReport
    {
        return ProjectProgressReport::with(['activitySnapshots', 'attachments.uploader:id,name', 'submitter:id,name', 'dueMeeting.meetingName', 'dueMeeting.meetingNumber', 'dueMeeting.meetingYear'])
            ->where('project_id', $this->project->id)
            ->where('uuid', $this->reportUuid)
            ->firstOrFail();
    }

    #[Computed]
    public function canEdit(): bool
    {
        return ! $this->report->isSubmitted() && Auth::user()->can('manageReports', $this->project);
    }

    public function saveDraft(ProjectReportService $service): void
    {
        $this->authorizeEdit();

        if ($this->guard(fn () => $service->saveDraft($this->report, Auth::user(), $this->fields()))) {
            session()->flash('message', 'Draf laporan telah disimpan.');
            $this->refresh();
        }
    }

    public function submit(ProjectReportService $service): void
    {
        $this->authorizeEdit();

        if ($this->guard(fn () => $service->submit($this->report, Auth::user(), $this->fields()))) {
            session()->flash('message', 'Laporan kemajuan telah dihantar dan dikunci.');
            $this->refresh();
        }
    }

    public function uploadDocument(ProjectProgressService $service): void
    {
        $this->authorizeEdit();

        if ($this->guard(fn () => $service->uploadAttachment($this->project, Auth::user(), $this->upload, report: $this->report))) {
            $this->upload = null;
            session()->flash('message', 'Dokumen berjaya dimuat naik.');
            $this->refresh();
        }
    }

    public function deleteDocument(string $attachmentUuid, ProjectProgressService $service): void
    {
        $this->authorizeEdit();

        $attachment = ProjectProgressAttachment::where('project_progress_report_id', $this->report->id)->where('uuid', $attachmentUuid)->firstOrFail();

        if ($this->guard(fn () => $service->deleteAttachment($attachment, Auth::user()))) {
            $this->refresh();
        }
    }

    public function render()
    {
        $this->authorize('view', $this->project);

        return view('livewire.projects.progress-report-page');
    }

    private function authorizeEdit(): void
    {
        $this->authorize('manageReports', $this->project);
        $this->resetErrorBag();
    }

    /** @return array<string, string> */
    private function fields(): array
    {
        return [
            'work_summary' => $this->work_summary,
            'issues_and_challenges' => $this->issues_and_challenges,
            'corrective_action_plan' => $this->corrective_action_plan,
        ];
    }

    private function fillFromReport(): void
    {
        $this->work_summary = (string) $this->report->work_summary;
        $this->issues_and_challenges = (string) $this->report->issues_and_challenges;
        $this->corrective_action_plan = (string) $this->report->corrective_action_plan;
    }

    private function refresh(): void
    {
        unset($this->project, $this->report, $this->canEdit);
    }

    private function guard(callable $action): bool
    {
        try {
            $action();
        } catch (AuthorizationException $e) {
            $this->refresh();
            session()->flash('error', $e->getMessage());

            return false;
        }

        return true;
    }
}
