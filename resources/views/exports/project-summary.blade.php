<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Ringkasan Status Kemajuan Projek {{ $summary['date']->format('d/m/Y') }}</title>
    <style>
        @page { margin: 18mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #0f172a; }
        h1 { font-size: 13pt; margin: 0 0 2pt 0; }
        .meta { color: #475569; margin-bottom: 8pt; }
        .totals { margin: 6pt 0 10pt 0; border-collapse: collapse; }
        .totals td { border: 1px solid #cbd5e1; padding: 4pt 8pt; }
        .totals strong { font-size: 11pt; }
        h2 { font-size: 9.5pt; margin: 12pt 0 4pt 0; background: #e0f2fe; padding: 3pt 5pt; }
        table.data { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        table.data th { background: #f1f5f9; text-align: left; font-weight: bold; border: 1px solid #cbd5e1; padding: 3pt; }
        table.data td { border: 1px solid #e2e8f0; padding: 3pt; vertical-align: top; }
        table.data tr { page-break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; }
        .green { background: #d1fae5; color: #065f46; }
        .amber { background: #fef3c7; color: #92400e; }
        .red { background: #fee2e2; color: #991b1b; }
        .muted { color: #64748b; }
        .badge { padding: 1pt 3pt; font-weight: bold; }
        footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #64748b; }
    </style>
</head>
<body>
    <footer>SPPICT v2 · Dijana pada {{ now()->format('d/m/Y h:i A') }} · Kemajuan dikira pada tarikh laporan berdasarkan aktiviti yang ditanda siap dan tarikh sasaran.</footer>

    <h1>Ringkasan Status Kemajuan Projek ICT</h1>
    <div class="meta">
        Pada {{ $summary['date']->format('d/m/Y') }}
        @if ($summary['agency']) · {{ $summary['agency']->name }} ({{ $summary['agency']->code }}) @else · Semua Jabatan/Agensi @endif
    </div>

    <table class="totals">
        <tr>
            <td>Projek aktif<br><strong>{{ $summary['totals']['active'] }}</strong></td>
            <td>Lewat<br><strong>{{ $summary['totals']['late'] }}</strong></td>
            <td>Selesai<br><strong>{{ $summary['totals']['completed'] }}</strong></td>
            <td>Ditutup tanpa siap<br><strong>{{ $summary['totals']['closed'] }}</strong></td>
        </tr>
    </table>

    @forelse ($summary['groups'] as $group)
        <h2>{{ $group['agency'] }} – {{ $group['name'] }} ({{ count($group['rows']) }} projek)</h2>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 9%">No. Rujukan</th>
                    <th style="width: 22%">Nama Projek</th>
                    <th style="width: 8%">Templat</th>
                    <th style="width: 11%">Tempoh</th>
                    <th class="num" style="width: 6%">Sebenar</th>
                    <th class="num" style="width: 7%">Dirancang</th>
                    <th class="num" style="width: 6%">Varians</th>
                    <th style="width: 11%">Status Jadual</th>
                    <th style="width: 8%">Laporan Terkini</th>
                    <th class="num" style="width: 6%">Laporan Belum Dihantar</th>
                    <th style="width: 6%">Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['rows'] as $row)
                    @php
                        $class = match ($row['indicator']) { 'GREEN' => 'green', 'AMBER' => 'amber', 'RED' => 'red', default => '' };
                    @endphp
                    <tr>
                        <td>{{ $row['reference'] }}</td>
                        <td>{{ $row['title'] }}</td>
                        <td>{{ $row['template'] }}<br><span class="muted">{{ $row['phases'] }}</span></td>
                        <td>{{ $row['start'] }} – {{ $row['end'] }}</td>
                        <td class="num">{{ number_format($row['actual'], 2) }}%</td>
                        <td class="num">{{ $row['planned'] === null ? '-' : number_format($row['planned'], 2) . '%' }}</td>
                        <td class="num">{{ $row['variance'] === null ? '-' : sprintf('%+.2f', $row['variance']) }}</td>
                        <td><span class="badge {{ $class }}">{{ $row['schedule'] }}</span>@if ($row['status'] !== \App\Enums\ProjectStatus::InProgress->label())<br><span class="muted">{{ $row['status'] }}</span>@endif</td>
                        <td>{{ $row['latest_report'] }}</td>
                        <td class="num">{{ $row['missing_reports'] }}</td>
                        <td>{{ $row['awaiting_jpict'] ? 'Menunggu JPICT' : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="muted">Tiada projek aktif dalam pemantauan.</p>
    @endforelse

    <p class="muted" style="margin-top: 10pt">Lampu: hijau = tidak lewat; kuning = lewat kurang daripada 20 mata peratus; merah = lewat 20 mata peratus atau lebih.</p>
</body>
</html>
