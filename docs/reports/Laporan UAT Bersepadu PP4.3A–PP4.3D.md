# Laporan UAT Bersepadu PP4.3A–PP4.3D

## A. Maklumat UAT

| Perkara | Nilai |
|---|---|
| Tarikh | 17/11/2026 |
| Branch / Commit | `feature/ui-design` / `ab95aad` (perubahan PP4.3B–D belum di-commit dan dikekalkan) |
| Database UAT | **Tiada.** Pelayan MySQL 8.0.46 hanya mempunyai `sppictv2_db` (pembangunan). Tiada pangkalan data dicipta. |
| Mailtrap | Tersedia: hos jenis **sandbox** (e-mel ditangkap, tidak dihantar kepada penerima sebenar) |
| Queue | `database` (pembangunan). Worker diuji pada SQLite `:memory:` sahaja. |
| Browser | Tidak digunakan (tiada persekitaran UAT berasingan) |

**Kaedah:**
- Mengikut §2.2 prompt, semua UAT yang memerlukan rekod transaksi pada MySQL **dihentikan**.
- Satu ujian sementara di luar projek (scratchpad) dijalankan dengan:
  - data SQLite `:memory:`, dengan pengawal yang menghentikan ujian jika sambungan bukan SQLite atau hos bukan Mailtrap sandbox;
  - penerima `@example.test`;
  - queue `database` dan worker sebenar (`queue:work --once`);
  - SMTP Mailtrap daripada konfigurasi sedia ada (tidak diubah).

## B. Ringkasan Keputusan

| Komponen | Status | Catatan |
|---|---|---|
| PP4.3A – Keputusan Pra JTICT/JTICT | **NOT TESTED** (UAT) | Tiada database UAT. Ujian automatik SQLite: PASS. |
| PP4.3B – Sejarah Keputusan | **NOT TESTED** (UAT) | Ujian automatik SQLite: PASS |
| PP4.3C – Salin Permohonan | **NOT TESTED** (UAT) | Ujian automatik SQLite: PASS. Ujian serentak MySQL belum dibuat. |
| PP4.3D – Notifikasi E-mel | **PARTIAL** | Keenam-enam jenis keputusan berjaya dihantar ke Mailtrap melalui queue `database` dan worker. Paparan e-mel dalam Mailtrap perlu disemak oleh pemilik. Isu had kadar ditemui (§D). |
| Paparan Desktop/Mudah Alih | **NOT TESTED** | Tiada persekitaran UAT |
| Regression Test | **PASS** (SQLite) | 568/568 lulus, 4,426 assertions, 1 *risky* sedia ada. MySQL: NOT TESTED. |

## C. Keputusan Setiap Senario

| ID | Senario | Keputusan | Bukti / Catatan |
|---|---|---|---|
| A01–A03 | Pra JTICT Diterima / Ditolak dengan Pindaan / Ditolak | NOT TESTED (UAT) | Diliputi oleh ujian automatik `PreJtictDecisionTest`, `PreJtictMeetingValidityTest`, `ApplicationDecisionTest` (PASS, SQLite) |
| A04–A06 | JTICT Lulus / Tidak Lulus / Kaji Semula | NOT TESTED (UAT) | `JtictDecisionTest` (PASS, SQLite). Dalam ujian Mailtrap, keenam-enam keputusan berjaya disimpan melalui `ApplicationDecisionService` (SQLite). |
| B01–B06 | Sejarah keputusan (pemohon / Urus Setia / kronologi / label) | NOT TESTED (UAT) | `DecisionHistoryDisplayTest`, `PreJtictDecisionTest` (PASS, SQLite) |
| C01–C04 | Salin pertama / kedua / selepas soft delete / tiada had masa | NOT TESTED (UAT) | `ApplicationCopyTest` (PASS, SQLite). Kawalan serentak `lockForUpdate` belum disahkan pada MySQL. |
| D01 | Pra JTICT Diterima | **PASS** (Mailtrap, SQLite) | Keputusan disimpan; 2 job dalam queue; worker menghantar 2 e-mel ke Mailtrap |
| D02 | Pra JTICT Ditolak dengan Pindaan | **PASS** (Mailtrap, SQLite) | Sama seperti D01 |
| D03 | Pra JTICT Ditolak | **PASS** (Mailtrap, SQLite) | Sama seperti D01 |
| D04 | JTICT Lulus | **PASS** (Mailtrap, SQLite) | Sama seperti D01 |
| D05 | JTICT Tidak Lulus | **PASS** (Mailtrap, SQLite) | Sama seperti D01 |
| D06 | JTICT Kaji Semula | **PASS** (Mailtrap, SQLite) | Sama seperti D01 |
| D – penerima | Pendaftar dan Pegawai Utama sahaja | **PASS** | 12 e-mel, hanya kepada `uat.pendaftar@` dan `uat.pegawai.utama@example.test`. Pegawai Kedua dan Urus Setia tidak menerima. |
| D – kegagalan | E-mel gagal tetapi keputusan kekal | **PASS** (keadaan sebenar) | Larian pertama: 8/12 e-mel ditolak oleh Mailtrap (had kadar). Keenam-enam keputusan kekal tersimpan, 8 job direkodkan dalam `failed_jobs` dan ralat dilog. |
| D – kandungan | Paparan e-mel dalam Mailtrap | **NOT TESTED** | Perlu semakan visual oleh pemilik. Kandungan disahkan oleh ujian automatik. |

## D. Isu Ditemui

**Isu 1 – E-mel hilang apabila Mailtrap menolak kerana had kadar**
- **Komponen:** PP4.3D
- **Tahap kesan:** Sederhana (UAT). Bagi produksi, kesannya bergantung pada pelayan SMTP.
- **Punca:**
  - Pelan percuma Mailtrap sandbox menolak e-mel yang dihantar terlalu rapat ("550 Too many emails per second"), walaupun dengan jarak 3 saat.
  - `ApplicationDecisionRecorded` menggunakan `$tries = 1` (reka bentuk PP4.3D untuk mengelak e-mel berganda), jadi e-mel yang ditolak tidak dicuba semula.
  - Keputusan tidak terjejas.
- **Cadangan tindakan:**
  - **UAT:** jalankan worker dengan rehat antara job, contohnya `php artisan queue:work --rest=12`. Dengan jarak 12 saat, 12/12 e-mel berjaya.
  - **Produksi (keputusan pemilik):** pertimbangkan `tries` 3 dengan `backoff`, dengan risiko kecil e-mel berganda jika SMTP menerima e-mel tetapi sambungan terputus. Pilihan lain: kekalkan reka bentuk sekarang dan pantau `failed_jobs`.

**Isu 2 – Log ralat worker mengandungi mesej SMTP asal**
- **Komponen:** PP4.3D / konfigurasi log
- **Tahap kesan:** Rendah
- **Punca:**
  - Apabila job gagal, worker Laravel melaporkan exception asal ke log.
  - Laporan ini tidak melalui penapis `[dirahsiakan]` PP4.3D.
  - Bagi kegagalan pengesahan SMTP, mesej Symfony biasanya mengandungi nama pengguna SMTP, tetapi tidak mengandungi kata laluan. Ini **tidak berlaku** dalam UAT ini; mesej yang dilog hanyalah had kadar.
- **Cadangan tindakan:** keputusan pemilik. Penapis laporan exception boleh ditambah dalam `bootstrap/app.php`. Ini tidak dilaksanakan dalam UAT ini.

## E. Ujian Tidak Dapat Dilaksanakan

| Ujian | Sebab |
|---|---|
| Semua senario A, B, C pada MySQL | Tiada database MySQL UAT yang berasingan |
| Ujian salinan serentak (`lockForUpdate`) pada MySQL | Tiada database MySQL UAT yang berasingan |
| Worker queue pada MySQL | Tiada database MySQL UAT yang berasingan. Worker diuji pada SQLite. |
| Semakan visual (desktop dan mudah alih), termasuk paparan e-mel dalam Mailtrap | Tiada persekitaran UAT yang boleh dilayari tanpa menggunakan `sppictv2_db`. Peti Mailtrap perlu dibuka oleh pemilik. |

**Konfigurasi yang diperlukan untuk meneruskan UAT (memerlukan kebenaran pemilik):**
1. Cipta pangkalan data MySQL berasingan, contohnya `sppictv2_uat`, pada pelayan yang sama.
2. Sediakan persekitaran UAT yang menunjuk ke pangkalan data itu, contohnya salinan projek atau git worktree sebagai laman Herd kedua (cth. `sppict-uat.test`) dengan `.env` sendiri. `.env` pembangunan tidak perlu diubah.
3. Dalam persekitaran UAT sahaja:
   - jalankan `php artisan migrate` dan seeder data rujukan;
   - cipta akaun ujian dengan e-mel `@example.test`;
   - jalankan worker dengan `php artisan queue:work --rest=12`.

## F. Pengesahan Integriti

- **Database pembangunan tidak berubah.** Snapshot baca sahaja sebelum dan selepas UAT adalah **sama**:
  - applications 8, application_reviews 12, application_decisions 5;
  - `jobs` 0, `failed_jobs` 0;
  - `audits` maks 398;
  - hash `application_decisions` `c8943c9a…`.
- **Rekod keputusan asal kekal utuh** (hash sama).
- **Perubahan kod sedia ada dipelihara.** Satu-satunya fail baharu dalam projek ialah laporan ini. Ujian UAT sementara disimpan dalam scratchpad, di luar projek.
- **Mailtrap sandbox** kini mengandungi 16 e-mel ujian: 4 daripada larian pertama dan 12 daripada larian kedua, semuanya kepada `@example.test`. Tiada e-mel kepada penerima sebenar.
- **Commit atau push:** tidak.

## G. Rumusan

**Bersedia dengan syarat.**
- Fungsi PP4.3A–PP4.3D lulus semua ujian automatik (SQLite).
- Aliran e-mel sebenar (queue `database`, worker, SMTP Mailtrap) berfungsi bagi keenam-enam keputusan.
- UAT pengguna sebenar pada MySQL belum boleh dibuat.

Tindakan susulan yang diperlukan:
1. Sediakan pangkalan data dan persekitaran UAT berasingan (§E), kemudian ulangi senario A, B, C dan semakan visual.
2. Semak 6 jenis e-mel dalam peti Mailtrap sandbox (tajuk bermula `[SPPICT] Keputusan`, No. Rujukan `SPPICT/2026/UAT-00x`).
3. Putuskan sama ada cadangan bagi Isu 1 dan Isu 2 perlu dilaksanakan.
