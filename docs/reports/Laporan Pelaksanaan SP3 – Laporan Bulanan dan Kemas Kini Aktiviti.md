# Laporan Pelaksanaan SP3 – Laporan Bulanan dan Kemas Kini Aktiviti

**Tarikh:** 23/11/2026 · **Branch:** `feature/sp-status-kemajuan` (dari `c750210`, SP2) · **Status:** PASS (ujian automatik). Belum diuji secara manual atau di UAT.

**Rujukan:** SP0 – Audit Struktur dan Analisis Integrasi, Bahagian F.4–F.7, J (SP3), L.1, L.3 dan L.7.

## 1. Skop

- **Tanda siap aktiviti** dengan tarikh sebenar siap. Binari 0/100, tanpa peratus separa (L.1).
- **Batal tanda siap** dengan catatan wajib.
- Sejarah tanda siap dan batal tanda yang tidak boleh diubah atau dipadam (append-only).
- **Kunci struktur** apabila aktiviti pertama ditanda siap (L.3).
- **Dokumen sokongan:**
  - PDF sahaja, maksimum 25 MB;
  - dokumen wajib mesti dimuat naik sebelum aktiviti boleh ditanda siap (I.8);
  - disimpan dalam storan private, dan dibuka hanya oleh pengguna yang boleh melihat projek.
- **Laporan kemajuan bulanan:**
  - satu laporan bagi setiap bulan;
  - Draf, kemudian Hantar (dikunci);
  - snapshot aktiviti pada tarikh penilaian;
  - tarikh akhir dan tanda "Lewat" (I.10, L.7).

**Di luar skop SP3:** peratus kemajuan sebenar, dirancang dan varians, bagi projek dan bagi laporan, dikira dalam SP4. Kolum laporan untuk nilai ini telah disediakan, tetapi masih kosong.

Tiada perubahan pada jadual `applications`, aliran permohonan atau keputusan JTICT.

## 2. Perubahan

| Fail | Perubahan |
|---|---|
| `database/migrations/2026_11_23_000001_add_monthly_report_columns_to_project_progress_reports_table.php` | **Baharu.** Kolum pada `project_progress_reports`: `status`, `evaluation_date`, `due_date`, `due_meeting_id`, `is_late`, `submitted_at`, peratus snapshot (untuk SP4), `created_by`. Indeks unik projek + tahun + bulan. Kolum lama `physical_*` / `financial_*` dijadikan nullable. Semakan pendua sebelum indeks unik. `down()` mencipta semula indeks FK yang dibuang secara automatik oleh MySQL. |
| `database/migrations/2026_11_23_000002_create_progress_updates_report_activities_and_attachments_tables.php` | **Baharu.** `project_progress_updates` (sejarah, append-only), `project_progress_report_activities` (snapshot laporan), `project_progress_attachments` (dokumen) |
| `app/Models/ProjectProgressReport.php` | Status, tarikh akhir, Lewat, relasi snapshot dan dokumen. **Laporan Dihantar tidak boleh diubah atau dipadam** (`LogicException`). |
| `app/Models/ProjectProgressUpdate.php` | **Baharu.** Append-only. |
| `app/Models/ProjectProgressReportActivity.php`, `ProjectProgressAttachment.php` | **Baharu** |
| `app/Enums/ProgressReportStatus.php`, `ProgressUpdateType.php` | **Baharu** |
| `app/Models/Project.php`, `ProjectProgressActivity.php` | Relasi dokumen dan sejarah |
| `app/Services/ProjectProgressService.php` | **Baharu.** `completeActivity()`, `uncompleteActivity()`, `uploadAttachment()`, `deleteAttachment()` |
| `app/Services/ProjectReportService.php` | **Baharu.** `create()`, `availablePeriods()`, `saveDraft()`, `submit()`, `dueMeeting()` |
| `app/Http/Controllers/ProjectAttachmentController.php` | **Baharu.** Buka dokumen selepas semakan `ProjectPolicy@view`. |
| `app/Livewire/Projects/ShowProject.php` + paparan | Lajur dokumen dan butang **Tanda Siap / Kemas Kini** bagi setiap aktiviti; modal kemas kini aktiviti (muat naik / padam dokumen, tanda siap, batal tanda); bahagian Laporan Kemajuan Bulanan (Cipta Laporan) dan Sejarah Kemas Kini Aktiviti |
| `app/Livewire/Projects/ProgressReportPage.php` + paparan | **Baharu.** Halaman laporan: sunting Draf, dokumen laporan, Simpan Draf, Hantar Laporan; paparan snapshot selepas dihantar |
| `routes/web.php` | `/projek/{uuid}/laporan/{uuid}`, `/projek/{uuid}/dokumen/{uuid}` |
| `tests/Feature/ProjectProgressTest.php` | **Baharu**, 14 ujian (§5) |

## 3. Peraturan yang dilaksanakan

**Tanda siap**
1. Hanya Pegawai Utama atau Pegawai Kedua yang aktif (I.11). Projek baca sahaja ditolak.
2. Tarikh sebenar siap wajib, tarikh yang sah, dan tidak boleh melebihi hari ini.
3. Dokumen wajib (matriks §5.5) mesti ada sebelum tanda siap. Aktiviti tanpa dokumen wajib, contohnya Latihan / TOT, boleh ditanda terus.
4. Tanda siap pertama mengisi `structure_locked_at`, jadi struktur dikunci (L.3).
5. **Batal tanda siap:**
   - catatan wajib;
   - tarikh siap yang dibatalkan disimpan dalam sejarah;
   - struktur **kekal dikunci**.
6. Untuk membetulkan tarikh sebenar siap, pegawai membatalkan tanda siap dengan catatan, kemudian menanda semula. Kedua-dua langkah tercatat dalam sejarah.

**Dokumen**

7. PDF disemak berdasarkan kandungan fail sebenar, bukan sambungan nama fail sahaja (peraturan `DocumentSignature` sedia ada), dengan had 25 MB.
8. Disimpan dalam storan private: `projects/{uuid}/progress`.
9. **Padam dokumen:**
   - dokumen di-soft delete, dan fail kekal dalam storan untuk audit;
   - dokumen wajib terakhir bagi aktiviti yang telah siap tidak boleh dipadam;
   - dokumen laporan yang telah dihantar tidak boleh dipadam.
10. **Buka dokumen:** dibenarkan kepada pengguna agensi projek, Urus Setia dan Pengurusan. Agensi lain dan Superadmin mendapat 403.

**Laporan bulanan**

11. Satu laporan bagi setiap bulan, bagi bulan antara bulan mula projek dan bulan semasa. Bulan yang telah ada laporan, bulan masa hadapan dan bulan sebelum projek bermula ditolak.
12. **Tarikh akhir (L.7):**
    - tarikh akhir bagi bulan M ialah Tarikh Status Kemaskini Kemajuan Projek bagi mesyuarat pertama yang jatuh pada atau selepas hari terakhir bulan M;
    - disalin ke laporan semasa laporan dicipta;
    - jika tiada tarikh semasa laporan dicipta, sistem mencari semula semasa laporan dihantar.
13. **Semasa dihantar:**
    - Ringkasan Kemajuan wajib diisi;
    - tarikh penilaian ialah hari terakhir bulan laporan, atau hari ini bagi bulan semasa;
    - snapshot semua aktiviti diambil: pemberat, tarikh sasaran, "dirancang siap" (tarikh sasaran ≤ tarikh penilaian) dan "siap" (siap pada atau sebelum tarikh penilaian);
    - laporan ditanda **Lewat** jika dihantar selepas tarikh akhir;
    - pegawai yang menghantar direkodkan;
    - laporan dikunci.
14. Aktiviti yang siap **selepas** tarikh penilaian tidak dikira siap dalam snapshot. Ini memastikan laporan lewat bagi bulan terdahulu mencerminkan keadaan sebenar pada bulan itu.
15. Draf laporan tidak boleh dipadam melalui sistem.

## 4. Akses

| Tindakan | Pegawai Utama / Kedua aktif | Rakan seagensi | Urus Setia | Pengurusan | Superadmin | Agensi lain |
|---|---|---|---|---|---|---|
| Tanda siap / batal tanda / muat naik / padam dokumen | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Cipta, sunting dan hantar laporan | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Lihat laporan, snapshot, sejarah dan dokumen | ✅ | ✅ | ✅ | ✅ | ❌ (403) | ❌ (403) |

## 5. Ujian

`ProjectProgressTest` (14 ujian):
- **Tanda siap:**
  - ditolak jika dokumen wajib belum dimuat naik;
  - selepas muat naik, tanda siap direkodkan dalam sejarah dan struktur dikunci;
  - Latihan / TOT boleh ditanda tanpa dokumen;
  - tarikh kosong, masa hadapan atau tidak sah ditolak;
  - tanda siap kali kedua ditolak.
- **Batal tanda:**
  - catatan wajib;
  - tarikh yang dibatalkan disimpan dalam sejarah;
  - struktur kekal dikunci;
  - sejarah append-only.
- **Akses:**
  - rakan seagensi, agensi lain, Urus Setia, Pengurusan dan Superadmin tidak boleh menanda siap, memuat naik atau mencipta laporan.
- **Dokumen:**
  - fail bukan PDF yang dinamakan `.pdf` ditolak; fail `.docx` ditolak; fail melebihi 25 MB ditolak;
  - dokumen wajib terakhir bagi aktiviti siap tidak boleh dipadam; selepas dokumen pengganti dimuat naik, dokumen lama boleh dipadam (soft delete, fail kekal);
  - muat turun ikut akses projek; dokumen projek lain mendapat 404.
- **Laporan:**
  - bulan yang dibenarkan; laporan berganda, bulan masa hadapan, bulan sebelum projek dan bulan 13 ditolak;
  - tarikh akhir mengikut mesyuarat pertama selepas akhir bulan; mesyuarat dipadam diabaikan; tiada mesyuarat bermakna tiada tarikh akhir;
  - semasa dihantar:
    - Ringkasan Kemajuan wajib;
    - snapshot betul, termasuk aktiviti yang siap selepas tarikh penilaian;
    - laporan ditanda Lewat jika perlu;
    - pegawai penghantar direkodkan;
  - selepas dihantar, laporan tidak boleh diubah, dipadam, atau menerima dokumen baharu;
  - laporan bulan semasa menggunakan tarikh hari ini dan tidak ditanda Lewat.
- **Skrin Livewire:**
  - aliran penuh: tanda siap melalui modal, Cipta Laporan, Hantar;
  - Pengurusan melihat laporan secara baca sahaja; agensi lain mendapat 403;
  - muat naik dokumen melalui modal, termasuk penolakan fail bukan PDF.

**Suite penuh:** 639/639 PASS. Satu amaran *risky* dalam `UserRoleManagementTest`, sama seperti sebelum ini.

**Had ujian:**
- SQLite tidak menyokong kunci baris sebenar, jadi permintaan serentak tidak dapat diuji.
- Ujian muat naik melalui Livewire dijalankan tanpa perjalanan masa. Livewire memadam fail sementara yang lebih lama daripada 24 jam, jadi fail ujian akan terpadam jika masa ujian dianjak ke hadapan. Ini hanya berlaku dalam ujian.

## 6. Pangkalan data

- **Pembangunan (`sppictv2_db`):**
  - kedua-dua migration dijalankan pada MySQL;
  - rollback dan migrate semula diuji, dan nama indeks serta FK sah;
  - tiada data diubah, kerana jadual laporan kosong.
- **UAT (`sppictv2_uat`):** belum dijalankan. Langkah apabila kod dibawa ke UAT:
  1. `php artisan migrate`. Ini merangkumi PP4.5, SP1, SP2 dan SP3.
  2. `php artisan db:seed --class=ProgressTemplateSeeder`.
  3. Tukar nama Jenis Perolehan melalui skrin Master Data jika belum dibuat.

## 7. Ujian manual dicadangkan (UAT)

1. **Pegawai projek, aktiviti dengan dokumen wajib:**
   - klik **Tanda Siap** tanpa dokumen; sistem sepatutnya menolak;
   - muat naik PDF dan tanda siap;
   - semak lencana Struktur Dikunci dan Sejarah Kemas Kini Aktiviti.
2. **Muat naik fail bukan PDF**, atau fail PDF melebihi 25 MB. Sistem sepatutnya menolak.
3. **Batal tanda siap:**
   - cuba tanpa catatan; sistem sepatutnya menolak;
   - cuba dengan catatan.
4. **Cipta Laporan bulan lepas:**
   - simpan draf;
   - hantar tanpa Ringkasan Kemajuan; sistem sepatutnya menolak;
   - hantar dengan Ringkasan Kemajuan;
   - semak snapshot dan tanda "Lewat" berbanding Tarikh Status Kemaskini Kemajuan Projek mesyuarat.
5. **Pengurusan dan Urus Setia:**
   - buka laporan dan dokumen; sepatutnya baca sahaja;
   - semak tiada butang Tanda Siap atau Cipta Laporan.
6. **Agensi lain:** buka URL dokumen atau laporan. Sistem sepatutnya memaparkan 403.

## 8. Pilihan reka bentuk (✅ semua disahkan pemilik 23/11/2026)

1. **Tafsiran "mesyuarat aktif" (L.7):**
   - sistem menggunakan semua mesyuarat yang **tidak dipadam** dan mempunyai Tarikh Status Kemaskini Kemajuan Projek;
   - status Aktif / Tidak Aktif mesyuarat **tidak** digunakan, kerana mesyuarat menjadi Tidak Aktif selepas tempoh permohonan ditutup;
   - jika status itu digunakan, laporan lewat bagi bulan terdahulu tidak akan dapat dikesan sebagai Lewat.
2. **Tarikh sebenar siap tiada had bawah.** Contohnya, aktiviti boleh siap sebelum tarikh mula projek. Hanya tarikh masa hadapan ditolak.
3. **Ringkasan Kemajuan sahaja yang wajib** semasa laporan dihantar. Isu dan Kekangan serta Tindakan Susulan adalah pilihan.
4. **Draf laporan tidak boleh dipadam.**
5. **Pembetulan tarikh sebenar siap** dibuat dengan membatalkan tanda siap (catatan wajib), kemudian menanda semula.

## 9. Seterusnya: SP4 (Pengiraan, Validasi dan Sejarah)

Pemilik bersetuju dengan cadangan I.5 dan I.6 pada 23/11/2026 (direkodkan dalam laporan SP0, L.8). Butiran asal:
- **I.5:**
  - indikator status utama: varians Mendahului / Mengikut / Lewat, atau lampu Hijau / Kuning / Merah, atau kedua-duanya;
  - ambang lampu (cadangan: 0 / −0.01 hingga −19.99 / ≤ −20);
  - tiada pengesahan prestasi oleh Urus Setia.
- **I.6:**
  - kriteria penutupan projek (status Selesai);
  - siapa yang menutup projek;
  - bila projek Penyelenggaraan dianggap Selesai.
