# Laporan Pelaksanaan SP5 – Dashboard dan Laporan

**Tarikh:** 23/11/2026 · **Branch:** `feature/sp-status-kemajuan` (dari `fad483a`, SP4) · **Status:** PASS (ujian automatik). Belum diuji secara manual atau di UAT.

**Rujukan:** SP0 – Audit Struktur dan Analisis Integrasi, Bahagian J (SP5) dan L.9 (keputusan pemilik pada 23/11/2026).

## 1. Skop

1. **Papan pemuka Pengurusan** (baharu), dengan lima bahagian:
   - (a) statistik permohonan projek mengikut tahun dihantar, Jabatan/Agensi dan Kategori Permohonan;
   - (b) bilangan projek mengikut status kemajuan;
   - (c) ringkasan mengikut Jabatan/Agensi;
   - (d) statistik projek mengikut kos Jabatan;
   - (e) senarai projek lewat.
2. **Papan pemuka Urus Setia:** bahagian baharu Pemantauan Projek, mengandungi (b), (c) dan (e).
3. **Papan pemuka pemohon:** senarai status kemajuan projek kini menggunakan data SP4, iaitu nilai kemajuan yang disimpan, status projek dan laporan bulanan yang telah dihantar.
4. **Eksport:**
   - **A:** Senarai Projek ke Excel;
   - **C:** Ringkasan Status Kemajuan Projek ke PDF dan Excel.
5. **Persaraan** `Project::updateStatusIndicator()`.

Tiada migration dan tiada perubahan pada aliran permohonan atau keputusan JTICT.

## 2. Perubahan

| Fail | Perubahan |
|---|---|
| `app/Services/DashboardStatisticsService.php` | **Baharu.** Statistik (a) hingga (e) |
| `app/Services/ProjectQueryService.php` | **Baharu.** Pertanyaan Senarai Projek dikongsi oleh skrin dan eksport A, supaya skop akses dan penapis sentiasa sama |
| `app/Services/ProjectSummaryService.php` | **Baharu.** Data eksport C, dikira terus pada tarikh eksport |
| `app/Exports/ProjectListExport.php`, `ProjectSummaryExport.php` | **Baharu.** Eksport Excel (`maatwebsite/excel`) |
| `resources/views/exports/project-summary.blade.php` | **Baharu.** PDF A4 landskap (`dompdf`) |
| `app/Http/Controllers/ProjectExportController.php` | **Baharu.** `list` (A), `summaryPdf` dan `summaryExcel` (C) |
| `app/Livewire/Dashboard/ProjectStatistics.php` + paparan | **Baharu.** Komponen statistik: penuh bagi Pengurusan; (b), (c) dan (e) bagi Urus Setia; penapis tahun |
| `resources/views/dashboard/pengurusan.blade.php`, `dashboard.blade.php` | **Baharu:** papan pemuka Pengurusan, dengan status mesyuarat JTICT semasa dan pautan eksport C |
| `resources/views/dashboard/urus-setia.blade.php` | Bahagian Pemantauan Projek |
| `resources/views/dashboard/pengguna.blade.php` | Senarai status kemajuan projek menggunakan data SP4; pautan ke Maklumat Projek |
| `app/Policies/ProjectPolicy.php` | `viewStatistics`: Urus Setia dan Pengurusan sahaja; Superadmin ditolak |
| `app/Livewire/Projects/ProjectList.php` + paparan | Menggunakan `ProjectQueryService`. Butang Eksport Senarai (Excel); Ringkasan Status (PDF / Excel) bagi Urus Setia dan Pengurusan. |
| `routes/web.php` | `/projek/eksport/senarai.xlsx`, `/projek/eksport/ringkasan.pdf`, `/projek/eksport/ringkasan.xlsx` |
| `app/Models/Project.php` | `updateStatusIndicator()` dibuang (tidak digunakan) |
| `resources/views/livewire/dashboard/management-overview.blade.php`, `app/Support/RoleTheme.php`, `resources/views/dashboard.blade.php` | **Reka bentuk interaktif papan pemuka Pengurusan** (diluluskan 23/11/2026, dibuat dalam sesi reka bentuk berasingan). Tema Pengurusan "Arang & Emas". Penapis tahun pada angka utama, (a) dan (d); pilihan ukuran kos atau bilangan; tapisan lampu projek lewat; susunan jadual agensi. Kepala halaman dan pautan eksport berada dalam komponen. |
| `tests/Feature/ProjectDashboardTest.php` | **Baharu**, 6 ujian |
| `tests/Feature/ManagementDashboardDesignTest.php` | **Baharu**, 2 ujian reka bentuk papan pemuka Pengurusan |
| `tests/Feature/PenggunaDashboardTest.php` | Ujian kemajuan projek dikemas kini kepada data SP4 |
| `tests/Feature/UrusSetiaDashboardTest.php` | `Str::between` ditukar kepada `Str::betweenFirst`, supaya hanya jadual senarai permohonan disemak. Papan pemuka kini ada jadual lain selepas senarai itu. |

## 3. Peraturan statistik

| Bahagian | Peraturan |
|---|---|
| (a) Permohonan | Permohonan yang telah dihantar: tidak termasuk Draf dan rekod yang dipadam. **Tahun dihantar** diambil daripada nombor rujukan rasmi `SPPICT/TTTT/NNNN`, yang diberikan semasa permohonan dihantar. Sistem tiada kolum tarikh hantar; jika format nombor rujukan lain, tahun rekod dicipta digunakan. Pecahan keputusan: Dalam Proses, Lulus, Tidak Lulus, Kaji Semula, Ditolak (Pra JTICT), mengikut keputusan terkini. |
| Penapis tahun | Terpakai pada (a)(ii), (a)(iii) dan (d). Jadual (a)(i) sentiasa memaparkan semua tahun. |
| (b) Status projek | Bilangan bagi setiap status kitaran. Bagi projek aktif (Dalam Pelaksanaan, Menunggu Pengesahan Penutupan): bilangan mengikut lampu Hijau / Kuning / Merah / Tiada data rancangan. |
| (c) Ringkasan agensi | Bilangan projek (tidak termasuk rekod sejarah v1), aktif, Selesai, Ditutup, lewat (projek aktif dengan varians negatif), dan purata kemajuan sebenar projek aktif |
| (d) Kos | Permohonan Lulus JTICT mengikut agensi: bilangan, dan jumlah **Jumlah Keseluruhan permohonan** (termasuk SST). Anggaran kos digunakan bagi permohonan lama yang tiada Jumlah Keseluruhan. |
| (e) Projek lewat | Projek aktif dengan varians negatif, paling lewat dahulu (maksimum 50 projek) |

**Sumber nilai:**
- Statistik projek menggunakan nilai kemajuan yang disimpan. Nilai ini dikemas kini setiap kali kemajuan atau jadual berubah, dan setiap hari pada 12:30 pagi.
- Eksport C pula mengira kemajuan terus pada tarikh eksport.

## 4. Eksport

| Eksport | Siapa | Kandungan |
|---|---|---|
| **A: Senarai Projek (Excel)** | Semua pengguna yang boleh membuka Senarai Projek | Mengikut penapis skrin (carian, status, agensi) dan skop akses: pengguna biasa hanya agensi sendiri, walaupun parameter agensi lain dihantar. 17 lajur: rujukan, nama, agensi, templat, jenis, tempoh, status, sebenar, dirancang, varians, status jadual, lampu, Menunggu JPICT, jumlah kos, dan tarikh penilaian. |
| **C: Ringkasan Status Kemajuan (PDF / Excel)** | Urus Setia dan Pengurusan sahaja | Projek aktif pada tarikh eksport, dikumpulkan mengikut agensi (penapis agensi pilihan). Ringkasan di atas: bilangan aktif, lewat, Selesai dan Ditutup. Setiap projek: rujukan, nama, templat / fasa, tempoh, sebenar, dirancang, varians, status jadual (berwarna dalam PDF), laporan bulanan terkini, bilangan laporan belum dihantar, dan Menunggu JPICT. |

Superadmin ditolak bagi kedua-dua eksport (I.9). Setiap eksport direkodkan dalam log aplikasi.

## 5. Ujian

`ProjectDashboardTest` (6 ujian):
- **(a) dan (d):**
  - statistik mengikut tahun, agensi (dengan dan tanpa penapis tahun) dan kategori, termasuk "Tidak dinyatakan";
  - Draf dan permohonan dipadam tidak dikira;
  - pecahan lima kategori keputusan betul;
  - kos Lulus JTICT mengikut agensi, dengan penapis tahun.
- **(b), (c) dan (e):**
  - bilangan status dan lampu (termasuk Tiada data rancangan dan rekod v1);
  - ringkasan agensi: aktif, Selesai, Ditutup, lewat dan purata;
  - projek Ditutup tidak dikira sebagai lewat;
  - susunan projek lewat betul.
- **Papan pemuka ikut peranan:**
  - Pengurusan nampak semua bahagian;
  - Urus Setia nampak (b), (c) dan (e) tanpa statistik permohonan;
  - pengguna biasa tiada statistik;
  - pengguna biasa dan Superadmin ditolak.
- **Penapis tahun Livewire** mengubah jumlah kos.
- **Eksport A:**
  - pengguna biasa hanya mendapat agensi sendiri;
  - penapis status Pengurusan berfungsi;
  - bilangan lajur sepadan dengan tajuk;
  - Superadmin mendapat 403.
- **Eksport C:**
  - pengguna biasa dan Superadmin mendapat 403;
  - PDF dijana untuk Pengurusan (Content-Type `application/pdf`);
  - Excel Urus Setia dengan penapis agensi.

**Suite penuh:** 666/666 PASS, termasuk reka bentuk interaktif. Satu amaran *risky* dalam `UserRoleManagementTest`, sama seperti sebelum ini.

## 6. Pangkalan data

Tiada migration baharu dalam SP5.

**UAT (`sppictv2_uat`):** belum dijalankan. Langkah apabila kod dibawa ke UAT:
1. `php artisan migrate`. Ini merangkumi PP4.5 dan SP1 hingga SP4.
2. `php artisan db:seed --class=ProgressTemplateSeeder`.
3. Pastikan `php artisan schedule:run` berjalan setiap minit.

## 7. Ujian manual dicadangkan (UAT)

1. Log masuk sebagai **Pengurusan**:
   - semak bahagian (a) hingga (e);
   - tukar penapis tahun;
   - muat turun Ringkasan Status dalam PDF dan Excel.
2. **Urus Setia:** semak bahagian Pemantauan Projek di bawah papan pemuka.
3. **Pemohon:** semak senarai status kemajuan projek, termasuk kemajuan sebenar, dirancang, lampu dan laporan bulanan terkini.
4. **Senarai Projek:**
   - tapis mengikut status atau agensi, kemudian klik **Eksport Senarai (Excel)**;
   - semak bahawa isi fail sepadan dengan skrin.
5. **Superadmin:** buka URL eksport. Sistem sepatutnya memaparkan 403.
6. **PDF Ringkasan:** semak paparan landskap, warna lampu dan pengumpulan mengikut agensi. Ini sesuai untuk kertas mesyuarat.

## 8. Catatan dan perkara terbuka

1. **Papan pemuka Pengurusan** tidak lagi memaparkan senarai permohonan generik yang dipaparkan sebelum ini. Status mesyuarat JTICT semasa dikekalkan.
2. **Tahun dihantar** diambil daripada nombor rujukan, kerana sistem tiada kolum tarikh hantar. Jika tarikh hantar yang tepat diperlukan pada masa hadapan, kolum `submitted_at` boleh ditambah.
3. **Laporan bulanan tertunggak:**
   - dipaparkan dalam eksport C dan kepada Urus Setia semasa pengesahan penutupan;
   - tidak dipaparkan sebagai statistik papan pemuka, kerana tidak diminta.
4. **Eksport B dan D** tidak dibina, mengikut keputusan pemilik.
5. Fasa berasingan selepas SP1 hingga SP5:
   - **Bank Projek (v1)**, yang memerlukan keputusan I.13;
   - kemajuan kewangan (I.4, ditangguhkan).
