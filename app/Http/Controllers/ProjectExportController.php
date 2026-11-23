<?php

namespace App\Http\Controllers;

use App\Exports\ProjectListExport;
use App\Exports\ProjectSummaryExport;
use App\Models\Project;
use App\Services\ProjectQueryService;
use App\Services\ProjectSummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * SP5: eksport (keputusan pemilik 23/11/2026, audit SP0 L.9).
 *
 * - A: Senarai Projek ke Excel, mengikut penapis dan skop akses skrin Senarai Projek (ProjectPolicy@viewAny).
 * - C: Ringkasan Status Kemajuan Projek ke PDF / Excel, Urus Setia dan Pengurusan sahaja (viewStatistics).
 */
class ProjectExportController extends Controller
{
    public function list(Request $request, ProjectQueryService $query): Response
    {
        Gate::authorize('viewAny', Project::class);

        $projects = $query->list($request->user(), $request->only(['search', 'status', 'agency']))->get();

        Log::info("Eksport Senarai Projek ({$projects->count()} projek) oleh ID Pengguna {$request->user()->id}");

        return Excel::download(new ProjectListExport($projects), 'senarai-projek-' . today()->format('Ymd') . '.xlsx');
    }

    public function summaryPdf(Request $request, ProjectSummaryService $summaries): Response
    {
        Gate::authorize('viewStatistics', Project::class);

        $summary = $summaries->build($this->agencyFilter($request));

        Log::info("Eksport Ringkasan Status Kemajuan (PDF) oleh ID Pengguna {$request->user()->id}");

        return Pdf::loadView('exports.project-summary', ['summary' => $summary])
            ->setPaper('a4', 'landscape')
            ->download('ringkasan-status-kemajuan-' . $summary['date']->format('Ymd') . '.pdf');
    }

    public function summaryExcel(Request $request, ProjectSummaryService $summaries): Response
    {
        Gate::authorize('viewStatistics', Project::class);

        $summary = $summaries->build($this->agencyFilter($request));

        Log::info("Eksport Ringkasan Status Kemajuan (Excel) oleh ID Pengguna {$request->user()->id}");

        return Excel::download(new ProjectSummaryExport($summary), 'ringkasan-status-kemajuan-' . $summary['date']->format('Ymd') . '.xlsx');
    }

    private function agencyFilter(Request $request): ?int
    {
        $agency = $request->query('agency');

        return is_string($agency) && ctype_digit($agency) ? (int) $agency : null;
    }
}
