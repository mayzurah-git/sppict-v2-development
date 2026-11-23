# Laporan Pelaksanaan PP4.5 – Checkbox SST pada Ringkasan Kos Projek

**Tarikh:** 19/11/2026 · **Branch:** `feature/pp4-sst-checkbox` (dari `feature/pp4-csv-import`, commit `2ab9a74`) · **Status:** PASS (ujian automatik). Belum diuji dalam UAT.

**Rujukan:** Laporan Audit Fasa PP0 – Modul Permohonan Projek, §8 (Reka bentuk checkbox SST 8%).

## 1. Keperluan

Hasil perbincangan pemilik dengan pengguna sistem:
- **Sebelum ini:** SST 8% sentiasa dikira secara automatik.
- **Kini:** SST hanya dikira apabila pemohon menanda kotak **SST** pada kad **Ringkasan Kos Projek**.

## 2. Keputusan lalai

| Perkara | Keputusan |
|---|---|
| Permohonan baharu | Kotak SST **tidak ditanda** (SST = 0) |
| Rekod sedia ada (semua status) | Ditanda (`sst_applicable = true`). Semua rekod lama telah dikenakan SST, jadi nilai tersimpan tidak berubah dan tiada pengiraan semula. |
| Kadar | Tidak berubah: `config('sppict.sst_rate')`, lalai 8%. Pemohon tidak boleh mengubah kadar. |
| Salin Permohonan (PP4.3C) | Status kotak SST disalin daripada permohonan asal |

## 3. Perubahan

| Fail | Perubahan |
|---|---|
| `database/migrations/2026_11_19_000001_add_sst_applicable_to_applications_table.php` | **Baharu.** Lajur `applications.sst_applicable boolean default false`. Semua rekod sedia ada ditetapkan `true`. |
| `app/Models/Application.php` | `sst_applicable` dalam `$fillable` dan cast `boolean` |
| `app/Livewire/Forms/ApplicationForm.php` | `public bool $sst_applicable = false`. `costSummary()` mengira SST hanya jika ditanda dan memulangkan `sst_applicable`. Pengesahan `boolean` dalam `validateStep3()`. Mesej siling disesuaikan. Amaran item "SST" manual hanya muncul jika kotak ditanda. |
| `app/Livewire/ApplicationForm.php` | `mount()` memulihkan status kotak SST daripada rekod |
| `app/Services/ApplicationSubmissionService.php` | Simpan `sst_applicable`. `sst_rate = null` dan `sst_amount = 0` apabila tidak dikenakan. |
| `app/Services/ApplicationCopyService.php` | Salin `sst_applicable` |
| `resources/views/livewire/application-form.blade.php` | Kotak SST pada baris SST dalam kad Ringkasan Kos Projek. Papar "Tidak dikenakan" jika tidak ditanda. |
| `phase-2-financial-details.blade.php` | Petunjuk Kos Siling: "Had maksimum termasuk SST (jika dikenakan)." |
| `phase-3-item-details.blade.php` | Arahan baharu tentang kotak SST |
| `resources/views/livewire/view-application.blade.php` | "SST: Tidak dikenakan" dan "Jumlah Keseluruhan (SST tidak dikenakan)" apabila tidak ditanda |
| `app/Services/ProjectDetailsCsvService.php` | Teks panduan templat CSV: SST dikira jika kotak SST ditanda |

**Semakan Kos Siling:** Jumlah Keseluruhan (termasuk SST hanya jika ditanda) masih tidak boleh melebihi Anggaran Kos Siling.

## 4. Ujian

- `SstCheckboxTest` (baharu, 6 ujian):
  - lalai tidak ditanda;
  - SST dikira apabila ditanda;
  - semakan siling dengan dan tanpa SST;
  - simpan draf tanpa SST (`sst_rate` null, jumlah = jumlah kecil);
  - simpan dengan SST, buka semula dan nyahtanda;
  - amaran item SST manual.
- `ApplicationCostSummaryTest`: helper ujian kini menanda kotak SST, supaya ujian pengiraan SST sedia ada kekal sah.
- Suite penuh: **593/593 PASS**.

## 5. Pangkalan data

- **Pembangunan (`sppict-v2.1`):** migration dijalankan. 9/9 permohonan sedia ada mempunyai `sst_applicable = 1`.
- **UAT (`sppictv2_uat`):** belum dijalankan. Migration perlu dijalankan apabila kod ini dibawa ke UAT.

## 6. Prosedur perubahan kadar SST

Kadar SST **tidak boleh** diubah melalui skrin sistem oleh mana-mana peranan, termasuk Superadmin. Kadar ditetapkan dalam konfigurasi pelayan, jadi perubahan dibuat oleh **pentadbir pelayan atau pembangun sistem** (pasukan teknikal BTM).

**Sumber kadar:**
- `config/sppict.php` membaca `'sst_rate' => (float) env('SST_RATE', 8)`.
- Jika `SST_RATE` tiada dalam `.env`, nilai lalai ialah **8**.

**Langkah (contoh 8% → 6%), pada setiap pelayan berkaitan (produksi, dan UAT jika perlu):**
1. Edit `.env` dan tambah atau ubah baris `SST_RATE=6`.
2. Jalankan `php artisan config:clear`, atau `php artisan config:cache` jika cache konfigurasi digunakan.
3. Semak bahawa kad Ringkasan Kos Projek memaparkan "SST (6%)".

Tiada perubahan kod, migration atau deploy diperlukan.

**Kesan kepada permohonan sedia ada:**

| Status permohonan | Kesan |
|---|---|
| Sudah dihantar (dan status selepasnya) | Tidak berubah. Kadar dan amaun disimpan pada rekod (`sst_rate`, `sst_amount`). |
| Draf / Tidak Lengkap / Ditolak dengan Pindaan | Dikira semula dengan kadar baharu apabila pemohon membuka dan menyimpan semula borang, jika kotak SST ditanda. |
| Salinan (Kaji Semula) | Mula-mula membawa nilai lama, kemudian dikira semula dengan kadar baharu apabila disimpan. |

**Pilihan masa depan:** kadar boleh diurus melalui skrin Superadmin (jadual tetapan, rekod audit, tarikh berkuat kuasa) jika perlu. Ini tidak dilaksanakan kerana perubahan kadar SST jarang berlaku.

## 7. Cadangan ujian UAT

- Permohonan baharu: kotak tidak ditanda, SST "Tidak dikenakan", Jumlah Keseluruhan = Jumlah Kecil.
- Tanda dan nyahtanda kotak: Ringkasan Kos dan Baki Siling berubah serta-merta.
- Simpan Draf, buka semula: status kotak dikekalkan.
- Hantar permohonan tanpa SST: paparan permohonan menunjukkan "SST: Tidak dikenakan".
- Permohonan lama: kotak SST ditanda dan nilai tidak berubah.
