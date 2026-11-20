# Laporan Pelaksanaan SP1 – Struktur Database dan Templat Pemberat

**Tarikh:** 20/11/2026 · **Branch:** `feature/sp-status-kemajuan` (dari `e5132e7`, laporan SP0) · **Status:** PASS (ujian automatik). Belum diuji di UAT.

**Rujukan:** SP0 – Audit Struktur dan Analisis Integrasi (`docs/audit/SP0-Audit-Struktur-dan-Analisis-Integrasi.md`), Bahagian F, J (SP1) dan L.

## 1. Skop

SP1 menyediakan asas pangkalan data Modul Status Kemajuan Projek:
- templat pemberat A–D (versi 1);
- tambahan pada jadual `projects` (keputusan I.2: guna semula);
- skop kelayakan Lulus JTICT;
- penyelarasan nama Jenis Perolehan (I.12).

**Tiada skrin baharu.** Tiada perubahan pada jadual `applications`, aliran permohonan atau keputusan JTICT.

## 2. Perubahan

| Fail | Perubahan |
|---|---|
| `database/migrations/2026_11_20_000001_create_progress_templates_tables.php` | **Baharu.** Jadual `progress_templates` (kod, versi, nama, `procurement_type_id`, `allows_phases`, `is_active`, unik kod+versi) dan `progress_template_activities` (urutan, nama, pemberat, `requires_document`, `document_label`, unik templat+urutan). `down()` menolak jika templat telah dipautkan kepada projek. |
| `database/migrations/2026_11_20_000002_add_progress_monitoring_columns_to_projects_table.php` | **Baharu.** Tambahan pada `projects`, butiran dalam §3. |
| `app/Models/ProgressTemplate.php` | **Baharu.** `activeFor()` (versi aktif tertinggi bagi Jenis Perolehan), `isInUse()`, `totalWeight()`, Auditable |
| `app/Models/ProgressTemplateActivity.php` | **Baharu.** Kawalan model: aktiviti templat yang telah digunakan tidak boleh ditambah, diubah atau dipadam (`LogicException`). Auditable. |
| `app/Enums/ProjectStatus.php`, `app/Enums/ProjectSource.php` | **Baharu.** Status kitaran projek, termasuk `HISTORICAL`; sumber `V2` / `V1` |
| `app/Models/Project.php` | Kolum baharu dalam `$fillable` dan cast. Relasi `progressTemplate()`. `isLegacy()`, `isHistorical()`, `isStructureLocked()`. |
| `app/Models/Application.php` | `scopeApprovedByJtict()` dan `isApprovedByJtict()`. Sumber ialah keputusan JTICT terkini dalam `application_decisions`, bukan status `CLOSED`. |
| `database/seeders/ProgressTemplateSeeder.php` | **Baharu.** 4 templat / 31 aktiviti mengikut spesifikasi §4.2–§4.5, dokumen wajib mengikut matriks §5.5, pemetaan melalui kod Jenis Perolehan. Idempoten, dengan semakan jumlah = 100. |
| `database/seeders/DatabaseSeeder.php` | Memanggil `ProgressTemplateSeeder` selepas `ApplicationMasterDataSeeder` |
| `database/seeders/ApplicationMasterDataSeeder.php` | Nama `pembangunan_aplikasi` → "Pembangunan Sistem / Aplikasi" (I.12, untuk pemasangan baharu) |
| `tests/Feature/ApplicationMasterDataIntegrationTest.php` | Jangkaan nama snapshot dikemas kini (I.12) |
| `tests/Feature/ProgressTemplateTest.php` | **Baharu**, 12 ujian (§5) |

## 3. Tambahan pada jadual `projects`

| Kolum / indeks | Tujuan |
|---|---|
| `application_id` nullable + **unik** | Satu permohonan = satu projek. Projek v1 (Bank Projek) tanpa permohonan dibenarkan. |
| `progress_template_id` (FK restrict) | Versi templat dikunci pada projek |
| `is_phased` | Berfasa / Tidak Berfasa (Templat D sahaja; dikawal dalam SP2) |
| `source` (lalai `V2`), `legacy_reference` (unik) | Projek v2 / v1 |
| `status` (lalai `NOT_STARTED`) | Kitaran projek, berasingan daripada peratus |
| `structure_locked_at` | Struktur fasa dikunci selepas tanda siap pertama |
| `planned_start_date`, `planned_end_date` | Jadual keseluruhan |
| `actual_progress_percent` (lalai 0), `planned_progress_percent`, `variance_percent`, `progress_evaluated_at` | Nilai cache. Nilai dirancang dan varians kosong apabila tiada tarikh sasaran. |
| `started_by`, `started_at`, `closed_by`, `closed_at`, `closure_remarks` | Jejak tindakan |
| Indeks (`agency_id`, `status`), (`source`) | Senarai dan papan pemuka |

Kolum lama (`status_indicator`, `physical/financial_progress_percent`, `is_completed`) dikekalkan sehingga SP5.

## 4. Penemuan semasa pelaksanaan

1. **Pembetulan laporan SP0:** `projects.project_manager_id` telah dibuang oleh migration `2026_10_05_000003`. SP0 F.0 tersilap mencadangkan ia dijadikan nullable. Laporan SP0 telah dibetulkan.
2. **Had nama indeks MySQL (64 aksara):** nama lalai indeks unik `progress_template_activities` terlalu panjang. Kini dinamakan `pt_activities_template_sequence_unique`.
   - Ujian menggunakan SQLite, yang tidak mengenakan had ini, jadi masalah hanya dikesan semasa migration pada MySQL.
   - Jadual separa yang tercipta (kosong, migration belum direkodkan) telah dibuang sebelum migration dijalankan semula.
3. **Indeks foreign key MySQL:** MySQL membuang secara automatik indeks asal `projects_agency_id_foreign` dan `projects_application_id_foreign`, kerana indeks baharu boleh menggantikannya. `down()` kini mencipta semula indeks tersebut sebelum membuang indeks baharu.
   - Rollback telah diuji pada MySQL dan memulihkan struktur asal dengan tepat: 16 kolum, nama indeks sama, `application_id` NOT NULL.

## 5. Ujian

`ProgressTemplateTest` (12 ujian):
- **Templat:**
  - 4 templat dan 31 aktiviti, setiap satu berjumlah 100;
  - pemetaan 4 kod Jenis Perolehan;
  - pemetaan kekal selepas nama master ditukar;
  - hanya Templat D `allows_phases`;
  - aktiviti Templat D tepat seperti spesifikasi (nama, pemberat, dokumen wajib; Latihan/TOT tanpa dokumen);
  - seeder idempoten;
  - nama master "Pembangunan Sistem / Aplikasi";
  - templat yang digunakan tidak boleh diubah, dipadam atau ditambah.
- **Kelayakan:**
  - Lulus layak;
  - Tidak Lulus, Kaji Semula, Pra JTICT Ditolak, tiada keputusan, dan Lulus tetapi dipadam tidak layak;
  - salinan yang diluluskan layak.
- **Jadual `projects`:**
  - projek kedua bagi permohonan sama ditolak;
  - banyak projek v1 tanpa permohonan dibenarkan;
  - `legacy_reference` unik;
  - nilai lalai.

**Suite penuh:** 605/605 PASS. Satu amaran *risky* dalam `UserRoleManagementTest`, fail yang tidak disentuh oleh SP1.

## 6. Pangkalan data

- **Pembangunan (`sppictv2_db`):**
  - kedua-dua migration dijalankan, rollback dan migrate semula diuji;
  - `ProgressTemplateSeeder` dijalankan;
  - disahkan 4 templat dan 31 aktiviti, setiap templat berjumlah 100.00, dengan dokumen wajib A=8, B=8, C=5, D=9;
  - nama master "Pembangunan Sistem / Aplikasi" telah ditukar oleh pemilik melalui skrin Master Data;
  - `projects` = 0 rekod; Lulus JTICT = 0.
- **UAT (`sppictv2_uat`):** belum. Apabila kod dibawa ke UAT:
  1. `php artisan migrate`, termasuk migration PP4.5 yang juga belum dijalankan di UAT;
  2. `php artisan db:seed --class=ProgressTemplateSeeder`;
  3. tukar nama Jenis Perolehan melalui skrin Master Data jika belum dibuat.

## 7. Seterusnya: SP2 (Aktiviti dan Jadual Projek)

Keputusan yang diperlukan sebelum SP2 telah dibuat oleh pemilik pada 20/11/2026 (laporan SP0, Bahagian I dan L.6):
- **I.1:** pemantauan bermula selepas Lulus JTICT, dengan penanda "Menunggu JPICT"; rekod JPICT pilihan;
- **I.3:** pemohon menetapkan tarikh; pindaan dibenarkan dengan justifikasi dan sejarah append-only;
- **I.9:** Pengurusan baca sahaja; Superadmin tiada akses;
- **I.11:** Pegawai Utama dan Pegawai Kedua yang aktif boleh mengemas kini.
