# Laporan Pelaksanaan SP2 – Aktiviti dan Jadual Projek

**Tarikh:** 20/11/2026 · **Branch:** `feature/sp-status-kemajuan` (dari `79a611b`, SP1) · **Status:** PASS (ujian automatik). Belum diuji secara manual atau di UAT.

**Rujukan:** SP0 – Audit Struktur dan Analisis Integrasi, Bahagian F.2A, F.3, G, J (SP2) dan L.1–L.6.

## 1. Skop

SP2 membolehkan pegawai projek memulakan pemantauan dan menetapkan struktur serta jadual projek:
- **Mulakan Pemantauan** bagi permohonan Lulus JTICT (I.1);
- pilihan Berfasa / Tidak Berfasa (Templat D sahaja), bilangan fasa dan aktiviti bagi setiap fasa (L.3);
- tarikh mula dan tamat projek, serta satu tarikh sasaran bagi setiap aktiviti (I.3, I.7);
- **Pinda Struktur** sebelum struktur dikunci, dan **Buka Kunci Struktur** oleh Urus Setia (L.3);
- **Pinda Jadual** dengan justifikasi wajib dan sejarah append-only (I.3);
- penanda **Menunggu JPICT** dan rekod kelulusan JPICT (pilihan) oleh Urus Setia (I.1);
- `ProjectPolicy` mengikut I.9 dan I.11;
- skrin Senarai Projek dan Maklumat Projek, menu, dan pautan dari halaman permohonan.

**Di luar skop SP2:**
- tanda siap aktiviti, dokumen dan laporan bulanan (SP3);
- pengiraan peratus sebenar / dirancang / varians (SP4). Skrin SP2 belum memaparkan peratus kemajuan.

Tiada perubahan pada jadual `applications`, aliran permohonan atau keputusan JTICT.

## 2. Perubahan

| Fail | Perubahan |
|---|---|
| `database/migrations/2026_11_20_000003_create_project_phases_and_activities_tables.php` | **Baharu.** `project_phases` (unik projek + nombor fasa) dan `project_progress_activities` (snapshot templat, `target_date`, kolum tanda siap untuk SP3, unik fasa + aktiviti templat). Nama indeks/FK panjang dinamakan secara eksplisit. |
| `database/migrations/2026_11_20_000004_create_project_schedule_history_and_jpict_columns.php` | **Baharu.** Kolum `projects.jpict_approved_at`, `jpict_reference`, `jpict_recorded_by`, `jpict_recorded_at`. Jadual `project_schedule_changes` dan `project_structure_unlocks` (append-only). |
| `app/Models/ProjectPhase.php`, `ProjectProgressActivity.php` | **Baharu.** Auditable, SoftDeletes, UUID. `ProjectProgressActivity::statusOn()` (Selesai / Lewat / Belum Siap, diterbitkan). |
| `app/Models/ProjectScheduleChange.php`, `ProjectStructureUnlock.php` | **Baharu.** Append-only: `LogicException` pada kemas kini dan padam (corak `ApplicationDecision`). |
| `app/Enums/ProjectActivityStatus.php`, `ScheduleField.php` | **Baharu** |
| `app/Models/Project.php` | Relasi fasa, aktiviti, sejarah. `isReadOnly()`, `totalCost()`, `requiresJpict()`, `isAwaitingJpict()`. Pemalar `JPICT_THRESHOLD` (500,000) dan `MAX_PHASES` (10). |
| `app/Policies/ProjectPolicy.php` | **Baharu.** `viewAny`, `view`, `start`, `manage`, `updateStructure`, `unlockStructure`, `recordJpict` (§3). |
| `app/Services/ProjectMonitoringService.php` | **Baharu.** `start()`, `updateStructure()`, `amendSchedule()`, `unlockStructure()`, `recordJpict()`. Semua peraturan dan kebenaran disemak semula dalam transaksi dengan kunci baris (`lockForUpdate`). |
| `app/Livewire/Projects/ProjectList.php`, `ProjectSetup.php`, `ShowProject.php` + paparan | **Baharu.** Senarai Projek, Mulakan Pemantauan / Pinda Struktur, Maklumat Projek (modal Pinda Jadual, Buka Kunci Struktur, Rekod JPICT). |
| `routes/web.php` | `/projek`, `/projek/mula/{uuid permohonan}`, `/projek/{uuid}`, `/projek/{uuid}/struktur` (`not-role:superadmin`) |
| `resources/views/livewire/layout/navigation.blade.php` | Pengguna biasa: "Kemajuan Projek" kini ke Senarai Projek. Menu "Projek" untuk Urus Setia dan Pengurusan. |
| `resources/views/livewire/view-application.blade.php` | Panel "Mulakan Pemantauan" / "Lihat Pemantauan Projek" apabila keputusan JTICT terkini Lulus |
| `tests/Feature/ProjectMonitoringTest.php` | **Baharu**, 20 ujian (§5) |

## 3. Akses (`ProjectPolicy`)

| Tindakan | Dibenarkan |
|---|---|
| Lihat senarai / maklumat projek | Pengguna dalam agensi projek, pegawai aktif, Urus Setia dan Pengurusan (baca sahaja). **Superadmin ditolak** (I.9). Agensi diambil daripada `projects.agency_id`, supaya projek v1 tanpa permohonan turut dikawal. |
| Mulakan Pemantauan | Pegawai Utama / Kedua aktif dalam agensi permohonan. Permohonan tidak dipadam, keputusan JTICT terkini Lulus, dan belum ada projek (termasuk projek dipadam). |
| Pinda Jadual | Pegawai Utama / Kedua aktif dalam agensi projek (I.11). Bukan projek baca sahaja (Rekod Sejarah, Selesai, Ditutup). |
| Pinda Struktur | Seperti Pinda Jadual, dan struktur belum dikunci |
| Buka Kunci Struktur | Urus Setia sahaja, dengan catatan wajib |
| Rekod JPICT | Urus Setia sahaja, bagi projek v2 ≥ RM500,000 |

Urus Setia dan Pengurusan tidak boleh memulakan pemantauan atau meminda jadual, walaupun mereka boleh melihat semua projek.

## 4. Peraturan yang dilaksanakan

1. **Satu permohonan = satu projek.** Kunci baris permohonan, semakan dalam transaksi dan indeks unik `application_id` (SP1).
2. **Templat:** dipilih melalui `procurement_type_id`. Jika tiada templat aktif, pemantauan disekat dengan mesej "Sila hubungi Urus Setia".
3. **Tidak Berfasa:** satu fasa mengandungi semua aktiviti templat (jumlah 100).
4. **Berfasa (Templat D sahaja):**
   - 2 hingga 10 fasa;
   - setiap fasa mempunyai sekurang-kurangnya satu aktiviti;
   - aktiviti yang sama boleh dipilih dalam fasa berlainan, tetapi hanya sekali dalam satu fasa;
   - nama fasa pilihan;
   - jumlah pemberat fasa selain 100 dipaparkan dengan nota "dikira secara berkadar" (pengiraan dalam SP4).
5. **Snapshot pemberat:** nama, pemberat dan keperluan dokumen disalin daripada templat semasa projek dimulakan.
6. **Tarikh:**
   - tarikh mula dan tamat projek wajib, dan tarikh tamat tidak boleh lebih awal daripada tarikh mula;
   - setiap aktiviti wajib mempunyai tarikh sasaran yang sah dan **dalam tempoh projek**.
7. **Pinda Struktur (sebelum dikunci):**
   - tarikh sasaran aktiviti sedia ada **tidak diubah** di sini; pindaan tarikh dibuat melalui Pinda Jadual dengan justifikasi;
   - aktiviti baharu wajib diberi tarikh sasaran;
   - fasa atau aktiviti yang dikeluarkan di-soft delete, dan dipulihkan jika dipilih semula (tiada rekod berganda).
8. **Kunci struktur:**
   - `structure_locked_at` diisi oleh SP3 apabila aktiviti pertama ditanda siap;
   - selepas Urus Setia membuka kunci, aktiviti yang telah ditanda siap tidak boleh dikeluarkan;
   - struktur dikunci semula sebaik sahaja disimpan jika masih ada aktiviti siap.
9. **Pinda Jadual:**
   - justifikasi wajib (maksimum 2000 aksara);
   - setiap tarikh yang berubah direkodkan sebagai satu baris dalam `project_schedule_changes` (tarikh lama, tarikh baharu, pengguna, masa), dikumpulkan mengikut pindaan;
   - pindaan tanpa sebarang perubahan ditolak;
   - **penetapan pertama semasa Mulakan Pemantauan tidak direkodkan sebagai pindaan**. Nilai asalnya kekal sebagai "tarikh lama" dalam pindaan pertama.
10. **JPICT:**
    - penanda "Menunggu JPICT" dipaparkan bagi projek v2 yang `total_cost` ≥ RM500,000 (atau `estimated_cost` bagi permohonan lama tanpa jumlah keseluruhan) dan tiada tarikh kelulusan JPICT;
    - penanda tidak menyekat pemantauan;
    - tarikh kelulusan tidak boleh melebihi hari ini;
    - rekod boleh dikemas kini, dan perubahan direkodkan dalam audit trail projek.
11. **Status projek:** menjadi "Dalam Pelaksanaan" apabila pemantauan dimulakan.

## 5. Ujian

`ProjectMonitoringTest` (20 ujian):
- **Mulakan:**
  - Pegawai Utama memulakan projek Tidak Berfasa: 1 fasa, 10 aktiviti, snapshot sepadan dengan templat, status, jejak, tiada rekod pindaan;
  - Pegawai Kedua boleh memulakan;
  - mula kali kedua ditolak (tiada projek berganda);
  - ditolak bagi rakan seagensi bukan pegawai, pegawai tamat tugasan, agensi lain, Urus Setia, Pengurusan, Superadmin, dan pegawai yang bertukar agensi;
  - ditolak bagi keputusan Tidak Lulus, Kaji Semula, dan permohonan dipadam;
  - Jenis Perolehan tanpa templat aktif disekat.
- **Berfasa:**
  - ditolak bagi Templat A;
  - Templat D dengan 2 fasa: aktiviti sama dalam dua fasa, jumlah 100 dan 60;
  - 1 fasa atau 11 fasa ditolak; fasa kosong ditolak; aktiviti luar templat ditolak.
- **Tarikh:** tarikh sasaran kosong, di luar tempoh projek, atau tidak sah (30/02) ditolak; tarikh tamat sebelum tarikh mula ditolak.
- **Struktur:**
  - pindaan sebelum dikunci mengekalkan tarikh sedia ada;
  - kembali kepada Tidak Berfasa menggunakan soft delete;
  - fasa yang dipilih semula dipulihkan.
- **Kunci:**
  - struktur yang dikunci tidak boleh dipinda;
  - pegawai, Pengurusan dan Superadmin tidak boleh membuka kunci;
  - Urus Setia membuka kunci dengan catatan wajib, dan rekod dicipta;
  - aktiviti siap tidak boleh dikeluarkan; struktur dikunci semula;
  - sejarah buka kunci append-only.
- **Pinda Jadual:**
  - justifikasi wajib; tiada perubahan ditolak;
  - 2 perubahan dalam satu pindaan direkodkan (nilai lama dan baharu);
  - pindaan kedua tidak menimpa sejarah;
  - sejarah append-only;
  - tarikh sasaran di luar tempoh baharu ditolak;
  - hanya pegawai aktif; pegawai yang diganti atau projek Ditutup ditolak.
- **Akses HTTP:**
  - agensi lain mendapat 403 melalui UUID;
  - Superadmin mendapat 403;
  - Urus Setia dan Pengurusan baca sahaja (tiada butang Pinda Jadual);
  - halaman struktur untuk pegawai aktif sahaja;
  - projek v1 menggunakan agensi projek.
- **JPICT:**
  - penanda bagi ≥ RM500,000;
  - hanya Urus Setia boleh merekod; tarikh masa hadapan ditolak; penanda hilang selepas direkod;
  - RM499,999.99 tiada penanda.
- **Skrin Livewire:**
  - aliran penuh Mulakan Pemantauan dari halaman permohonan dan senarai;
  - Pinda Jadual melalui modal (justifikasi wajib);
  - borang Berfasa memaparkan ralat service.

**Suite penuh:** 625/625 PASS. Satu amaran *risky* dalam `UserRoleManagementTest`, sama seperti SP1.

**Had ujian:** SQLite tidak menyokong kunci baris sebenar, jadi klik berganda diuji secara berurutan.

## 6. Pangkalan data

- **Pembangunan (`sppictv2_db`):**
  - kedua-dua migration dijalankan pada MySQL;
  - rollback dan migrate semula diuji: nama indeks dan FK sah dalam had 64 aksara;
  - tiada data diubah; `projects` = 0, Lulus JTICT = 0.
- **UAT (`sppictv2_uat`):** belum dijalankan. Apabila kod dibawa ke UAT:
  1. `php artisan migrate`. Ini merangkumi PP4.5, SP1 dan SP2.
  2. `php artisan db:seed --class=ProgressTemplateSeeder`.
  3. Tukar nama Jenis Perolehan melalui skrin Master Data jika belum dibuat.

## 7. Ujian manual dicadangkan (UAT)

Pembangunan tiada permohonan Lulus JTICT, jadi ujian manual memerlukan UAT.
1. **Pegawai Utama** permohonan Lulus JTICT:
   - buka halaman permohonan dan klik **Mulakan Pemantauan**;
   - isi tarikh projek dan tarikh sasaran;
   - semak Maklumat Projek.
2. **Templat D:**
   - pilih **Berfasa**, tambah fasa dan pilih aktiviti;
   - semak nota "dikira secara berkadar".
3. **Pinda Jadual:**
   - ubah tarikh tamat dan satu tarikh sasaran;
   - cuba simpan tanpa justifikasi;
   - semak Sejarah Pindaan Jadual.
4. **Pengguna agensi lain:** buka URL projek → 403.
5. **Akaun Pengurusan:** menu Projek, baca sahaja.
6. **Akaun Superadmin:** `/projek` → 403.
7. **Urus Setia:**
   - Rekod Kelulusan JPICT bagi projek ≥ RM500,000;
   - Buka Kunci Struktur (boleh diuji selepas SP3, apabila tanda siap tersedia).

## 8. Pilihan reka bentuk dan pengesahan pemilik (23/11/2026)

| # | Perkara | Status |
|---|---|---|
| 1 | **Rakan seagensi** (bukan pegawai projek) boleh **melihat** projek agensi sendiri, tetapi tidak boleh mengemas kini | ✅ Disahkan |
| 2 | **Tarikh sasaran mesti dalam tempoh projek** (antara tarikh mula dan tamat) | ✅ Disahkan |
| 3 | **Projek Berfasa mesti lebih daripada 1 fasa.** Had atas semasa: 10 fasa. | ✅ Disahkan (minimum 2, maksimum 10) |
| 4 | Selepas Urus Setia membuka kunci, **aktiviti yang telah ditanda siap tidak boleh dikeluarkan**, dan struktur dikunci semula apabila disimpan | ✅ Disahkan |
| 5 | **Ambang JPICT** menggunakan Jumlah Keseluruhan (`total_cost`, termasuk SST jika ditanda). Permohonan lama tanpa jumlah keseluruhan menggunakan anggaran kos. | ✅ Disahkan |

## 9. Seterusnya: SP3 (Laporan Bulanan dan Kemas Kini Aktiviti)

Keputusan diperlukan sebelum SP3:
- **I.8:** format dan saiz dokumen sokongan; sama ada dokumen wajib menyekat tanda siap;
- **I.10:** tarikh akhir laporan bulanan dan sama ada laporan lewat dibenarkan.
