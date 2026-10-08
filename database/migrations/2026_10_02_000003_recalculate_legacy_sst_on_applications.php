<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pembetulan data sekali sahaja: SST sebelum ini dimasukkan secara manual
 * dalam perincian projek. Kini SST dikira automatik, jadi:
 *  - baris item SST manual dibuang (soft delete, boleh dipulihkan);
 *  - SPPICT/2026/0002 yang kos seunitnya sudah termasuk SST 8% ditukar kepada harga sebelum SST;
 *  - Jumlah Kecil, SST dan Jumlah Keseluruhan dikira semula bagi semua permohonan.
 *
 * Tidak boleh diundur secara automatik; rujuk backup db-backup-sebelum-sst-20261002.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rate = (float) config('sppict.sst_rate', 8);
        $now = now();

        DB::transaction(function () use ($rate, $now) {
            // 1. Buang baris item SST manual (cth: "SST", "SST 8%")
            $sstLineIds = DB::table('application_details')
                ->whereNull('deleted_at')
                ->get(['id', 'technical_specifications'])
                ->filter(fn ($detail) => preg_match('/^SST(\s*[0-9.]+\s*%)?$/i', trim($detail->technical_specifications)))
                ->pluck('id');

            DB::table('application_details')
                ->whereIn('id', $sstLineIds)
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            // 2. SPPICT/2026/0002: kos seunit sudah termasuk SST 8%
            $applicationId = DB::table('applications')
                ->where('reference_number', 'SPPICT/2026/0002')
                ->value('id');

            if ($applicationId) {
                $details = DB::table('application_details')
                    ->where('application_id', $applicationId)
                    ->whereNull('deleted_at')
                    ->get(['id', 'unit_quantity', 'unit_cost']);

                foreach ($details as $detail) {
                    $unitCost = round($detail->unit_cost / 1.08, 2);

                    DB::table('application_details')->where('id', $detail->id)->update([
                        'unit_cost' => $unitCost,
                        'total_cost' => round($unitCost * $detail->unit_quantity, 2),
                        'updated_at' => $now,
                    ]);
                }
            }

            // 3. Kira semula ringkasan kos bagi semua permohonan
            foreach (DB::table('applications')->pluck('id') as $id) {
                $subtotalSen = (int) round(DB::table('application_details')
                    ->where('application_id', $id)
                    ->whereNull('deleted_at')
                    ->sum('total_cost') * 100);
                $sstSen = (int) round($subtotalSen * $rate / 100);

                DB::table('applications')->where('id', $id)->update([
                    'subtotal_cost' => $subtotalSen / 100,
                    'sst_rate' => $rate,
                    'sst_amount' => $sstSen / 100,
                    'total_cost' => ($subtotalSen + $sstSen) / 100,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Pembetulan data tidak boleh diundur secara automatik.
    }
};
