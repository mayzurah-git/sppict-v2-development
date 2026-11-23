# Laporan Audit dan Pengesahan PP4.2B – Fungsi Keputusan Pra JTICT (Larian 2)

**Sistem:** SPPICT v2.1
**Tarikh audit:** 16/11/2026
**Cawangan:** `feature/ui-design` (commit terakhir `543829a`; tiada commit dibuat)
**Jenis fasa:** Audit dan pengesahan sahaja. Tiada perubahan pada kod, konfigurasi atau data pembangunan.
**Rujukan:** Laporan PP4.1B, PP4.2, PP4.2A dan *Laporan Audit dan Pengesahan PP4.2B* (larian 1, 11/11/2026)

> **Nota lokasi:** Laporan larian 1 (`docs/Laporan Audit dan Pengesahan PP4.2B.md`, 11/11/2026) sudah wujud. Ia **tidak ditimpa**. Laporan ini disimpan sebagai fail berasingan dan mengesahkan semula semua dapatan pada 16/11/2026.
>
> **Nota workspace:** Arahan dijalankan dari workspace `sppict-v2` (repo v2.0, satu commit, tiada kod PP4). Kod PP4.1B–PP4.2A berada dalam `C:\Users\mayzurah\Herd\www\sppict-v2.1`. Audit ini dibuat ke atas projek **sppict-v2.1**.

---

## 1. Ringkasan eksekutif

| Bahagian | Status | Ringkasan |
|---|---|---|
| A – Git, konfigurasi, struktur & integriti | **Lulus** | Kod sama persis dengan larian 1 (hash diff Git sepadan). Struktur sepadan dengan PP4.1B. Tiada rekod yatim. Data PP4 tidak berubah sejak 05/11/2026 dan tidak berubah sepanjang audit. |
| B – Ujian automatik SQLite | **Lulus dengan Catatan** | 8 fail berkaitan: 112/112 lulus. Suite penuh: 504/504 lulus (2 larian), 1 *risky* di luar skop. Ujian dokumen 20/20 larian lulus. Kebocoran fail sementara disahkan. |
| C – Pengesahan MySQL | **Belum Diuji** | Masih tiada pangkalan data ujian MySQL berasingan. Fasa dihentikan mengikut C1. |
| D – UAT | **Belum Diuji** | Pelan UAT lengkap (33 senario) disediakan semula. Tiada UAT dilaksanakan oleh pengguna. |
| E – Semakan visual | **Belum Diuji** | Tiada alat pelayar dalam persekitaran agent. Log masuk akan menulis ke `sppictv2_db`. Semakan statik sahaja. |
| F – Isu & risiko | Selesai | 0 Kritikal, 0 Tinggi, 5 Sederhana, 6 Rendah (1 isu baharu: PP42B-11). PP42B-01 kini **sensitif masa** (Mesyuarat JTICT Bil. 5 pada 23/11/2026). |

**Kesimpulan:** Tiada perubahan kod atau data sejak larian 1. Semua dapatan larian 1 disahkan semula. Fungsi Keputusan Pra JTICT lulus semua ujian automatik pada SQLite dan data pembangunan konsisten. PP4.2B **belum** disahkan sepenuhnya kerana pengesahan MySQL, UAT dan semakan visual masih Belum Diuji. PP4.3 boleh dimulakan **bersyarat** (Seksyen 15).

---

## 2. Skop audit

- Senarai menunggu Keputusan Pra JTICT dan bahagian Perlu Semakan Manual (`PreJtictList`).
- Paparan butiran dan keputusan Diterima, Ditolak dengan Pindaan dan Ditolak (`ViewApplication`).
- Sejarah keputusan, kitaran pindaan dan penghantaran semula.
- Kawalan akses (`ApplicationPolicy`), label status (`Application::statusDisplayLabel()`) dan tanda penghantaran semula (`ApplicationList`).
- Transaksi `ApplicationDecisionService::decide()`.

Di luar skop: keputusan JTICT (PP4.3), Salin Permohonan, Kemajuan Projek dan e-mel keputusan.

---

## 3. Peraturan keselamatan yang dipatuhi

| Peraturan | Pematuhan | Bukti |
|---|---|---|
| Tiada perubahan kod, Blade, CSS, JS atau konfigurasi | Dipatuhi | Hash `git diff` dan hash fail *untracked* sama sebelum dan selepas (Seksyen 4) |
| Tiada migration, seeder, `npm run build`, `composer/npm update` | Dipatuhi | Migrasi kekal 45, batch 24 |
| Hanya SELECT / SHOW / DESCRIBE / EXPLAIN pada `sppictv2_db` | Dipatuhi | Skrip audit mempunyai pengawal yang menolak pernyataan lain, dan sesi ditetapkan `SET SESSION TRANSACTION READ ONLY` (`@@transaction_read_only = 1`). Pengawal pernah menyekat `SHOW CREATE TABLE` kerana kata kunci "CREATE"; hanya corak tepat `SHOW CREATE TABLE \`nama\`` kemudian dibenarkan. |
| Tiada perubahan `.env`, DB, SMTP, queue | Dipatuhi | `.env` dibaca untuk kunci bukan rahsia sahaja. Kelayakan DB dibaca oleh skrip tetapi tidak dipaparkan. |
| Tiada operasi Git yang mengubah keadaan | Dipatuhi | Hanya `git status`, `git diff`, `git log`, `git ls-files`, `git branch --show-current` dan `git hash-object` |
| Ujian tidak menyentuh DB pembangunan | Dipatuhi | `phpunit.xml` → SQLite `:memory:`; tiada `.env.testing`; tiada pembolehubah OS `DB_*`; **tiada `bootstrap/cache/config.php`** (cache konfigurasi akan mengabaikan env PHPUnit); tiada ujian yang menukar sambungan. Snapshot DB sebelum dan selepas sama. |
| Tiada pembaikan automatik | Dipatuhi | Semua isu hanya didokumenkan |

Fail audit sementara (skrip baca sahaja, log JUnit, snapshot) disimpan dalam scratchpad sesi agent, di luar projek.

**Kesan sampingan yang perlu dimaklumkan:** larian ulangan `ApplicationDocumentTest` meninggalkan **30 fail `doc*.tmp` tambahan** dalam folder `%TEMP%` pengguna (42 → 72). Ini tingkah laku ujian sedia ada (PP42B-03), di luar projek dan pangkalan data. Fail tersebut tidak dipadam oleh agent.

---

## 4. Keadaan Git sebelum dan selepas audit

| Perkara | Larian 1 (11/11) | Sebelum (16/11) | Selepas (16/11) |
|---|---|---|---|
| Cawangan / commit | `feature/ui-design` / `543829a` | Sama | Sama |
| Fail diubah (belum di-commit) | 43 (1,700 tambah / 780 buang) | 43 | 43 |
| Fail *untracked* | 11 + laporan larian 1 | 12 | 12 + laporan ini |
| Hash `git diff` | `b72a03c2ce5ed97ef488eca512d6bc3491bd6140` | `b72a03c2…6140` | `b72a03c2…6140` |
| Hash fail *untracked* (tanpa laporan PP4.2B) | `bdabb4c120ec65f528106472186b499d33e7f19b` | `bdabb4c1…19b` | `bdabb4c1…19b` |

Kesimpulan: kod tidak berubah sejak larian 1. Fail terakhir diubah pada 05/11/2026 16:18. Perubahan pemilik sistem yang belum di-commit (RichText, `config/purifier.php`, `resources/css/app.css`, komponen borang, `ApplicationFormDesignTest` dan lain-lain) kekal tanpa sentuhan. Satu-satunya fail baharu ialah laporan ini.

---

## 5. Semakan konfigurasi

| Perkara | Nilai |
|---|---|
| PHP | 8.4.27 (CLI, NTS x64) |
| Laravel | 13.23.0 |
| Livewire / Volt | 3.8.3 / 1.11.2 |
| PHPUnit | 12.5.33 |
| spatie/laravel-permission / owen-it/laravel-auditing | 8.3.0 / 14.0.6 |
| MySQL (pembangunan) | 8.0.46 |
| Sambungan aktif aplikasi | `mysql`, pangkalan data `sppictv2_db` |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` (pembangunan; mesti `false` di produksi) |
| Zon masa MySQL | `SYSTEM` (global dan sesi) |
| Pemacu sesi / queue / cache | `database` / `database` / `database` |
| `phpunit.xml` | `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL=""`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `CACHE_STORE=array` |
| `.env.testing` | Tiada |
| `bootstrap/cache/config.php` | Tiada (hanya `packages.php`, `services.php`) |

**Kesimpulan:** ujian automatik menggunakan SQLite in-memory dan tidak boleh menulis ke `sppictv2_db`.

**Amaran untuk masa depan:** jika `php artisan config:cache` dijalankan dalam persekitaran ini, PHPUnit akan menggunakan konfigurasi MySQL yang di-cache, dan `RefreshDatabase` akan **memadam semua data `sppictv2_db`**. Jangan jalankan ujian selepas `config:cache` tanpa `config:clear`.

---

## 6. Struktur pangkalan data

### 6.1 Semakan wajib (A3)

| # | Semakan | Keputusan | Bukti |
|---|---|---|---|
| 1 | `application_decisions` mempunyai UUID | **Lulus** | `uuid char(36) NOT NULL`, `UNIQUE KEY application_decisions_uuid_unique`. 0 UUID kosong, 0 berganda. |
| 2 | `application_decisions` tiada `updated_at` | **Lulus** | Hanya `created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| 3 | Foreign key NO ACTION | **Lulus** | `application_id`, `decided_by` dan `meeting_id`: `UPDATE_RULE = DELETE_RULE = NO ACTION`. `application_reviews` (3 FK) dan `applications.copied_from_application_id` / `jtict_meeting_id` juga NO ACTION. |
| 4 | `applications.copied_from_application_id` nullable | **Lulus** | `bigint unsigned`, `IS_NULLABLE=YES`, FK ke `applications.id` |
| 5 | `applications.jtict_decision` dikekalkan (legacy) | **Lulus** | `enum('PENDING','APPROVED','APPROVED_CONDITIONAL','REVIEW','REJECTED') NOT NULL DEFAULT 'PENDING'`; `jtict_remarks text NULL` kekal |
| 6 | Tiada perubahan struktur tidak dijangka | **Lulus** | Migrasi terakhir: `2026_11_03_000001` dan `2026_11_03_000002` (PP4.1B), batch 24. Jumlah 45. Sama dengan larian 1. |

### 6.2 Struktur `application_decisions`

`id`, `uuid` char(36), `application_id`, `stage` varchar(20), `decision` varchar(30), `from_status` varchar(30), `to_status` varchar(30), `remarks` text NULL, `decision_date` date, `meeting_id` NULL, `decided_by`, `created_at`.

Indeks: `(application_id, stage, created_at)`, `(stage, decision)`, `meeting_id`, `decided_by`, `uuid` (unik).

`applications`, `application_reviews`, `meetings` dan `users` disemak melalui `SHOW CREATE TABLE` penuh. Semua mempunyai `uuid` unik. `applications`, `meetings` dan `users` mempunyai `deleted_at` (SoftDeletes). `application_reviews` tiada `updated_at` (sejarah tidak berubah).

---

## 7. Integriti rekod pembangunan

### 7.1 Snapshot sebelum dan selepas audit

| Perkara | Larian 1 (11/11) | Sebelum (16/11) | Selepas (16/11) |
|---|---|---|---|
| applications | 8 | 8 | 8 |
| application_reviews | 11 | 11 | 11 |
| application_decisions | 4 | 4 | 4 |
| meetings | 2 | 2 | 2 |
| users | 13 | 13 | 13 |
| jobs / failed_jobs | 0 / 0 | 0 / 0 | 0 / 0 |
| audits (id maks) | 393 | 393 | 393 |
| Migrasi (jumlah / batch) | 45 / 24 | 45 / 24 | 45 / 24 |
| Kemas kini terakhir `applications` / keputusan / semakan | — | 05/11 16:30 / 05/11 16:30 / 05/11 16:29 | Sama |
| Hash `applications` | * | `0bccb5b1…438e` | `0bccb5b1…438e` |
| Hash `application_reviews` | * | `e2dfddce…88f6` | `e2dfddce…88f6` |
| Hash `application_decisions` | * | `f81b6de4…a32f` | `f81b6de4…a32f` |
| Hash `meetings` | * | `e1c89e11…a2db` | `e1c89e11…a2db` |
| Hash `users` | * | `207e5f55…7dd6` | `207e5f55…7dd6` |

\* Kaedah hash larian 1 berbeza; tidak boleh dibandingkan terus. Kiraan, id maks dan cap masa menunjukkan tiada perubahan data PP4 sejak larian 1.

Nota: `users.last_login_at` maks = 16/11/2026 10:32. Ini log masuk pemilik sistem sebelum audit, bukan tindakan agent. Hash `users` sama sebelum dan selepas audit.

### 7.2 Pecahan

- **Status permohonan:** DRAFT 2, SUBMITTED 3, IN_JTICT 3. Tiada yang soft-deleted.
- **Keputusan:** PRE_JTICT / ACCEPTED 3, PRE_JTICT / AMENDMENT_REQUIRED 1. Tiada keputusan JTICT.
- **`jtict_decision` legacy:** PENDING 8 (tidak disentuh oleh PP4).
- **Peranan:** pengguna_biasa 10, pentadbir_urus_setia 1 (aktif), pengurusan 1, superadmin 1. Tiada pengguna berbilang peranan.

### 7.3 Pemeriksaan integriti (SELECT sahaja)

| # | Pemeriksaan | Keputusan |
|---|---|---|
| I1 | Keputusan tanpa permohonan | 0 |
| I2 | Keputusan pada permohonan soft-deleted | 0 |
| I3 | `meeting_id` tidak sah | 0 |
| I4 | `decided_by` tidak sah | 0 |
| I5 | `copied_from_application_id` tidak sah | 0 (semua NULL) |
| I6 | Status pasca-Pra JTICT tanpa rekod keputusan Pra JTICT | 0 |
| I7 | Keputusan Pra JTICT terkini vs status semasa | 3/3 sepadan (ACCEPTED ↔ IN_JTICT; permohonan 6, 7, 9) |
| I8 | `decision_date` melebihi tarikh rekod | 0 |
| I9 | `decision_date` lebih awal daripada PP2 Lengkap terkini sebelum keputusan | 0 |
| I10 | `from_status` keputusan Pra JTICT bukan COMPLETE | 0 |
| I11 | `meeting_id` keputusan ≠ `jtict_meeting_id` permohonan | 0 |
| I12 | Keputusan pada mesyuarat tidak aktif / lepas / dipadam | **1** – keputusan #1 (permohonan 6, Mesyuarat 1). Lihat PP42B-01. |
| I13 | UUID keputusan kosong atau berganda | 0 / 0 |
| I14 | Permohonan COMPLETE (menunggu atau semakan manual) | 0 pada masa ini |
| I15 | SUBMITTED tanpa Mesyuarat JTICT | 2 (permohonan 2 dan 3, data lama). Lihat PP42B-05. |
| I16 | Penghantaran semula selepas Pindaan Pra JTICT | 1 (semakan #10, permohonan 7) |

### 7.4 Penemuan untuk semakan pemilik (bukan kerosakan)

1. **Kitaran pindaan lengkap pada MySQL (permohonan 7):**
   - keputusan #3 AMENDMENT_REQUIRED (tarikh 04/11, direkod 05/11);
   - semakan #10 `PRE_JTICT_AMENDMENT → SUBMITTED` (05/11 15:55, pengguna 10);
   - semakan #11 PP2 Lengkap (16:29);
   - keputusan #4 ACCEPTED (05/11 16:30).

   ID permohonan kekal 7 dan sejarah terdahulu kekal. Ini **bukti pemerhatian** bahawa aliran berfungsi pada MySQL 8.0.46. Ia bukan pengesahan MySQL terkawal.
2. **Tarikh keputusan diundur:** keputusan #2 dan #3 bertarikh 04/11 tetapi direkod 05/11. Ini dibenarkan oleh peraturan (≤ hari ini, ≥ tarikh PP2 Lengkap).
3. **Permohonan 6 diterima untuk mesyuarat yang telah berlalu:** keputusan #1 ACCEPTED (04/11/2026) dengan Mesyuarat 1 (`is_active=0`, tarikh 23/01/2026). Lihat PP42B-01.
4. **Mesyuarat JTICT Bil. 5 (Mesyuarat 2) bertarikh 23/11/2026**, iaitu 7 hari dari tarikh audit. Permohonan 7 dan 9 (Diterima) menunggu mesyuarat ini. Permohonan 8 (SUBMITTED, Mesyuarat 2) masih menunggu semakan PP2. Tempoh penghantaran mesyuarat ditutup 06/11.
5. **Data Mesyuarat 1:** `submission_opens_at` = 05/10/2026 tetapi `submission_closes_at` = 09/01/2026 (tarikh buka selepas tarikh tutup). Ini di luar skop PP4 (modul Mesyuarat). Lihat PP42B-11.

---

## 8. Keputusan ujian automatik SQLite

### 8.1 Fail ujian berkaitan (larian 16/11/2026)

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
| **Jumlah** | **Lulus** | **112/112** | 0 | 0 | 0 | **1,036** |

Keputusan sama persis dengan larian 1.

### 8.2 Suite penuh

| Larian | Ujian | Lulus | Gagal | Ralat | Risky | Assertions |
|---|---|---|---|---|---|---|
| 1 | 504 | 504 | 0 | 0 | 1 | 3,873 |
| 2 | 504 | 504 | 0 | 0 | 1 | 3,873 |

Ujian *risky*: `UserRoleManagementTest::test_urus_setia_cannot_open_superadmin_self_or_pending_users` (0 assertion). Ujian ini dalam modul Pengurusan Pengguna, di luar skop PP4 (PP42B-08).

### 8.3 Ujian muat naik dokumen (kegagalan rawak PP4.2A)

| Fail | Larian ulangan | Keputusan |
|---|---|---|
| ApplicationDocumentTest | 10 | 10/10 lulus (12/12 ujian, 57 assertions setiap larian) |
| ApplicationFormDesignTest | 10 | 10/10 lulus (5/5 ujian, 37 assertions setiap larian) |

- Kegagalan PP4.2A **tidak dapat dihasilkan semula**. Jumlah bukti setakat ini: 40 larian berasingan dan 4 suite penuh (larian 1 + larian 2), semuanya lulus.
- **Kebocoran fail sementara disahkan secara kuantitatif:** `%TEMP%\doc*.tmp` bertambah daripada 42 kepada 72 selepas 10 larian `ApplicationDocumentTest`, iaitu **3 fail setiap larian**. `ApplicationFormDesignTest` tidak menambah fail. Punca: pembantu ujian `docx()` tidak memadam fail `tempnam()`.
- Status: **Lulus dengan Catatan** (PP42B-03).

### 8.4 Liputan ujian automatik berbanding senario UAT

| Senario | Ujian automatik (SQLite) |
|---|---|
| D1 Senarai & Semakan Manual | `PreJtictDecisionTest::test_list_shows_only_applications_awaiting_pre_jtict_decision`, `PreJtictDisplayTest` |
| D2 Diterima | `test_accepted_decision_moves_application_to_in_jtict_with_optional_remarks`, ujian tarikh |
| D3 Pindaan | `test_amendment_decision_requires_remarks`, `test_full_amendment_cycle_through_ui_keeps_identity_and_history`, ujian tanda penghantaran semula |
| D4 Ditolak | `test_rejected_decision_closes_application_and_blocks_amendment` |
| D5 Sejarah | Ujian paparan butiran (nama perekod disembunyikan daripada pemohon; tiada edit/padam) |
| D6 Akses | `test_only_active_urus_setia_can_record_pre_jtict_decision`. Gabungan Urus Setia + pengurusan tidak diuji secara khusus. |
| D7 Tanpa mesyuarat | Ujian polisi dan bahagian Perlu Semakan Manual |

Liputan automatik **tidak** menggantikan UAT.

### 8.5 Semakan statik kawalan backend

Keputusan Pra JTICT dikawal pada tiga lapisan:
1. Blade: `@can('decidePreJtict', $application)` (`view-application.blade.php:48`).
2. Livewire: `$this->authorize('decidePreJtict', …)` dalam `openPreJtictModal()` dan `submitPreJtictDecision()` (`ViewApplication.php:206`, `:237`).
3. Servis: dalam `DB::transaction`, rekod dikunci (`lockForUpdate`), pengguna dimuat semula, dan polisi, status serta peralihan disemak semula (`ApplicationDecisionService.php:76-87`).

Polisi `canRecordDecision()` mensyaratkan `canUseSystem()`, peranan `pentadbir_urus_setia`, dan **bukan** `superadmin` / `pengurusan`.

---

## 9. Keputusan pengesahan MySQL

| Perkara | Status |
|---|---|
| C1 – Pangkalan data ujian MySQL berasingan | **Tiada.** `SHOW DATABASES` (16/11/2026): `information_schema`, `performance_schema`, `sppictv2_db` sahaja. |
| C2 – 10 perkara pengesahan | **Belum Diuji** |

Fasa C dihentikan mengikut C1. Tiada pangkalan data dicipta. Tiada konfigurasi diubah.

**Keperluan (perlu kelulusan pemilik sistem):**
1. Cipta pangkalan data berasingan, contohnya `sppictv2_test`.
2. Sediakan konfigurasi ujian berasingan tanpa mengubah `.env`, contohnya `phpunit.mysql.xml` dengan `DB_CONNECTION=mysql` dan `DB_DATABASE=sppictv2_test`. Pastikan tiada laluan jatuh kembali kepada `sppictv2_db`.
3. Ujian menggunakan `RefreshDatabase`, jadi semua data dalam DB ujian akan dipadam. DB itu mesti khusus untuk ujian.
4. Jalankan 8 fail ujian berkaitan. Fokus kepada:
   - subquery `last_resubmitted_from` dan `withExists`;
   - `ofMany` (`latestPreJtictDecision`, `latestCompleteReview`);
   - `lockForUpdate` dan transaksi dalam `ApplicationDecisionService`;
   - jenis `date` bagi `decision_date` dan zon masa (`SYSTEM`).

---

## 10. Pelan UAT dan keputusan

**Prasyarat umum:**
- Persekitaran UAT berasingan (DB ujian) atau rekod ujian yang diluluskan oleh pemilik sistem. **Jangan** gunakan rekod sebenar dalam `sppictv2_db`.
- Akaun ujian. DB pembangunan hanya ada 1 Urus Setia aktif, tiada Urus Setia tidak aktif, dan tiada pengguna berbilang peranan, jadi akaun tambahan diperlukan untuk D6.
- Mesyuarat JTICT aktif yang akan datang.

**Bukti yang perlu disimpan (semua senario):**
- tangkapan skrin dengan tarikh dan masa;
- no. rujukan permohonan dan UUID dalam URL;
- ID rekod `application_decisions` / `application_reviews` daripada DB UAT.

**Status semua senario: Belum Diuji.** Tiada UAT dilaksanakan oleh pengguna dalam PP4.2B.

### D1 – Senarai menunggu Keputusan Pra JTICT (peranan: `pentadbir_urus_setia`)

| ID | Objektif | Prasyarat | Langkah | Hasil dijangka | Bukti | Status |
|---|---|---|---|---|---|---|
| UAT-D1-01 | Permohonan layak dipaparkan | Permohonan COMPLETE melalui PP2, ada Mesyuarat JTICT | Buka menu **Pra JTICT** | Dipaparkan dengan label "Lengkap – Menunggu Keputusan Pra JTICT", mesyuarat dan tarikh Lengkap | Skrin senarai | Belum Diuji |
| UAT-D1-02 | Tanpa mesyuarat tiada dalam senarai biasa | COMPLETE tanpa mesyuarat | Buka **Pra JTICT** | Tiada dalam jadual utama; ada di **Perlu Semakan Manual** dengan label "Lengkap" | Skrin kedua-dua bahagian | Belum Diuji |
| UAT-D1-03 | Keputusan muktamad tidak menunggu | Permohonan sudah Diterima / Ditolak | Buka **Pra JTICT** | Tidak dipaparkan sebagai menunggu | Skrin senarai | Belum Diuji |
| UAT-D1-04 | Tidak layak → Semakan Manual | COMPLETE tanpa rekod PP2 Lengkap | Buka **Pra JTICT** | Di Perlu Semakan Manual sahaja; tiada butang keputusan | Skrin | Belum Diuji |
| UAT-D1-05 | Tanda penghantaran semula | Dihantar semula selepas (a) Tidak Lengkap PP2, (b) Pindaan Pra JTICT | Senarai Permohonan untuk Semakan, penapis Dihantar | (a) "Dihantar semula selepas pindaan" (biru); (b) "Dihantar semula selepas Pindaan Pra JTICT" (oren); hantaran pertama tiada tanda | Skrin senarai | Belum Diuji |

### D2 – Keputusan Diterima (peranan: `pentadbir_urus_setia`)

| ID | Objektif | Prasyarat | Langkah | Hasil dijangka | Bukti | Status |
|---|---|---|---|---|---|---|
| UAT-D2-01 | Rekod Diterima tanpa catatan | UAT-D1-01 | Semak & Putuskan → **Diterima** → tarikh hari ini → Sahkan | Status `IN_JTICT`; label "Diterima – Menunggu Mesyuarat JTICT"; rekod dalam sejarah | Skrin + ID keputusan | Belum Diuji |
| UAT-D2-02 | Tarikh wajib | Sama | Kosongkan tarikh → Sahkan | "Tarikh keputusan wajib diisi."; tiada rekod | Skrin ralat | Belum Diuji |
| UAT-D2-03 | Tarikh masa depan ditolak | Sama | Tarikh esok | "Tarikh keputusan tidak boleh melebihi tarikh semasa."; tiada rekod | Skrin ralat | Belum Diuji |
| UAT-D2-04 | Tarikh sebelum PP2 Lengkap ditolak | Sama | Tarikh sebelum tarikh Lengkap | Ralat menyebut tarikh Lengkap; tiada rekod | Skrin ralat | Belum Diuji |
| UAT-D2-05 | Diterima ≠ Lulus JTICT | UAT-D2-01 | Semak papan pemuka pemohon | Kiraan "Lulus" tidak bertambah; tiada keputusan JTICT | Skrin papan pemuka | Belum Diuji |

### D3 – Ditolak dengan Pindaan

| ID | Objektif | Peranan | Langkah | Hasil dijangka | Bukti | Status |
|---|---|---|---|---|---|---|
| UAT-D3-01 | Catatan wajib | Urus Setia | **Ditolak dengan Pindaan**, catatan kosong → Sahkan | Ralat catatan wajib; tiada rekod | Skrin ralat | Belum Diuji |
| UAT-D3-02 | Rekod pindaan | Urus Setia | Catatan (≥ 10 aksara) + tarikh → Sahkan | Status `PRE_JTICT_AMENDMENT`; label "Ditolak dengan Pindaan – Menunggu Tindakan Pemohon" | Skrin + ID keputusan | Belum Diuji |
| UAT-D3-03 | Pemohon melihat catatan | Pegawai Utama aktif | Buka permohonan dan papan pemuka | Notis oren dengan catatan; butang "Kemas Kini & Hantar Semula" | Skrin | Belum Diuji |
| UAT-D3-04 | Bukan pegawai aktif: papar sahaja | Pendaftar / pegawai asal | Buka permohonan | Catatan kelihatan; tiada butang kemas kini | Skrin | Belum Diuji |
| UAT-D3-05 | Hantar semula | Pegawai Utama aktif | Pinda → Hantar Semula | Status `SUBMITTED`; rekod "Dihantar Semula" dalam `application_reviews`; **no. rujukan dan UUID URL tidak berubah** | Skrin sebelum/selepas + ID semakan | Belum Diuji |
| UAT-D3-06 | Tanda & sejarah kekal | Urus Setia | Senarai semakan + halaman butiran | Tanda oren; keputusan Pindaan terdahulu kekal | Skrin | Belum Diuji |
| UAT-D3-07 | Kitaran semula | Urus Setia | PP2 Lengkap → muncul semula dalam Pra JTICT → Diterima | Dua rekod keputusan, terkini di atas | Skrin sejarah | Belum Diuji |

### D4 – Ditolak

| ID | Objektif | Peranan | Langkah | Hasil dijangka | Bukti | Status |
|---|---|---|---|---|---|---|
| UAT-D4-01 | Rekod Ditolak tanpa catatan | Urus Setia | **Ditolak** + tarikh → Sahkan | Status `CLOSED`; label "Selesai – Ditolak (Pra JTICT)" | Skrin + ID keputusan | Belum Diuji |
| UAT-D4-02 | Tiada aliran pindaan | Pegawai Utama | Buka permohonan; cuba URL edit | Tiada butang kemas kini; URL edit → 403 | Skrin 403 | Belum Diuji |
| UAT-D4-03 | Sejarah boleh dilihat | Pemohon & Urus Setia | Buka butiran | Sejarah dipaparkan mengikut kebenaran | Skrin | Belum Diuji |

### D5 – Sejarah keputusan

| ID | Objektif | Peranan | Langkah | Hasil dijangka | Bukti | Status |
|---|---|---|---|---|---|---|
| UAT-D5-01 | Turutan & butiran | Urus Setia | Buka permohonan dengan ≥ 2 keputusan | Terkini di atas (tag "Terkini"); tarikh keputusan, tarikh direkod dan catatan (atau "Tiada ulasan.") betul | Skrin | Belum Diuji |
| UAT-D5-02 | Nama perakam | Urus Setia vs Pemohon | Bandingkan paparan | Urus Setia melihat nama; pemohon melihat "Urus Setia" sahaja | Dua skrin | Belum Diuji |
| UAT-D5-03 | Tiada edit/padam | Semua | Semak UI | Tiada butang edit/padam pada rekod sejarah | Skrin | Belum Diuji |

### D6 – Kawalan akses

| ID | Peranan | Langkah | Hasil dijangka | Bukti | Status |
|---|---|---|---|---|---|
| UAT-D6-01 | `pentadbir_urus_setia` aktif | Buka Pra JTICT dan buat keputusan | Dibenarkan | Skrin | Belum Diuji |
| UAT-D6-02 | `superadmin` | Buka `/urus-setia/pra-jtict` dan butiran permohonan | 403 bagi senarai; tiada butang keputusan | Skrin 403 | Belum Diuji |
| UAT-D6-03 | `pengurusan` | Sama | 403; tiada butang keputusan | Skrin 403 | Belum Diuji |
| UAT-D6-04 | `pengguna_biasa` | Sama | 403; tiada butang keputusan | Skrin 403 | Belum Diuji |
| UAT-D6-05 | Urus Setia tidak aktif | Cuba log masuk / buka Pra JTICT | Akses ditolak mengikut polisi akaun tidak aktif | Skrin | Belum Diuji – akaun ujian tiada |
| UAT-D6-06 | Urus Setia + superadmin / + pengurusan | Buka Pra JTICT | Ditolak (polisi mengecualikan) | Skrin 403 | Belum Diuji – akaun ujian tiada |

### D7 – Permohonan tanpa mesyuarat

| ID | Objektif | Peranan | Langkah | Hasil dijangka | Bukti | Status |
|---|---|---|---|---|---|---|
| UAT-D7-01 | Tiada butang/modal | Urus Setia | Buka butiran COMPLETE tanpa mesyuarat | Kad keputusan Pra JTICT tidak dipaparkan | Skrin | Belum Diuji |
| UAT-D7-02 | Backend menolak | Penguji teknikal | Panggil terus tindakan Livewire `openPreJtictModal` / `submitPreJtictDecision` | 403; tiada rekod | Log rangkaian + DB | Belum Diuji |
| UAT-D7-03 | Perlu Semakan Manual | Urus Setia | Buka Pra JTICT | Dipaparkan di Perlu Semakan Manual | Skrin | Belum Diuji |
| UAT-D7-04 | Tiada proses automatik | Urus Setia | Semak status selepas beberapa hari | Status kekal COMPLETE | Skrin bertarikh | Belum Diuji |

Prosedur bagi permohonan tanpa mesyuarat **belum ditetapkan** dan memerlukan keputusan pemilik sistem (PP42B-05).

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
2. Log masuk ke aplikasi pembangunan menulis ke `sppictv2_db` (`sessions` kerana `SESSION_DRIVER=database`, `users.last_login_at`, dan rekod audit). Ini melanggar peraturan baca sahaja.

**Semakan statik (bukan pengganti semakan visual):**
- Jadual Pra JTICT dan senarai permohonan dibalut `overflow-x-auto`, jadi tatal mendatar berlaku dalam jadual sahaja.
- Lencana label panjang menggunakan `whitespace-normal`. Perlu disahkan secara visual.
- Tiga modal (`view-application.blade.php` baris 343, 405, 444) menggunakan `fixed inset-0 … flex items-center justify-center` **tanpa padding luar**, dengan kotak `w-full max-w-lg … max-h-[90vh] overflow-y-auto`. Pada skrin kecil, modal mungkin rapat ke tepi skrin (PP42B-07).

---

## 12. Senarai isu dan risiko

Tiada isu **Kritikal** atau **Tinggi**.

| ID | Kategori | Penerangan | Langkah hasilkan semula | Bukti | Fail / komponen | Kesan | Cadangan |
|---|---|---|---|---|---|---|---|
| PP42B-01 | Sederhana (keputusan pemilik; prasyarat PP4.3; **sensitif masa**) | Keputusan Pra JTICT tidak menyemak sama ada Mesyuarat JTICT masih aktif, akan datang atau tidak dipadam. Polisi hanya menyemak `jtict_meeting_id !== null`, dan `validatePreJtict()` menggunakan `Meeting::withTrashed()`, jadi mesyuarat yang telah soft-deleted juga diterima. `validateJtict()` (PP4.3) hanya mensyaratkan tarikh keputusan ≥ tarikh mesyuarat, jadi keputusan JTICT bagi permohonan 6 boleh bertarikh seawal 23/01/2026, sebelum keputusan Pra JTICT (04/11/2026). | Permohonan COMPLETE dengan mesyuarat tidak aktif, lepas atau dipadam → Diterima | I12: keputusan #1, permohonan 6, Mesyuarat 1 (`is_active=0`, 23/01/2026) | `ApplicationPolicy::decidePreJtict()`, `ApplicationDecisionService::validatePreJtict()` / `validateJtict()`, `Application::scopeAwaitingPreJtictDecision()` | Label "Menunggu Mesyuarat JTICT" mengelirukan; turutan tarikh keputusan tidak logik | Pemilik tetapkan peraturan **sebelum PP4.3 dan sebelum Mesyuarat Bil. 5 (23/11/2026)**: (a) sekat jika mesyuarat sudah berlalu, tidak aktif atau dipadam; (b) benarkan pemindahan ke mesyuarat baharu; atau (c) terima dan dokumentasikan. Tentukan tindakan bagi permohonan 6. |
| PP42B-02 | Sederhana | Tiada pengesahan MySQL terkawal; tiada DB ujian berasingan | — | `SHOW DATABASES` 16/11 | Konfigurasi ujian | Perbezaan SQLite/MySQL belum dibuktikan | Luluskan `sppictv2_test` dan konfigurasi ujian MySQL berasingan (Seksyen 9) |
| PP42B-03 | Sederhana | Kegagalan rawak ujian `.docx` (PP4.2A) tidak dapat dihasilkan semula (40 larian + 4 suite penuh). Pembantu ujian meninggalkan 3 fail `doc*.tmp` setiap larian. | 10 larian `ApplicationDocumentTest` | `%TEMP%\doc*.tmp` 42 → 72 | `app/Rules/DocumentSignature.php`, `tests/Feature/ApplicationDocumentTest.php::docx()` | Ujian mungkin tidak stabil; folder TEMP bertambah | Pantau. Padam fail `tempnam` dalam pembantu ujian; log sebab kegagalan dalam rule (perlu kelulusan) |
| PP42B-04 | Sederhana | UAT D6 tidak dapat dilaksanakan dengan akaun sedia ada | — | 1 Urus Setia aktif; tiada Urus Setia tidak aktif; tiada pengguna berbilang peranan | Data ujian | Kawalan akses peranan berganda dan akaun tidak aktif hanya diuji secara automatik | Sediakan akaun ujian dalam persekitaran UAT |
| PP42B-05 | Sederhana (keputusan pemilik) | Prosedur bagi permohonan tanpa mesyuarat / Perlu Semakan Manual belum ditetapkan | Tetapkan permohonan 2/3 Lengkap (dalam UAT) | I15: permohonan 2, 3 SUBMITTED tanpa mesyuarat | `scopeNeedsManualPreJtictReview`, `decidePreJtict` | Permohonan boleh tersangkut | Pemilik tetapkan prosedur rasmi |
| PP42B-06 | Rendah | Catatan pilihan (Diterima/Ditolak), jika diisi, perlu ≥ 10 aksara | Catatan "OK" bagi Diterima | `REMARKS_MIN = 10` | `ApplicationDecisionService` | UX mengelirukan | Pemilik putuskan |
| PP42B-07 | Rendah | Modal tanpa padding luar pada skrin kecil (belum disahkan visual) | Buka modal pada lebar ≤ 400px | Semakan statik | `view-application.blade.php` 343, 405, 444 | Kosmetik | Sahkan visual dahulu |
| PP42B-08 | Rendah | Ujian *risky* tanpa assertion | Suite penuh | JUnit `assertions="0"` | `UserRoleManagementTest::test_urus_setia_cannot_open_superadmin_self_or_pending_users` | Ujian tidak membuktikan apa-apa | Tambah assertion (modul Pengguna) |
| PP42B-09 | Rendah (dokumentasi) | `AGENTS.md` bercanggah dengan pelaksanaan (superadmin "Urus Penuh" Keputusan; Laravel 12; PDF sahaja) | — | `docs/AGENTS.md` dan `AGENTS.md` repo `sppict-v2` | Dokumentasi | Ejen AI mungkin mengikut panduan salah | Kemas kini mengikut keputusan PP4 dan Laravel 13 |
| PP42B-10 | Rendah (dokumentasi) | Peraturan "hanya Pegawai Utama/Kedua aktif boleh meminda selepas dihantar" (05/11/2026) belum didokumenkan secara rasmi | — | Laporan PP4.2A Seksyen 8 | Dokumentasi | Jejak dasar tidak lengkap | Dokumentasikan |
| **PP42B-11** (baharu) | Rendah (di luar skop PP4) | Mesyuarat 1: tarikh buka penghantaran (05/10/2026) selepas tarikh tutup (09/01/2026). Tiada validasi tarikh buka ≤ tarikh tutup ditemui dalam data. | Semak rekod mesyuarat 1 | SELECT `meetings` id=1 | Modul Mesyuarat (`MeetingForm`) | Tetingkap penghantaran tidak logik | Semak data dan validasi borang mesyuarat (fasa berasingan) |

---

## 13. Batasan pengesahan

1. Semua ujian automatik dijalankan pada SQLite in-memory. Tingkah laku MySQL (`lockForUpdate`, keserentakan, jenis `date`) tidak dibuktikan.
2. Bukti MySQL (kitaran permohonan 7) ialah pemerhatian penggunaan pemilik, bukan ujian terkawal.
3. Tiada UAT oleh pengguna dan tiada semakan visual.
4. Keserentakan dua Urus Setia hanya disimulasikan secara berurutan.
5. Audit dibuat ke atas working tree dengan 43 fail diubah dan 12 fail *untracked* yang belum di-commit. Keputusan boleh berbeza jika fail tersebut berubah.
6. Larian 2 tidak menemui perubahan kod atau data sejak larian 1. Ia mengesahkan semula, bukan meluaskan, liputan pengesahan.

---

## 14. Cadangan tindakan seterusnya

1. **Segera (sebelum 23/11/2026):** pemilik sistem putuskan PP42B-01, iaitu peraturan mesyuarat lepas, tidak aktif atau dipadam, dan tindakan bagi permohonan 6.
2. **Pemilik sistem:** putuskan PP42B-05 (prosedur Perlu Semakan Manual).
3. **Pemilik sistem:** luluskan DB ujian MySQL berasingan, kemudian jalankan Fasa C.
4. **Pemilik sistem:** laksanakan UAT D1–D7 dan semakan visual tiga saiz skrin dalam persekitaran UAT dengan akaun ujian (PP42B-04). Rekod bukti dalam jadual Seksyen 10.
5. Commit kerja PP4.2/PP4.2A yang telah disemak (atas keputusan pemilik) supaya garis dasar PP4.3 jelas. Kerja ini sudah 11 hari belum di-commit.
6. Kemudian (perlu kelulusan): PP42B-03, -06, -07, -08, -09, -10 dan -11. Pemilik juga boleh membersihkan fail `doc*.tmp` dalam `%TEMP%`.

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

**Keputusan: SEDIA BERSYARAT** (tidak berubah daripada larian 1).

- PP4.3 boleh **dimulakan dari segi pembangunan**. Struktur PP4.1B dan aliran Pra JTICT stabil, dan tiada isu Kritikal atau Tinggi.
- **Syarat sebelum PP4.3 dimulakan:** pemilik sistem memutuskan PP42B-01, kerana peraturan mesyuarat JTICT menentukan logik `validateJtict()`. Keputusan ini perlu dibuat sebelum Mesyuarat JTICT Bil. 5 (23/11/2026), yang akan menjadi mesyuarat pertama untuk merekod keputusan JTICT bagi permohonan 7 dan 9.
- **Syarat sebelum PP4.2 dianggap disahkan sepenuhnya:**
  1. Fasa C (MySQL) dilaksanakan.
  2. UAT D1–D7 dilaksanakan dengan bukti.
  3. Semakan visual direkodkan.
