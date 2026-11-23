# Laporan Audit dan Pengesahan PP4.2B – Fungsi Keputusan Pra JTICT

**Sistem:** SPPICT v2.1
**Tarikh audit:** 11/11/2026
**Cawangan:** `feature/ui-design` (commit terakhir `543829a`; tiada commit dibuat)
**Jenis fasa:** Audit dan pengesahan sahaja. Tiada perubahan pada kod, konfigurasi atau data.
**Rujukan:** Laporan PP4.1B, PP4.2 dan PP4.2A

---

## 1. Ringkasan eksekutif

| Bahagian | Status | Ringkasan |
|---|---|---|
| A – Audit Git, konfigurasi, struktur & integriti | **Lulus** | Struktur sepadan dengan PP4.1B. Tiada rekod yatim (*orphan*). Data tidak berubah sepanjang audit. |
| B – Ujian automatik SQLite | **Lulus dengan Catatan** | 8 fail berkaitan: 112/112 lulus. Suite penuh: 504/504 lulus (2 larian), 1 *risky* di luar skop. Kegagalan rawak PP4.2A tidak berulang dalam 20 larian. |
| C – Pengesahan MySQL | **Belum Diuji** | Tiada pangkalan data ujian MySQL berasingan. Fasa dihentikan mengikut C1. |
| D – UAT | **Belum Diuji** | Pelan UAT lengkap disediakan (33 senario). Tiada UAT dilaksanakan oleh pengguna. |
| E – Semakan visual | **Belum Diuji** | Tiada alat pelayar dalam persekitaran agent. Log masuk juga akan menulis ke `sppictv2_db`. Hanya semakan statik dibuat. |
| F – Isu & risiko | Selesai | 0 Kritikal, 0 Tinggi, 5 Sederhana, 5 Rendah |

**Kesimpulan:** fungsi Keputusan Pra JTICT lulus semua ujian automatik pada SQLite. Data pembangunan konsisten. PP4.2B **tidak** boleh dianggap disahkan sepenuhnya kerana pengesahan MySQL, UAT dan semakan visual masih Belum Diuji. PP4.3 boleh dimulakan **bersyarat** (lihat Seksyen 15).

---

## 2. Skop audit

- Senarai menunggu Keputusan Pra JTICT dan bahagian Perlu Semakan Manual.
- Paparan butiran, keputusan Diterima, Ditolak dengan Pindaan dan Ditolak.
- Sejarah keputusan, kitaran pindaan dan penghantaran semula.
- Kawalan akses, label status dan tanda penghantaran semula.
- Kod rujukan:
  - `ApplicationDecisionService`;
  - `ApplicationPolicy`;
  - model `Application` dan `ApplicationDecision`;
  - komponen `PreJtictList`, `ViewApplication` dan `ApplicationList`;
  - paparan Blade berkaitan.

Di luar skop: keputusan JTICT (PP4.3), Salin Permohonan, Kemajuan Projek dan e-mel keputusan.

---

## 3. Peraturan keselamatan yang dipatuhi

| Peraturan | Pematuhan | Bukti |
|---|---|---|
| Tiada perubahan kod, Blade, CSS, JS atau konfigurasi | Dipatuhi | Hash diff Git sebelum dan selepas sama (Seksyen 4) |
| Tiada migration, seeder atau `npm run build` | Dipatuhi | Migrasi kekal 45, batch 24 |
| Tiada `INSERT`, `UPDATE`, `DELETE` atau `TRUNCATE` pada `sppictv2_db` | Dipatuhi | Skrip audit menolak sebarang pernyataan selain `SELECT`, `SHOW`, `EXPLAIN` dan `DESCRIBE` (pengawal dalam kod). Hash data sebelum dan selepas sama (Seksyen 7). |
| Tiada perubahan `.env`, DB, SMTP atau queue | Dipatuhi | `.env` dibaca untuk kunci bukan rahsia sahaja (`DB_CONNECTION`, `DB_DATABASE`, `APP_ENV`, pemacu sesi, queue dan cache) |
| Tiada operasi Git yang mengubah keadaan | Dipatuhi | Hanya `git status`, `git diff`, `git log` dan `git hash-object` (baca) |
| Tiada nilai rahsia dipaparkan | Dipatuhi | Hos, pengguna, kata laluan dan token tidak dibaca atau dipaparkan |
| Ujian tidak menyentuh DB pembangunan | Dipatuhi | Sambungan ujian disahkan `sqlite` / `:memory:` semasa larian (Seksyen 5) |
| Tiada pembaikan automatik | Dipatuhi | Semua isu hanya didokumenkan |

Fail sementara audit (skrip baca sahaja, log ujian dan snapshot) disimpan dalam folder scratchpad sesi agent, di luar projek.

---

## 4. Keadaan Git sebelum dan selepas audit

| Perkara | Sebelum | Selepas |
|---|---|---|
| Cawangan | `feature/ui-design` | `feature/ui-design` |
| Fail diubah (belum di-commit) | 43 fail (1,700 tambah / 780 buang) | Sama |
| Fail baharu (*untracked*) | 11 | Sama |
| Hash `git diff` | `b72a03c2ce5ed97ef488eca512d6bc3491bd6140` | `b72a03c2ce5ed97ef488eca512d6bc3491bd6140` |
| Hash kandungan fail *untracked* | `bdabb4c120ec65f528106472186b499d33e7f19b` | `bdabb4c120ec65f528106472186b499d33e7f19b` |

Perubahan yang belum di-commit merangkumi:
- kerja PP4.2, PP4.2A dan peraturan "pegawai aktif sahaja";
- kerja pemilik sistem: RichText, `config/purifier.php`, `resources/css/app.css`, komponen borang, fasa borang dan `ApplicationFormDesignTest`.

Tiada fail yang disimpan, dibuang atau diubah. Satu-satunya fail baharu ialah laporan ini.

---

## 5. Semakan konfigurasi

| Perkara | Nilai |
|---|---|
| PHP | 8.4.27 (CLI, NTS x64) |
| Laravel | 13.23.0 |
| MySQL (pembangunan) | 8.0.46 |
| Sambungan aktif (aplikasi) | `mysql`, pangkalan data `sppictv2_db` |
| Zon masa aplikasi / MySQL | `Asia/Kuala_Lumpur` / `SYSTEM`. `NOW()` MySQL sama dengan `now()` PHP. |
| Pemacu sesi / queue / cache (`.env`) | `database` / `database` / `database` |
| `phpunit.xml` | `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array` |
| `.env.testing` | Tiada |
| Pembolehubah persekitaran OS `DB_*` | Tiada (tidak mengatasi `phpunit.xml`) |
| Pengesahan masa larian | Probe ujian: `default=sqlite database=:memory: driver=sqlite` |

**Kesimpulan:** ujian automatik menggunakan SQLite in-memory dan **tidak** boleh menulis ke `sppictv2_db`.

---

## 6. Struktur pangkalan data

### 6.1 Semakan wajib (A3)

| # | Semakan | Keputusan | Bukti |
|---|---|---|---|
| 1 | `application_decisions` mempunyai UUID | **Lulus** | `uuid char(36) NOT NULL`, `UNIQUE KEY application_decisions_uuid_unique`. Tiada UUID kosong atau berganda. |
| 2 | `application_decisions` tiada `updated_at` | **Lulus** | Hanya `created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| 3 | Foreign key menggunakan NO ACTION | **Lulus** | `application_id`, `decided_by` dan `meeting_id`: `UPDATE_RULE` dan `DELETE_RULE` = `NO ACTION`. `application_reviews` juga NO ACTION. |
| 4 | `applications.copied_from_application_id` nullable | **Lulus** | `bigint unsigned`, `IS_NULLABLE=YES`, FK NO ACTION ke `applications.id` |
| 5 | `applications.jtict_decision` dikekalkan (legacy) | **Lulus** | `enum('PENDING','APPROVED','APPROVED_CONDITIONAL','REVIEW','REJECTED') NOT NULL DEFAULT 'PENDING'`. `jtict_remarks` juga kekal. |
| 6 | Tiada perubahan struktur tidak dijangka | **Lulus** | Migrasi PP4.1B (`2026_11_03_000001`, `2026_11_03_000002`) dalam batch 24, iaitu batch terakhir. Jumlah migrasi 45. |

### 6.2 Struktur `application_decisions`

`id`, `uuid`, `application_id`, `stage` varchar(20), `decision` varchar(30), `from_status`, `to_status`, `remarks` text NULL, `decision_date` date NOT NULL, `meeting_id` NULL, `decided_by` NOT NULL, `created_at`.

Indeks: `(application_id, stage, created_at)`, `(stage, decision)`, `meeting_id` dan `decided_by`.

Jadual `applications`, `application_reviews`, `meetings` dan `users` wujud dengan struktur yang dijangka, berdasarkan `SHOW CREATE TABLE` penuh. `application_reviews` tidak mempunyai `updated_at`, iaitu sejarah semakan yang tidak berubah.

---

## 7. Integriti rekod pembangunan

### 7.1 Snapshot sebelum dan selepas audit

| Perkara | Sebelum | Selepas |
|---|---|---|
| applications | 8 | 8 |
| application_reviews | 11 | 11 |
| application_decisions | 4 | 4 |
| meetings | 2 | 2 |
| users | 13 | 13 |
| jobs / failed_jobs | 0 / 0 | 0 / 0 |
| Migrasi (jumlah / batch maks) | 45 / 24 | 45 / 24 |
| `audits` id maks | 393 | 393 |
| Hash kandungan `applications` | `7e0cebff…a4df` | `7e0cebff…a4df` |
| Hash kandungan `application_reviews` | `2fa77993…3163` | `2fa77993…3163` |
| Hash kandungan `application_decisions` | `884c2e9b…1b13` | `884c2e9b…1b13` |

Nota: bilangan rekod berbeza berbanding laporan PP4.2A (contohnya keputusan 3 → 4, semakan 10 → 11). Perbezaan ini datang daripada penggunaan aplikasi oleh pemilik sistem pada 05/11/2026, sebelum audit ini. Sepanjang audit, data tidak berubah.

### 7.2 Pecahan

- **Status permohonan:** DRAFT 2, SUBMITTED 3, IN_JTICT 3 (tiada yang soft-deleted).
- **Keputusan:** PRE_JTICT / ACCEPTED 3, PRE_JTICT / AMENDMENT_REQUIRED 1. Tiada keputusan JTICT.
- **`jtict_decision` legacy:** PENDING 8 (tidak disentuh oleh PP4).

### 7.3 Pemeriksaan integriti (SELECT sahaja)

| Pemeriksaan | Keputusan |
|---|---|
| Keputusan tanpa permohonan | 0 |
| Keputusan pada permohonan soft-deleted | 0 |
| `meeting_id` tidak sah | 0 |
| `decided_by` tidak sah | 0 |
| `copied_from_application_id` tidak sah | 0 (semua NULL) |
| Status pasca-Pra JTICT (IN_JTICT / PRE_JTICT_AMENDMENT / CLOSED) tanpa rekod keputusan | 0 |
| Keputusan Pra JTICT terkini vs status semasa | 3/3 sepadan (ACCEPTED ↔ IN_JTICT) |
| Tarikh keputusan melebihi tarikh rekod | 0 |
| Tarikh keputusan lebih awal daripada PP2 Lengkap terkini | 0 |

### 7.4 Penemuan untuk semakan pemilik (bukan kerosakan)

1. **Kitaran pindaan lengkap pada MySQL (permohonan ID 7):**
   - AMENDMENT_REQUIRED (keputusan #3, 04/11);
   - Dihantar Semula `PRE_JTICT_AMENDMENT → SUBMITTED` (semakan #10, 05/11 15:55);
   - PP2 Lengkap (#11, 16:29);
   - ACCEPTED (#4, 05/11 16:30).

   Semua rekod konsisten dan sejarah terdahulu kekal. Ini ialah **bukti pemerhatian** bahawa aliran berfungsi pada MySQL 8.0.46. Ia **bukan** pengesahan MySQL terkawal (Seksyen 9).
2. **Tarikh keputusan diundur sehari:** keputusan #2 (permohonan 9) dan #3 (permohonan 7) bertarikh 04/11 tetapi direkodkan pada 05/11. Ini dibenarkan oleh peraturan (≤ hari ini, ≥ tarikh PP2 Lengkap).
3. **Permohonan ID 6 diterima untuk mesyuarat yang telah berlalu:** keputusan ACCEPTED (04/11/2026) direkodkan dengan Mesyuarat 1, yang tidak aktif dan bertarikh 23/01/2026. Lihat isu **PP42B-01**.
4. **Permohonan ID 2 dan 3:** berstatus SUBMITTED tanpa Mesyuarat JTICT (data lama). Jika ditetapkan Lengkap, kedua-duanya akan masuk Perlu Semakan Manual. Lihat **PP42B-05**.

---

## 8. Keputusan ujian automatik SQLite

### 8.1 Fail ujian berkaitan (larian 11/11/2026)

| Fail ujian | Keputusan | Lulus | Gagal | Ralat | Risky | Assertions |
|---|---|---|---|---|---|---|
| PreJtictDisplayTest | Lulus | 8/8 | 0 | 0 | 0 | 49 |
| PreJtictDecisionTest | Lulus | 15/15 | 0 | 0 | 0 | 180 |
| ApplicationDecisionTest | Lulus | 24/24 | 0 | 0 | 0 | 299 |
| ApplicationReviewTest | Lulus | 18/18 | 0 | 0 | 0 | 163 |
| OnBehalfApplicationTest | Lulus | 16/16 | 0 | 0 | 0 | 172 |
| ApplicationOfficerTest | Lulus | 18/18 | 0 | 0 | 0 | 98 |
| PenggunaDashboardTest | Lulus | 6/6 | 0 | 0 | 0 | 33 |
| UrusSetiaDashboardTest | Lulus | 7/7 | 0 | 0 | 0 | 42 |
| **Jumlah** | **Lulus** | **112/112** | 0 | 0 | 0 | 1,036 |

### 8.2 Suite penuh

| Larian | Keputusan |
|---|---|
| 1 | 504/504 lulus, 3,873 assertions, 1 *risky* |
| 2 (dengan log JUnit) | 504/504 lulus, 0 gagal, 0 ralat |

Ujian *risky*: `UserRoleManagementTest::test_urus_setia_cannot_open_superadmin_self_or_pending_users` tidak membuat sebarang assertion. Ujian ini berada di modul Pengurusan Pengguna, di luar skop PP4, dan sudah wujud sebelum PP4.2.

### 8.3 Ujian muat naik dokumen (kegagalan rawak PP4.2A)

| Fail | Larian ulangan | Keputusan |
|---|---|---|
| ApplicationDocumentTest | 10 | 10/10 lulus (12/12 setiap larian) |
| ApplicationFormDesignTest | 10 | 10/10 lulus (5/5 setiap larian) |

- Kegagalan PP4.2A (`test_proposal_paper_accepts_pdf_and_docx` dan `test_replacing_a_document_soft_deletes_the_old_one_and_keeps_its_file`) **tidak dapat dihasilkan semula** dalam 20 larian dan 2 suite penuh.
- Kedua-dua ujian yang pernah gagal menggunakan fail `.docx`.
- Pengesahan `.docx` (`app/Rules/DocumentSignature.php`) bergantung pada tiga operasi fail sementara Windows: `tempnam()`, `copy()` dan `ZipArchive::open()`.
- Hipotesis (belum dibuktikan): fail sementara dikunci seketika, contohnya oleh pengimbas antivirus Windows.
- 97 fail `doc*.tmp` ditemui tertinggal dalam folder TEMP pengguna. Pembantu ujian `docx()` tidak memadam fail `tempnam()`nya. Di Windows, awalan `tempnam` dihadkan kepada 3 aksara, jadi fail pembantu ujian dan salinan rule tidak dapat dibezakan.
- Status: **Lulus dengan Catatan**. Lihat **PP42B-03**.

### 8.4 Liputan ujian automatik berbanding senario UAT

| Senario | Diliputi oleh ujian automatik (SQLite) |
|---|---|
| D1 Senarai menunggu & Semakan Manual | `PreJtictDecisionTest::test_list_shows_only_applications_awaiting_pre_jtict_decision`, `PreJtictDisplayTest::test_complete_label_distinguishes…` |
| D2 Diterima | `test_accepted_decision_moves_application_to_in_jtict_with_optional_remarks`, ujian tarikh |
| D3 Ditolak dengan Pindaan | `test_amendment_decision_requires_remarks`, `test_full_amendment_cycle_through_ui_keeps_identity_and_history`, ujian tanda penghantaran semula |
| D4 Ditolak | `test_rejected_decision_closes_application_and_blocks_amendment` |
| D5 Sejarah | Ujian paparan butiran (nama perekod disembunyikan daripada pemohon), ujian tiada edit/padam |
| D6 Kawalan akses | `test_only_active_urus_setia_can_record_pre_jtict_decision` (superadmin, pengurusan, pengguna_biasa, Urus Setia + superadmin, Urus Setia tidak aktif). Gabungan Urus Setia + pengurusan **tidak** diuji secara khusus; ia hanya dilindungi oleh logik polisi yang sama. |
| D7 Tanpa mesyuarat | Ujian polisi dan bahagian Perlu Semakan Manual |

Liputan automatik **tidak** menggantikan UAT.

---

## 9. Keputusan pengesahan MySQL

| Perkara | Status |
|---|---|
| C1 – Pangkalan data ujian MySQL berasingan | **Tiada.** `SHOW DATABASES` hanya menyenaraikan `information_schema`, `performance_schema` dan `sppictv2_db`. |
| C2 – Semua 10 perkara pengesahan | **Belum Diuji** |

Fasa C dihentikan mengikut arahan C1. Tiada pangkalan data dicipta dan tiada konfigurasi sambungan diubah. `sppictv2_db` tidak digunakan untuk ujian MySQL.

**Keperluan untuk melaksanakan Fasa C (memerlukan kelulusan pemilik):**
1. Cipta pangkalan data berasingan, contohnya `sppictv2_test`, pada pelayan MySQL 8.0.46 yang sama atau berasingan.
2. Sediakan konfigurasi ujian yang menunjuk kepada DB tersebut tanpa mengubah `.env`. Contohnya, fail `phpunit.mysql.xml` berasingan atau `.env.testing` dengan `DB_DATABASE=sppictv2_test`. Pastikan nilai ini **tidak** boleh jatuh kembali kepada `sppictv2_db`.
3. Jalankan `migrate` pada DB ujian sahaja. Ujian menggunakan `RefreshDatabase` dan akan memadam semua data DB yang digunakan, jadi DB itu mestilah DB ujian khusus.
4. Jalankan 8 fail ujian berkaitan dengan konfigurasi MySQL. Fokus kepada:
   - subquery `last_resubmitted_from`;
   - `withExists` dengan syarat OR berkumpulan;
   - `lockForUpdate` dan transaksi dalam `ApplicationDecisionService`;
   - pengendalian `decision_date` (jenis `date`) berbanding zon masa.

---

## 10. Pelan UAT dan keputusan

**Prasyarat umum:**
- Persekitaran UAT berasingan (DB ujian) atau rekod ujian yang diluluskan oleh pemilik sistem.
- Akaun ujian (berdasarkan DB pembangunan semasa):
  - hanya **1** Urus Setia aktif;
  - **tiada** Urus Setia tidak aktif;
  - **tiada** pengguna berbilang peranan.
- Akaun tambahan diperlukan untuk D6.
- Mesyuarat JTICT aktif yang masih dibuka.

**Bukti yang perlu disimpan:**
- tangkapan skrin (tarikh dan masa kelihatan);
- no. rujukan permohonan;
- ID rekod `application_decisions` atau `application_reviews` daripada semakan pangkalan data UAT.

Status semua senario: **Belum Diuji**. Tiada UAT dilaksanakan oleh pengguna dalam PP4.2B.

### D1 – Senarai menunggu Keputusan Pra JTICT (Urus Setia)

| ID UAT | Objektif | Prasyarat | Langkah | Hasil dijangka | Status |
|---|---|---|---|---|---|
| UAT-D1-01 | Permohonan layak dipaparkan | Permohonan COMPLETE melalui PP2, ada Mesyuarat JTICT | Buka menu **Pra JTICT** | Permohonan dipaparkan dengan label "Lengkap – Menunggu Keputusan Pra JTICT", mesyuarat dan tarikh Lengkap | Belum Diuji |
| UAT-D1-02 | Tanpa mesyuarat tidak dalam senarai biasa | Permohonan COMPLETE tanpa mesyuarat (data lama) | Buka **Pra JTICT** | Tiada dalam jadual utama. Dipaparkan di **Perlu Semakan Manual** dengan label "Lengkap". | Belum Diuji |
| UAT-D1-03 | Keputusan muktamad tidak menunggu | Permohonan sudah Diterima atau Ditolak | Buka **Pra JTICT** | Tidak dipaparkan sebagai menunggu | Belum Diuji |
| UAT-D1-04 | Tidak layak → Perlu Semakan Manual | COMPLETE tanpa rekod PP2 Lengkap, atau ada keputusan muktamad (data lama) | Buka **Pra JTICT** | Dipaparkan di Perlu Semakan Manual sahaja. Tiada butang keputusan. | Belum Diuji |
| UAT-D1-05 | Tanda penghantaran semula | Permohonan dihantar semula selepas (a) Tidak Lengkap PP2, (b) Pindaan Pra JTICT | Senarai Permohonan untuk Semakan (penapis Dihantar) | (a) "Dihantar semula selepas pindaan" (biru). (b) "Dihantar semula selepas Pindaan Pra JTICT" (oren). Permohonan pertama kali dihantar tiada tanda. | Belum Diuji |

### D2 – Keputusan Diterima (`pentadbir_urus_setia`)

| ID UAT | Objektif | Prasyarat | Langkah | Hasil dijangka | Status |
|---|---|---|---|---|---|
| UAT-D2-01 | Rekod Diterima tanpa ulasan | UAT-D1-01 | Semak & Putuskan → **Diterima** → isi tarikh hari ini → Sahkan | Status `IN_JTICT`; label "Diterima – Menunggu Mesyuarat JTICT"; sejarah dipaparkan | Belum Diuji |
| UAT-D2-02 | Tarikh wajib | Seperti di atas | Kosongkan tarikh → Sahkan | Ralat "Tarikh keputusan wajib diisi."; tiada rekod | Belum Diuji |
| UAT-D2-03 | Tarikh masa depan ditolak | Seperti di atas | Masukkan tarikh esok | Ralat; tiada rekod | Belum Diuji |
| UAT-D2-04 | Tarikh sebelum PP2 Lengkap ditolak | Seperti di atas | Masukkan tarikh sebelum tarikh Lengkap | Ralat menyebut tarikh Lengkap; tiada rekod | Belum Diuji |
| UAT-D2-05 | Diterima ≠ Lulus JTICT | UAT-D2-01 | Semak papan pemuka pemohon dan kad "Permohonan Projek Lulus" | Kiraan Lulus tidak bertambah; tiada keputusan JTICT dipaparkan | Belum Diuji |

### D3 – Keputusan Ditolak dengan Pindaan

| ID UAT | Objektif | Peranan | Langkah | Hasil dijangka | Status |
|---|---|---|---|---|---|
| UAT-D3-01 | Ulasan wajib | Urus Setia | Pilih **Ditolak dengan Pindaan**, ulasan kosong → Sahkan | Ralat ulasan wajib; tiada rekod | Belum Diuji |
| UAT-D3-02 | Rekod pindaan | Urus Setia | Isi ulasan (≥ 10 aksara) dan tarikh → Sahkan | Status `PRE_JTICT_AMENDMENT`; label "Ditolak dengan Pindaan – Menunggu Tindakan Pemohon" | Belum Diuji |
| UAT-D3-03 | Pemohon melihat ulasan | Pegawai Utama aktif | Buka permohonan dan papan pemuka | Notis oren dengan ulasan Urus Setia; butang "Kemas Kini & Hantar Semula" | Belum Diuji |
| UAT-D3-04 | Pendaftar bukan pegawai aktif papar sahaja | Pendaftar / pegawai asal | Buka permohonan | Ulasan kelihatan; tiada butang kemas kini (peraturan 05/11/2026) | Belum Diuji |
| UAT-D3-05 | Hantar semula | Pegawai Utama aktif | Pinda dan hantar semula | Status `SUBMITTED`; rekod "Dihantar Semula" dalam sejarah semakan; **no. rujukan dan URL (UUID) tidak berubah** | Belum Diuji |
| UAT-D3-06 | Tanda dan sejarah kekal | Urus Setia | Senarai semakan dan halaman butiran | Tanda oren; keputusan Pindaan terdahulu kekal dalam Sejarah Keputusan Pra JTICT | Belum Diuji |
| UAT-D3-07 | Kitaran semula | Urus Setia | PP2 Lengkap → muncul semula dalam Pra JTICT → Diterima | Dua rekod keputusan, terkini di atas | Belum Diuji |

### D4 – Keputusan Ditolak

| ID UAT | Objektif | Langkah | Hasil dijangka | Status |
|---|---|---|---|---|
| UAT-D4-01 | Rekod Ditolak tanpa ulasan | Pilih **Ditolak**, isi tarikh → Sahkan | Status `CLOSED`; label "Selesai – Ditolak (Pra JTICT)" | Belum Diuji |
| UAT-D4-02 | Tiada pindaan | Log masuk sebagai Pegawai Utama → buka permohonan | Tiada butang kemas kini; URL edit memberi 403 | Belum Diuji |
| UAT-D4-03 | Sejarah boleh dilihat | Pemohon dan Urus Setia buka butiran | Sejarah keputusan dipaparkan mengikut kebenaran | Belum Diuji |

### D5 – Sejarah keputusan

| ID UAT | Objektif | Langkah | Hasil dijangka | Status |
|---|---|---|---|---|
| UAT-D5-01 | Turutan dan butiran | Buka permohonan dengan ≥ 2 keputusan | Terkini di atas (tag "Terkini"); tarikh keputusan, tarikh direkodkan dan ulasan (atau "Tiada ulasan.") betul | Belum Diuji |
| UAT-D5-02 | Nama perekod | Bandingkan paparan Urus Setia dengan paparan pemohon | Urus Setia melihat nama; pemohon melihat "Urus Setia" sahaja | Belum Diuji |
| UAT-D5-03 | Tiada edit/padam | Semak UI | Tiada butang edit atau padam pada rekod sejarah | Belum Diuji |

### D6 – Kawalan akses

| ID UAT | Peranan | Langkah | Hasil dijangka | Status |
|---|---|---|---|---|
| UAT-D6-01 | `pentadbir_urus_setia` aktif | Buka Pra JTICT dan buat keputusan | Dibenarkan | Belum Diuji |
| UAT-D6-02 | `superadmin` | Buka `/urus-setia/pra-jtict` dan butiran permohonan | 403 untuk senarai; tiada butang keputusan | Belum Diuji |
| UAT-D6-03 | `pengurusan` | Seperti di atas | 403; tiada butang keputusan | Belum Diuji |
| UAT-D6-04 | `pengguna_biasa` | Seperti di atas | 403; tiada butang keputusan | Belum Diuji |
| UAT-D6-05 | Urus Setia tidak aktif | Cuba log masuk | Akses ditolak (log keluar / mesej akaun tidak aktif) | Belum Diuji – **akaun ujian tiada** |
| UAT-D6-06 | Urus Setia + superadmin (atau + pengurusan) | Buka Pra JTICT | Ditolak (polisi mengecualikan peranan berganda ini) | Belum Diuji – **akaun ujian tiada** |

### D7 – Permohonan tanpa mesyuarat

| ID UAT | Objektif | Langkah | Hasil dijangka | Status |
|---|---|---|---|---|
| UAT-D7-01 | Tiada butang/modal | Buka butiran permohonan COMPLETE tanpa mesyuarat | Kad keputusan Pra JTICT tidak dipaparkan | Belum Diuji |
| UAT-D7-02 | Backend menolak | (Penguji teknikal) panggilan terus kepada tindakan Livewire | Ditolak (403 / ralat kebenaran); tiada rekod | Belum Diuji |
| UAT-D7-03 | Perlu Semakan Manual | Buka Pra JTICT | Dipaparkan di bahagian Perlu Semakan Manual | Belum Diuji |
| UAT-D7-04 | Tiada proses automatik | Semak status selepas beberapa hari atau tindakan lain | Status kekal COMPLETE | Belum Diuji |

Prosedur bagi permohonan tanpa mesyuarat **belum ditetapkan**. Perkara ini memerlukan keputusan pemilik sistem (PP42B-05).

---

## 11. Semakan visual

| Paparan | Desktop | Tablet | Mudah alih |
|---|---|---|---|
| Senarai Pra JTICT | Belum Diuji | Belum Diuji | Belum Diuji |
| Perlu Semakan Manual | Belum Diuji | Belum Diuji | Belum Diuji |
| Modal keputusan | Belum Diuji | Belum Diuji | Belum Diuji |
| Sejarah keputusan | Belum Diuji | Belum Diuji | Belum Diuji |
| Notis Pindaan Pra JTICT | Belum Diuji | Belum Diuji | Belum Diuji |
| Tanda penghantaran semula | Belum Diuji | Belum Diuji | Belum Diuji |
| Dashboard Pemohon | Belum Diuji | Belum Diuji | Belum Diuji |
| Dashboard Urus Setia | Belum Diuji | Belum Diuji | Belum Diuji |

**Sebab Belum Diuji:**
1. Persekitaran agent tiada pelayar atau alat tangkapan skrin.
2. Log masuk ke aplikasi pembangunan akan menulis ke `sppictv2_db`:
   - jadual `sessions` (`SESSION_DRIVER=database`);
   - `users.last_login_at` (listener `RecordLastLogin`);
   - rekod audit.

   Ini bertentangan dengan peraturan baca sahaja fasa ini.

**Semakan statik (bukan pengganti semakan visual):**
- Jadual Pra JTICT (`min-w-[900px]`) dibalut `overflow-x-auto`, jadi tatal mendatar berlaku dalam jadual sahaja. Senarai permohonan juga dibalut `overflow-x-auto`.
- Lencana dengan label terperinci menggunakan `whitespace-normal` (PP4.2A) dan sepatutnya berbalut. Perlu disahkan secara visual.
- Modal (`view-application.blade.php` baris 343, 405 dan 444): lapisan `fixed inset-0 … flex items-center justify-center` **tiada padding luar**, dan kotak modal `w-full max-w-lg`. Pada skrin mudah alih, modal mungkin rapat ke tepi skrin. Ketinggian dihadkan `max-h-[90vh] overflow-y-auto`. Lihat PP42B-07.
- Aset CSS dibina dalam PP4.2A (`app-BS7dg-7U.css`). `npm run build` tidak dijalankan dalam PP4.2B.

---

## 12. Senarai isu dan risiko

Tiada isu **Kritikal** atau **Tinggi** ditemui.

| ID | Kategori | Penerangan | Langkah hasilkan semula | Bukti | Fail / komponen | Kesan | Cadangan |
|---|---|---|---|---|---|---|---|
| PP42B-01 | Sederhana (keputusan pemilik; prasyarat PP4.3) | Keputusan Pra JTICT tidak menyemak sama ada Mesyuarat JTICT permohonan masih aktif atau akan datang. Permohonan boleh "Diterima – Menunggu Mesyuarat JTICT" untuk mesyuarat yang telah berlalu. `validateJtict` (PP4.3) hanya mensyaratkan tarikh ≥ tarikh mesyuarat. | Permohonan COMPLETE dengan mesyuarat tidak aktif atau lepas → Diterima | Data: permohonan ID 6, keputusan #1 (04/11/2026), Mesyuarat 1 (`is_active=0`, tarikh 23/01/2026) | `ApplicationDecisionService::validatePreJtict()` / `validateJtict()`, `Application::statusDisplayLabel()` | Label mengelirukan; keputusan JTICT kelak boleh direkod untuk mesyuarat yang berlangsung sebelum keputusan Pra JTICT | Pemilik tetapkan peraturan sebelum PP4.3: (a) sekat Pra JTICT jika mesyuarat sudah berlalu, (b) benarkan pemindahan ke mesyuarat baharu, atau (c) terima dan dokumentasikan. Tentukan juga tindakan bagi permohonan ID 6. |
| PP42B-02 | Sederhana | Tiada pengesahan MySQL terkawal; tiada DB ujian berasingan | — | `SHOW DATABASES` (Seksyen 9) | Konfigurasi ujian | Perbezaan SQLite/MySQL (kunci baris, subquery, tarikh) belum dibuktikan | Luluskan penyediaan `sppictv2_test` dan konfigurasi ujian MySQL berasingan (Seksyen 9) |
| PP42B-03 | Sederhana | Kegagalan rawak ujian muat naik `.docx` (PP4.2A) tidak dapat dihasilkan semula; punca belum dibuktikan. Fail sementara tertinggal. | Jalankan suite penuh berulang kali | Seksyen 8.3; 97 fail `doc*.tmp` dalam TEMP | `app/Rules/DocumentSignature.php`, `tests/Feature/ApplicationDocumentTest.php::docx()` | Ujian tidak stabil; jika berpunca daripada penguncian fail, pengguna sebenar mungkin sekali-sekala menerima ralat "format tidak sah" bagi `.docx` yang sah | Pantau. Pertimbangkan log sebab kegagalan dalam rule dan memadam fail `tempnam` dalam pembantu ujian (perlu kelulusan) |
| PP42B-04 | Sederhana | UAT D6 tidak dapat dilaksanakan dengan akaun sedia ada | — | DB: 1 Urus Setia aktif; tiada Urus Setia tidak aktif; tiada pengguna berbilang peranan | Data ujian | Kawalan akses peranan berganda dan akaun tidak aktif hanya diuji secara automatik | Sediakan akaun ujian dalam persekitaran UAT berasingan |
| PP42B-05 | Sederhana (keputusan pemilik) | Prosedur bagi permohonan tanpa mesyuarat / Perlu Semakan Manual belum ditetapkan (dibawa daripada PP4.2) | Tetapkan permohonan ID 2/3 Lengkap (dalam UAT) | Permohonan 2 dan 3: SUBMITTED, `jtict_meeting_id` NULL | `scopeNeedsManualPreJtictReview`, `decidePreJtict` | Permohonan boleh tersangkut tanpa laluan rasmi | Pemilik tetapkan prosedur (contohnya tetapkan mesyuarat secara manual melalui fungsi rasmi) |
| PP42B-06 | Rendah | Ulasan pilihan (Diterima / Ditolak), jika diisi, masih perlu ≥ 10 aksara | Isi ulasan "OK" bagi Diterima | `REMARKS_MIN = 10` | `ApplicationDecisionService` | UX: pengguna mungkin keliru | Pemilik putuskan sama ada had minimum dikekalkan untuk ulasan pilihan |
| PP42B-07 | Rendah | Modal tiada padding luar pada skrin kecil (risiko, belum disahkan secara visual) | Buka modal pada lebar ≤ 400px | Semakan statik (Seksyen 11) | `view-application.blade.php` baris 343, 405, 444 | Kosmetik | Sahkan secara visual dahulu; jika benar, tambah padding pada lapisan modal |
| PP42B-08 | Rendah | Ujian *risky* tanpa assertion | Jalankan suite penuh | Log JUnit: `assertions="0"` | `UserRoleManagementTest::test_urus_setia_cannot_open_superadmin_self_or_pending_users` | Ujian tidak membuktikan apa-apa | Tambah assertion (modul Pengguna, di luar PP4) |
| PP42B-09 | Rendah (dokumentasi) | `docs/AGENTS.md` bercanggah dengan pelaksanaan: matriks RBAC memberi superadmin "Urus Penuh" untuk Keputusan dan menyatakan Kertas Cadangan PDF sahaja | — | `docs/AGENTS.md` Seksyen 1.4 dan 5 | Dokumentasi | Ejen AI atau pembangun lain mungkin mengikut panduan yang salah | Kemas kini AGENTS.md mengikut keputusan PP4 (superadmin tidak membuat keputusan) dan format dokumen semasa |
| PP42B-10 | Rendah (dokumentasi) | Peraturan "hanya Pegawai Utama/Kedua aktif boleh meminda selepas dihantar" (05/11/2026) belum didokumenkan dalam laporan pelaksanaan rasmi | — | Laporan PP4.2A Seksyen 8 item 6 | Dokumentasi | Jejak keputusan dasar tidak lengkap | Dokumentasikan dalam laporan berasingan atau laporan PP4.3 |

---

## 13. Batasan pengesahan

1. Semua ujian automatik dijalankan pada **SQLite in-memory**. Tingkah laku MySQL (kunci baris `lockForUpdate`, keserentakan sebenar dan jenis `date`) **tidak** dibuktikan oleh ujian.
2. Bukti pemerhatian MySQL (kitaran permohonan ID 7) datang daripada penggunaan pemilik, bukan ujian terkawal. Ia tidak menggantikan Fasa C.
3. Tiada UAT oleh pengguna dan tiada semakan visual dilaksanakan.
4. Ujian keserentakan (dua Urus Setia serentak) hanya disimulasikan secara berurutan dalam ujian automatik.
5. Audit dibuat ke atas working tree yang mengandungi perubahan belum di-commit. Keputusan mungkin berbeza jika perubahan pemilik (RichText, borang) diubah selepas ini.

---

## 14. Cadangan tindakan seterusnya

1. **Pemilik sistem:** putuskan PP42B-01 (mesyuarat yang telah berlalu) dan PP42B-05 (prosedur Perlu Semakan Manual) sebelum reka bentuk keputusan JTICT PP4.3.
2. **Pemilik sistem:** luluskan penyediaan DB ujian MySQL berasingan. Kemudian jalankan Fasa C (Seksyen 9).
3. **Pemilik sistem:** laksanakan UAT D1–D7 dalam persekitaran UAT dengan akaun ujian (PP42B-04), dan semakan visual pada tiga saiz skrin. Rekod bukti.
4. Commit kerja PP4.2/PP4.2A yang telah disemak (atas keputusan pemilik) supaya garis dasar PP4.3 jelas.
5. Pertimbangan kemudian (perlu kelulusan): PP42B-03, PP42B-06, PP42B-07, PP42B-08, PP42B-09 dan PP42B-10.

---

## 15. Keputusan kesediaan untuk PP4.3

| Kriteria | Status |
|---|---|
| Struktur dan integriti data | Lulus |
| Ujian automatik SQLite | Lulus dengan Catatan |
| Pengesahan MySQL | Belum Diuji |
| UAT | Belum Diuji |
| Semakan visual | Belum Diuji |
| Isu Kritikal / Tinggi | Tiada |

**Keputusan: SEDIA BERSYARAT.**
- PP4.3 (keputusan JTICT) boleh **dimulakan dari segi pembangunan**, kerana struktur PP4.1B dan aliran Pra JTICT stabil serta tiada isu Kritikal atau Tinggi.
- Syarat sebelum PP4.3 dimulakan:
  1. Pemilik sistem memutuskan **PP42B-01**, kerana peraturan mesyuarat JTICT secara langsung mempengaruhi logik keputusan JTICT.
- Syarat sebelum PP4.2 dianggap **disahkan sepenuhnya** dan sedia untuk produksi:
  1. Fasa C (MySQL) dilaksanakan.
  2. UAT D1–D7 dilaksanakan.
  3. Semakan visual direkodkan.
