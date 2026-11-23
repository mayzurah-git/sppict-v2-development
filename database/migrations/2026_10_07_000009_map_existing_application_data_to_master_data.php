<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fasa U4: petakan data transaksi sedia ada kepada master data Permohonan Projek.
 *
 * - Isi procurement_type_id, procurement_method_id dan project_category_id berdasarkan
 *   pemetaan table + column (bukan nilai sahaja: "Penyelenggaraan" berbeza maksud).
 * - Lajur teks lama dikekalkan sebagai snapshot; hanya dua label Kategori Projek
 *   dalam application_details.item_category dikemas kini mengikut spesifikasi.
 * - DB::table() digunakan supaya rekod soft-deleted turut diproses dan tiada event
 *   model / audit trail baharu dicetuskan.
 * - Sebarang nilai tidak sepadan atau master data tiada akan menggagalkan migration
 *   dan semua perubahan di-rollback.
 */
return new class extends Migration
{
    private const MAPPINGS = [
        [
            'table' => 'applications',
            'column' => 'procurement_type',
            'foreign_key' => 'procurement_type_id',
            'master' => 'procurement_types',
            'nullable' => true,
            'values' => [
                'Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)' => 'pembekalan_perkhidmatan_one_off',
                'Pembekalan / Perkhidmatan Bermasa' => 'pembekalan_perkhidmatan_bermasa',
                'Penyelenggaraan' => 'penyelenggaraan',
                'Pembangunan Aplikasi' => 'pembangunan_aplikasi',
            ],
        ],
        [
            'table' => 'applications',
            'column' => 'procurement_method',
            'foreign_key' => 'procurement_method_id',
            'master' => 'procurement_methods',
            'nullable' => true,
            'values' => [
                'Sebut Harga' => 'sebut_harga',
                'Tender Terbuka' => 'tender_terbuka',
                'Rundingan Terus' => 'rundingan_terus',
            ],
        ],
        [
            'table' => 'application_details',
            'column' => 'item_category',
            'foreign_key' => 'project_category_id',
            'master' => 'project_categories',
            'nullable' => false,
            'values' => [
                'Projek Baharu' => 'projek_baharu',
                'Peningkatan Sistem' => 'peningkatan_sistem',
                'Peluasan Sistem/Projek' => 'peluasan_sistem_projek',
                'Peluasan Sistem / Projek' => 'peluasan_sistem_projek',
                'Penambahbaikan Peralatan' => 'penambahbaikan_peralatan',
                'Penyelenggaraan' => 'penyelenggaraan_sewaan_langganan',
                'Penyelenggaraan / Sewaan / Langganan' => 'penyelenggaraan_sewaan_langganan',
                'Khidmat Perunding ICT' => 'khidmat_perunding_ict',
            ],
        ],
    ];

    /**
     * Label Kategori Projek yang berubah (application_details.item_category sahaja).
     */
    private const ITEM_CATEGORY_RELABELS = [
        'Peluasan Sistem/Projek' => 'Peluasan Sistem / Projek',
        'Penyelenggaraan' => 'Penyelenggaraan / Sewaan / Langganan',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $before = $this->counts();

            foreach (self::MAPPINGS as $mapping) {
                $this->ensureNoUnmatchedValues($mapping);
            }

            foreach (self::MAPPINGS as $mapping) {
                // Hanya nilai yang wujud dalam data memerlukan master (pangkalan data baharu tiada transaksi).
                $values = array_intersect_key($mapping['values'], array_flip(
                    DB::table($mapping['table'])->distinct()->pluck($mapping['column'])->filter()->all()
                ));
                $masterIds = $this->resolveMasterIds($mapping['master'], array_unique($values));

                foreach ($values as $oldValue => $code) {
                    DB::table($mapping['table'])
                        ->where($mapping['column'], $oldValue)
                        ->whereNull($mapping['foreign_key'])
                        ->update([$mapping['foreign_key'] => $masterIds[$code]]);
                }
            }

            foreach (self::ITEM_CATEGORY_RELABELS as $oldLabel => $newLabel) {
                DB::table('application_details')
                    ->where('item_category', $oldLabel)
                    ->update(['item_category' => $newLabel]);
            }

            foreach (self::MAPPINGS as $mapping) {
                $this->verify($mapping);
            }

            if ($this->counts() !== $before) {
                throw new RuntimeException('Bilangan rekod applications/application_details/audits berubah semasa pemetaan master data.');
            }
        });
    }

    /**
     * Pemetaan data tidak diterbalikkan. Lajur *_id dibuang oleh migration
     * 2026_10_07_000008 jika struktur perlu dikembalikan. Label item_category tidak
     * ditukar semula kerana label lama dan baharu tidak lagi boleh dibezakan dengan
     * selamat (rekod baharu juga menggunakan label spesifikasi).
     */
    public function down(): void
    {
        //
    }

    private function ensureNoUnmatchedValues(array $mapping): void
    {
        $unmatched = DB::table($mapping['table'])
            ->whereNotIn($mapping['column'], array_keys($mapping['values']))
            ->when(
                $mapping['nullable'],
                fn ($query) => $query->whereNotNull($mapping['column'])->where($mapping['column'], '!=', ''),
                fn ($query) => $query->orWhereNull($mapping['column']),
            )
            ->select($mapping['column'], DB::raw('count(*) as total'))
            ->groupBy($mapping['column'])
            ->get();

        if ($unmatched->isNotEmpty()) {
            $list = $unmatched->map(fn ($row) => var_export($row->{$mapping['column']}, true) . " ({$row->total} rekod)")->implode(', ');

            throw new RuntimeException("Nilai {$mapping['table']}.{$mapping['column']} tiada dalam pemetaan master data: {$list}. Migration dihentikan.");
        }
    }

    /**
     * @return array<string, int> code => id
     */
    private function resolveMasterIds(string $masterTable, array $codes): array
    {
        $ids = [];

        foreach ($codes as $code) {
            $rows = DB::table($masterTable)->where('code', $code)->pluck('id');

            if ($rows->count() !== 1) {
                throw new RuntimeException("Master data {$masterTable} dengan code '{$code}' mesti wujud tepat satu rekod (ditemui {$rows->count()}). Jalankan ApplicationMasterDataSeeder dahulu.");
            }

            $ids[$code] = $rows->first();
        }

        return $ids;
    }

    private function verify(array $mapping): void
    {
        $missing = DB::table($mapping['table'])
            ->whereNotNull($mapping['column'])
            ->where($mapping['column'], '!=', '')
            ->whereNull($mapping['foreign_key'])
            ->count();

        if ($missing > 0) {
            throw new RuntimeException("{$missing} rekod {$mapping['table']} mempunyai {$mapping['column']} tetapi {$mapping['foreign_key']} masih NULL.");
        }

        // FK mesti merujuk master yang betul bagi setiap nilai snapshot.
        $mismatched = DB::table($mapping['table'])
            ->join($mapping['master'], "{$mapping['master']}.id", '=', "{$mapping['table']}.{$mapping['foreign_key']}")
            ->where(function ($query) use ($mapping): void {
                foreach ($mapping['values'] as $value => $code) {
                    $query->orWhere(fn ($pair) => $pair
                        ->where("{$mapping['table']}.{$mapping['column']}", $value)
                        ->where("{$mapping['master']}.code", '!=', $code));
                }
            })
            ->count();

        if ($mismatched > 0) {
            throw new RuntimeException("{$mismatched} rekod {$mapping['table']} mempunyai {$mapping['foreign_key']} yang tidak sepadan dengan {$mapping['column']}.");
        }
    }

    private function counts(): array
    {
        return [
            'applications' => DB::table('applications')->count(),
            'application_details' => DB::table('application_details')->count(),
            'audits' => DB::table('audits')->count(),
        ];
    }
};
