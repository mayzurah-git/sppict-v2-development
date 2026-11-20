<?php

namespace Database\Seeders;

use App\Models\ProcurementType;
use App\Models\ProgressTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * SP1: Templat pemberat kemajuan projek A–D, versi 1 (spesifikasi SP §4.2–§4.5, audit SP0 E.2).
 *
 * Idempoten: templat sedia ada (kod + versi) tidak diubah walaupun seeder dijalankan semula, kerana
 * templat yang telah digunakan tidak boleh diubah. Perubahan pemberat = versi baharu.
 * Memerlukan master Jenis Perolehan (ApplicationMasterDataSeeder); pemetaan melalui kod, bukan nama.
 * Dokumen wajib mengikut matriks spesifikasi SP §5.5.
 */
class ProgressTemplateSeeder extends Seeder
{
    public const VERSION = 1;

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::templates() as $code => $template) {
                $this->seedTemplate($code, $template);
            }
        });
    }

    /**
     * @return array<string, array{procurement_type: string, name: string, allows_phases: bool, activities: array<int, array{0: string, 1: int|float, 2: ?string}>}>
     *         aktiviti: [nama, pemberat, label dokumen wajib atau null]
     */
    public static function templates(): array
    {
        $tenderDoc = 'Dokumen tender, sebut harga atau kelulusan';
        $procurementDoc = 'Dokumen keputusan atau rekod proses perolehan';
        $sstDoc = 'Surat Setuju Terima';
        $kickoffDoc = 'Minit mesyuarat atau rekod kickoff';
        $contractDoc = 'Dokumen kontrak ditandatangani';
        $deliveryDoc = 'Nota penghantaran atau dokumen penerimaan';
        $uatDoc = 'Laporan UAT / PAT';
        $fatDoc = 'Laporan FAT';
        $completionDoc = 'Dokumen Akuan Siap Kerja atau serahan';

        return [
            'A' => [
                'procurement_type' => 'pembekalan_perkhidmatan_one_off',
                'name' => 'Pembekalan / Perkhidmatan Tidak Bermasa',
                'allows_phases' => false,
                'activities' => [
                    ['Penyediaan Dokumen Tender / Sebut Harga / Kelulusan', 5, $tenderDoc],
                    ['Proses Perolehan', 10, $procurementDoc],
                    ['Surat Setuju Terima (SST)', 5, $sstDoc],
                    ['Mesyuarat Kickoff', 10, $kickoffDoc],
                    ['Penghantaran', 30, $deliveryDoc],
                    ['User Acceptance Test (UAT)', 20, $uatDoc],
                    ['Final Acceptance Test (FAT)', 15, $fatDoc],
                    ['Akuan Siap Kerja', 5, $completionDoc],
                ],
            ],
            'B' => [
                'procurement_type' => 'pembekalan_perkhidmatan_bermasa',
                'name' => 'Pembekalan / Perkhidmatan Bermasa',
                'allows_phases' => false,
                'activities' => [
                    ['Penyediaan Dokumen Tender / Sebut Harga', 5, $tenderDoc],
                    ['Proses Perolehan', 10, $procurementDoc],
                    ['Surat Setuju Terima (SST)', 5, $sstDoc],
                    ['Mesyuarat Kickoff', 10, $kickoffDoc],
                    ['Dokumen Kontrak', 15, $contractDoc],
                    ['Penghantaran', 30, $deliveryDoc],
                    ['User Acceptance Test (UAT)', 15, $uatDoc],
                    ['Final Acceptance Test (FAT)', 10, $fatDoc],
                ],
            ],
            'C' => [
                'procurement_type' => 'penyelenggaraan',
                'name' => 'Penyelenggaraan',
                'allows_phases' => false,
                'activities' => [
                    ['Penyediaan Dokumen Tender / Sebut Harga', 25, $tenderDoc],
                    ['Proses Perolehan', 25, $procurementDoc],
                    ['Surat Setuju Terima (SST)', 5, $sstDoc],
                    ['Mesyuarat Kickoff', 10, $kickoffDoc],
                    ['Dokumen Kontrak', 35, $contractDoc],
                ],
            ],
            'D' => [
                'procurement_type' => 'pembangunan_aplikasi',
                'name' => 'Pembangunan Sistem / Aplikasi',
                'allows_phases' => true,
                'activities' => [
                    ['Penyediaan Dokumen Tender / Sebut Harga / Kelulusan', 5, $tenderDoc],
                    ['Proses Perolehan', 10, $procurementDoc],
                    ['Surat Setuju Terima (SST)', 5, $sstDoc],
                    ['Mesyuarat Kickoff', 5, $kickoffDoc],
                    ['Dokumen Kontrak Perjanjian', 5, $contractDoc],
                    ['Pembangunan Aplikasi / Penghantaran', 30, $deliveryDoc],
                    ['User Acceptance Test (UAT) / Provisional Acceptance Test (PAT)', 20, $uatDoc],
                    ['Final Acceptance Test (FAT)', 10, $fatDoc],
                    ['Latihan / TOT', 5, null],
                    ['Akuan Siap Kerja / Serahan', 5, $completionDoc],
                ],
            ],
        ];
    }

    private function seedTemplate(string $code, array $template): void
    {
        if (ProgressTemplate::withTrashed()->where('code', $code)->where('version', self::VERSION)->exists()) {
            return;
        }

        $total = array_sum(array_column($template['activities'], 1));
        if (abs($total - ProgressTemplate::TOTAL_WEIGHT) > 0.001) {
            throw new RuntimeException("Jumlah pemberat Templat {$code} ialah {$total}, bukan 100.");
        }

        $procurementTypeId = ProcurementType::withTrashed()->where('code', $template['procurement_type'])->value('id');
        if ($procurementTypeId === null) {
            throw new RuntimeException("Jenis Perolehan '{$template['procurement_type']}' tiada. Jalankan ApplicationMasterDataSeeder dahulu.");
        }

        $progressTemplate = ProgressTemplate::create([
            'code' => $code,
            'version' => self::VERSION,
            'name' => $template['name'],
            'procurement_type_id' => $procurementTypeId,
            'allows_phases' => $template['allows_phases'],
            'is_active' => true,
        ]);

        foreach ($template['activities'] as $index => [$name, $weight, $documentLabel]) {
            $progressTemplate->activities()->create([
                'sequence' => $index + 1,
                'name' => $name,
                'weight' => $weight,
                'requires_document' => $documentLabel !== null,
                'document_label' => $documentLabel,
            ]);
        }
    }
}
