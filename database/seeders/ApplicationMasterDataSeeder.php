<?php

namespace Database\Seeders;

use App\Models\ApplicationCategory;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use Illuminate\Database\Seeder;

/**
 * Data selenggara awal Permohonan Projek: Kategori Permohonan, Kategori Projek,
 * Jenis Perolehan dan Kaedah Perolehan. Rekod sedia ada (ikut code) tidak diubah,
 * supaya kemaskini oleh Urus Setia tidak ditimpa apabila seeder dijalankan semula.
 */
class ApplicationMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Code disimpan dalam applications.application_category; jangan ditukar selepas digunakan.
        $this->seed(ApplicationCategory::class, [
            'pembangunan_sistem' => 'Pembangunan Sistem / Aplikasi',
            'perolehan_perkakasan' => 'Perolehan Perkakasan / Komputer',
            'rangkaian_keselamatan_siber' => 'Rangkaian & Keselamatan Siber',
            'penyelenggaraan_sewaan_langganan' => 'Penyelenggaraan, Sewaan dan Langganan',
            'kajian_perunding' => 'Kajian/Khidmat Perunding ICT',
        ]);

        // Susunan menentukan prefix paparan A–F (sort_order 1–6).
        $this->seed(ProjectCategory::class, [
            'projek_baharu' => 'Projek Baharu',
            'peningkatan_sistem' => 'Peningkatan Sistem',
            'peluasan_sistem_projek' => 'Peluasan Sistem / Projek',
            'penambahbaikan_peralatan' => 'Penambahbaikan Peralatan',
            'penyelenggaraan_sewaan_langganan' => 'Penyelenggaraan / Sewaan / Langganan',
            'khidmat_perunding_ict' => 'Khidmat Perunding ICT',
        ]);

        $this->seed(ProcurementType::class, [
            'pembekalan_perkhidmatan_one_off' => 'Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)',
            'pembekalan_perkhidmatan_bermasa' => 'Pembekalan / Perkhidmatan Bermasa',
            'penyelenggaraan' => 'Penyelenggaraan',
            'pembangunan_aplikasi' => 'Pembangunan Aplikasi',
        ]);

        $this->seed(ProcurementMethod::class, [
            'sebut_harga' => 'Sebut Harga',
            'tender_terbuka' => 'Tender Terbuka',
            'rundingan_terus' => 'Rundingan Terus',
        ]);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  array<string, string>  $rows  code => name
     */
    private function seed(string $model, array $rows): void
    {
        $order = 1;

        foreach ($rows as $code => $name) {
            $model::withTrashed()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort_order' => $order, 'is_active' => true],
            );

            $order++;
        }
    }
}
