# Laporan Pelaksanaan SP4 – Pengiraan, Validasi dan Penutupan

**Tarikh:** 23/11/2026 · **Branch:** `feature/sp-status-kemajuan` (dari `485283c`, SP3) · **Status:** PASS (ujian automatik). Belum diuji secara manual atau di UAT.

**Rujukan:** SP0 – Audit Struktur dan Analisis Integrasi, Bahagian J (SP4), L.2, L.3 dan L.8 (keputusan I.5 dan I.6, termasuk butiran penutupan yang disahkan pemilik pada 23/11/2026).

## 1. Skop

**A. Pengiraan kemajuan (L.2, L.3, I.5)**
- Service pengiraan tulen: kemajuan sebenar, dirancang dan varians bagi setiap fasa dan bagi projek.
- Status jadual Mendahului / Mengikut Jadual / Lewat, dengan lampu Hijau / Kuning / Merah.
- Paparan langsung pada Maklumat Projek; nilai cache untuk senarai dan papan pemuka; arahan harian.
- Peratus laporan bulanan dikira daripada snapshot semasa laporan dihantar.

**B. Penutupan projek (I.6)**
- Mohon penutupan, tarik balik, sahkan atau tolak oleh Urus Setia.
- Tutup projek tanpa siap oleh Urus Setia.
- Peraturan khas bagi projek Penyelenggaraan.
- Sejarah penutupan yang tidak boleh diubah atau dipadam (append-only).

**Di luar skop SP4:** papan pemuka Urus Setia dan Pengurusan, kemas kini papan pemuka pemohon, eksport, dan persaraan `Project::updateStatusIndicator()` (SP5).

## 2. Perubahan

| Fail | Perubahan |
|---|---|
| `app/Services/ProgressCalculator.php` | **Baharu.** `compute()` (tulen, tiada akses pangkalan data), `forProject()`, `forReport()`, `refresh()` (nilai cache) |
| `app/Support/Progress/ProgressResult.php` | **Baharu.** Hasil pengiraan: sebenar, dirancang, varians, status, lampu, dan nilai setiap fasa |
| `app/Enums/ScheduleStatus.php` | **Baharu.** Status jadual dan ambang lampu (I.5) |
| `app/Console/Commands/RefreshProjectProgress.php`, `routes/console.php` | **Baharu.** `sppict:refresh-project-progress`, dijadualkan setiap hari 12:30 pagi |
| `app/Services/ProjectMonitoringService.php`, `ProjectProgressService.php` | Nilai cache dikemas kini selepas Mulakan Pemantauan, Pinda Struktur, Pinda Jadual, tanda siap dan batal tanda |
| `app/Services/ProjectReportService.php` | Peratus laporan dikira daripada snapshot semasa laporan dihantar. Semakan kebenaran ditukar kepada `manageReports`. |
| `database/migrations/2026_11_23_000003_create_project_closure_events_table.php` | **Baharu.** `project_closure_events` (append-only) |
| `app/Models/ProjectClosureEvent.php`, `app/Enums/ClosureEventType.php` | **Baharu** |
| `app/Services/ProjectClosureService.php` | **Baharu.** `closureBlocker()`, `missingReportCount()`, `request()`, `withdraw()`, `approve()`, `reject()`, `terminate()` |
| `app/Policies/ProjectPolicy.php` | `manage` disekat semasa Menunggu Pengesahan Penutupan. Baharu: `manageReports`, `requestClosure`, `withdrawClosure`, `decideClosure`, `terminate`. |
| `app/Models/Project.php` | Relasi `closureEvents()`, `closer()`; `isMaintenance()` |
| `app/Livewire/Projects/ShowProject.php` + paparan | Kad Kemajuan Projek (dikira langsung), nilai setiap fasa, bahagian dan modal Penutupan Projek, mesej "100% – Dalam Pelaksanaan Kontrak" |
| `resources/views/livewire/projects/project-list.blade.php` | Lajur Sebenar, Dirancang dan Jadual |
| `resources/views/livewire/projects/progress-report-page.blade.php` | Peratus laporan (sebenar, dirancang, varians, status) |
| `tests/Unit/ProgressCalculatorTest.php` | **Baharu**, 9 ujian |
| `tests/Feature/ProjectClosureTest.php` | **Baharu**, 9 ujian |
| `tests/Feature/ProjectProgressTest.php` | +1 ujian cache. Jangkaan peratus laporan dikemas kini. |

## 3. Pengiraan

| Ukuran | Formula (bagi setiap fasa) |
|---|---|
| Sebenar | Σ pemberat aktiviti siap pada atau sebelum tarikh penilaian ÷ Σ pemberat aktiviti fasa × 100 |
| Dirancang | Σ pemberat aktiviti dengan tarikh sasaran ≤ tarikh penilaian ÷ Σ pemberat aktiviti fasa × 100 |
| Projek | Purata sama rata semua fasa (sebenar dan dirancang). Projek Tidak Berfasa sama dengan fasa tunggalnya. |
| Varians | Sebenar − Dirancang. Dikira daripada nilai yang telah dibundarkan, supaya angka yang dipaparkan konsisten. |
| Status | Varians > 0: Mendahului Jadual. = 0: Mengikut Jadual. < 0: Lewat. |
| Lampu | Hijau: varians ≥ 0. Kuning: −0.01 hingga −19.99. Merah: ≤ −20. |

**Butiran pengiraan:**
- Aritmetik dibuat dalam unit 0.01 pemberat (integer), kemudian dibundarkan kepada 2 tempat perpuluhan.
- Purata projek dikira daripada nilai fasa yang belum dibundarkan.
- **Tiada data rancangan:** jika mana-mana aktiviti dalam sesuatu fasa tiada tarikh sasaran, nilai dirancang, varians, status dan lampu dibiarkan kosong (contohnya rekod sejarah v1).
- **Masa tidak menambah kemajuan sebenar.** Hanya kemajuan dirancang meningkat apabila tarikh sasaran berlalu.

**Di mana nilai digunakan:**
- **Maklumat Projek:** dikira terus pada hari ini.
- **Senarai Projek:** menggunakan nilai cache.
- **Laporan bulanan:** dikira daripada snapshot, dan tidak berubah selepas laporan dihantar.
- **Papan pemuka pemohon sedia ada:** `physical_progress_percent` dan `status_indicator` diselaraskan dengan nilai baharu sehingga papan pemuka dikemas kini dalam SP5. Jika tiada nilai dirancang, `status_indicator` tidak diubah.

## 4. Penutupan projek

| Langkah | Siapa | Syarat dan kesan |
|---|---|---|
| Mohon Penutupan | Pegawai Utama / Kedua aktif | Status Dalam Pelaksanaan dan semua aktiviti siap (100%). Penyelenggaraan: juga selepas Tarikh Tamat Projek (tarikh tamat kontrak). Tarikh siap projek diambil daripada tarikh siap aktiviti terakhir. Catatan pilihan. Status menjadi **Menunggu Pengesahan Penutupan**. |
| Semasa menunggu | — | Aktiviti, dokumen aktiviti, jadual dan struktur tidak boleh dikemas kini. **Laporan bulanan masih boleh dicipta dan dihantar.** Urus Setia nampak bilangan laporan bulanan yang belum dihantar. |
| Tarik Balik | Pegawai aktif | Status kembali Dalam Pelaksanaan. Catatan pilihan. |
| Sahkan | Urus Setia | Status menjadi **Selesai**. `closed_by` dan `closed_at` direkodkan. Projek menjadi baca sahaja untuk semua pengguna. |
| Tolak | Urus Setia | Ulasan wajib. Status kembali Dalam Pelaksanaan. |
| Tutup Tanpa Siap | Urus Setia | Bagi projek Dalam Pelaksanaan atau Menunggu Pengesahan. Ulasan wajib. Status menjadi **Ditutup**, baca sahaja. Tidak terpakai bagi rekod v1. |
| Buka semula | — | Tidak dibenarkan |

Setiap langkah direkodkan dalam `project_closure_events` (append-only) dan dipaparkan dalam bahagian Penutupan Projek. Projek Penyelenggaraan yang telah mencapai 100% sebelum tarikh tamat kontrak memaparkan "100% – Dalam Pelaksanaan Kontrak".

## 5. Ujian

**`ProgressCalculatorTest` (9 ujian unit):**
- contoh L.2 (Templat A, 15 Mac: sebenar 20, dirancang 30, varians −10, Lewat, Kuning);
- contoh L.3 (dua fasa 60% dan 50%, projek 55%; fasa berjumlah 60 dikira berkadar);
- projek satu fasa sama dengan nilai fasanya;
- sempadan tarikh sasaran (sebelum, pada, dan selepas tarikh sasaran);
- aktiviti yang siap selepas tarikh penilaian tidak dikira;
- masa berlalu tidak menambah kemajuan sebenar;
- tiada tarikh sasaran bermakna tiada nilai dirancang;
- pembundaran, dan purata daripada nilai yang belum dibundarkan;
- ambang status dan lampu (0, −0.01, −19.99, −20);
- struktur kosong.

**`ProjectProgressTest` (ujian cache):**
- nilai cache dikemas kini selepas Mulakan Pemantauan, tanda siap, batal tanda dan Pinda Jadual;
- arahan harian mengemas kini nilai dirancang selepas masa berlalu;
- Maklumat Projek dan Senarai Projek memaparkan nilai yang sama.

**`ProjectClosureTest` (9 ujian):**
- penutupan hanya boleh dimohon apabila 100%; tarikh siap projek diambil daripada aktiviti terakhir;
- rakan seagensi, Urus Setia dan Pengurusan tidak boleh memohon;
- semasa menunggu: aktiviti dan jadual dikunci, tetapi laporan bulanan boleh dihantar;
- tarik balik; tolak dengan ulasan wajib; pegawai dan Pengurusan tidak boleh membuat keputusan;
- sahkan menjadikan projek Selesai dan baca sahaja, tanpa buka semula; sejarah append-only;
- tutup tanpa siap (ulasan wajib; Urus Setia sahaja; bukan rekod v1), termasuk daripada status menunggu;
- Penyelenggaraan hanya boleh dimohon tutup selepas tarikh tamat kontrak;
- kiraan laporan bulanan yang belum dihantar;
- aliran skrin penuh: pegawai memohon, Urus Setia menolak tanpa ulasan (ditolak) dan kemudian mengesahkan; Pengurusan mendapat 403.

**Suite penuh:** 658/658 PASS. Satu amaran *risky* dalam `UserRoleManagementTest`, sama seperti sebelum ini.

**Penemuan semasa pembangunan:** arahan Blade sebaris `@php(...)` yang diletakkan sebelum blok `@php ... @endphp` dalam fail yang sama dihurai bersama oleh Blade, dan ini menyebabkan ralat sintaks. Semua arahan sebaris dalam paparan projek telah ditukar kepada bentuk blok.

## 6. Pangkalan data

- **Pembangunan (`sppictv2_db`):**
  - migration `project_closure_events` dijalankan pada MySQL, dan rollback serta migrate semula diuji;
  - `sppict:refresh-project-progress` dijalankan; 0 projek dikemas kini kerana belum ada projek.
- **UAT (`sppictv2_uat`):** belum dijalankan. Langkah apabila kod dibawa ke UAT:
  1. `php artisan migrate`. Ini merangkumi PP4.5 dan SP1–SP4.
  2. `php artisan db:seed --class=ProgressTemplateSeeder`.
  3. Pastikan `php artisan schedule:run` berjalan setiap minit pada pelayan. Ini sudah diperlukan untuk penyahaktifan akaun dorman.

## 7. Ujian manual dicadangkan (UAT)

1. Tanda siap beberapa aktiviti, kemudian semak kad Kemajuan Projek dan lajur dalam Senarai Projek.
2. Pinda tarikh sasaran ke masa lalu atau masa hadapan, kemudian semak perubahan nilai dirancang dan lampu.
3. Projek Berfasa: semak nilai setiap fasa dan purata projek.
4. Hantar laporan bulanan, kemudian semak peratus laporan. Kemas kini aktiviti selepas itu; peratus laporan sepatutnya tidak berubah.
5. Tanda semua aktiviti siap, kemudian klik **Mohon Penutupan**. Semak bahawa aktiviti tidak lagi boleh dikemas kini.
6. Sebagai Urus Setia: cuba **Tolak** tanpa ulasan (sepatutnya ditolak), kemudian **Sahkan Penutupan**. Semak bahawa projek menjadi Selesai dan baca sahaja.
7. Sebagai Urus Setia: **Tutup Projek (Tanpa Siap)** bagi projek lain.
8. Projek Penyelenggaraan: capai 100% sebelum tarikh tamat. Semak mesej "Dalam Pelaksanaan Kontrak" dan bahawa butang Mohon Penutupan tidak dipaparkan.

## 8. Seterusnya: SP5 (Dashboard dan Laporan)

Skop dalam SP0 Bahagian J:
- kemas kini papan pemuka pemohon kepada data baharu;
- papan pemuka Urus Setia dan Pengurusan (status, varians, laporan tertunggak);
- eksport jika diluluskan;
- persaraan `Project::updateStatusIndicator()`.

Keputusan berkaitan (I.5, I.9, I.10) telah dibuat.

Perkara yang perlu disahkan sebelum SP5:
- sama ada eksport (Excel / PDF) diperlukan dalam SP5;
- kandungan utama papan pemuka Pengurusan.
