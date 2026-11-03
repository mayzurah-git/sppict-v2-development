# Laporan Pelaksanaan Fasa PP1 — Status Proses Permohonan

**Sistem:** SPPICT v2.1
**Tarikh:** 28 Oktober 2026
**Branch:** `feature/ui-design` (tiada operasi Git dilakukan)
**Rujukan:** `docs/Laporan Audit Fasa PP0 - Modul Permohonan Projek.md`

Petunjuk status fungsi dalam laporan ini:

- **[DILAKSANAKAN]** — kod telah diubah dan diuji.
- **[REKA BENTUK]** — disediakan pada peringkat reka bentuk (cth. peta peralihan, nilai status) tetapi tiada fungsi pengguna lagi.
- **[BELUM DILAKSANAKAN]** — tiada dalam kod; dirancang untuk fasa kemudian.

---

## 1. Ringkasan Dapatan Audit Kod Sebelum Perubahan

Dapatan PP0 telah disahkan semula terhadap kod semasa. Semuanya masih tepat.

| Dapatan PP0 | Pengesahan PP1 |
|---|---|
| `applications.status` ialah ENUM: `DRAFT`, `SUBMITTED`, `UNDER_REVIEW`, `NEEDS_AMENDMENT`, `APPROVED_URUS_SETIA`, `REJECTED_URUS_SETIA` | Disahkan (`SHOW COLUMNS`) |
| Kod hanya menulis `DRAFT` dan `SUBMITTED` | Disahkan (`ApplicationSubmissionService` baris 72 & 105) |
| Paparan menggunakan `IN_REVIEW`, `APPROVED`, `REJECTED` yang tiada dalam ENUM | Disahkan (`application-list.blade.php`, `dashboard/urus-setia.blade.php`) |
| Senarai status ditulis berulang dalam beberapa fail | Disahkan (model, service, Livewire, 2 Blade) |
| Keputusan JTICT dalam lajur berasingan `jtict_decision` / `jtict_remarks`; tiada lajur Pra JTICT | Disahkan |
| `review_remarks` wujud tetapi tidak ditulis atau dipaparkan | Disahkan |
| SQLite (ujian) tidak menguatkuasakan ENUM | Disahkan — `UrusSetiaDashboardTest` mencipta status `APPROVED` dan lulus walaupun MySQL akan menolaknya |

**Data DB pembangunan (baca sahaja sebelum perubahan):** 5 permohonan — 3 `SUBMITTED`, 2 `DRAFT`. Tiada nilai lama (`UNDER_REVIEW`, `APPROVED_URUS_SETIA`, `REJECTED_URUS_SETIA`), tiada nilai tidak standard. `jtict_decision = PENDING` pada semua rekod. `review_remarks` dan `jtict_remarks` kosong.

**Mekanisme sejarah sedia ada:** `owen-it/laravel-auditing` pada model `Application` (threshold 0 — tiada pemangkasan; merekod `old_values`/`new_values`, `user_id`, masa). Ia menangkap setiap perubahan `status` dan `review_remarks` apabila dijalankan melalui web.

**Percanggahan PP0 dengan kod:** Tiada ditemui.

---

## 2. Ringkasan Perubahan yang Dilaksanakan

1. **[DILAKSANAKAN]** PHP Enum `App\Enums\ApplicationStatus` sebagai satu sumber rujukan status proses, dengan label Bahasa Melayu, maksud, status boleh sunting dan peta peralihan.
2. **[DILAKSANAKAN]** Model, service dan senarai Livewire menggunakan enum dan tidak lagi menggunakan rentetan status yang bertaburan.
3. **[DILAKSANAKAN]** Pengawal peralihan pada penghantaran: hanya Draf / Tidak Lengkap boleh menjadi Dihantar (lapisan tambahan selepas `ApplicationPolicy::update`).
4. **[DILAKSANAKAN]** Komponen Blade `x-application-status-badge` digunakan oleh senarai, butiran permohonan dan papan pemuka.
5. **[DILAKSANAKAN]** Penapis status menggunakan nilai dalaman enum. Nilai tidak sah diabaikan.
6. **[DILAKSANAKAN]** Kad papan pemuka Urus Setia memaparkan status proses sahaja. Kad "Lulus" / "Ditolak" dibuang kerana ia keputusan mesyuarat.
7. **[DILAKSANAKAN]** Migration tambahan: `applications.status` ENUM → VARCHAR(30) + indeks.
8. **[DILAKSANAKAN]** 10 ujian baharu; 1 ujian sedia ada dikemas kini (lihat Seksyen 8).
9. Aset hadapan dibina semula (`npm run build`) supaya kelas warna baharu tersedia. `public/build` diabaikan oleh Git.

---

## 3. Status Proses Sebelum dan Selepas

### Sebelum

| Nilai | Sumber | Ditulis oleh kod? | Label paparan |
|---|---|---|---|
| `DRAFT` | ENUM DB | Ya | Draf |
| `SUBMITTED` | ENUM DB | Ya | Dihantar |
| `UNDER_REVIEW` | ENUM DB | Tidak | — |
| `NEEDS_AMENDMENT` | ENUM DB | Tidak (hanya dibaca) | Perlu Pembetulan |
| `APPROVED_URUS_SETIA` | ENUM DB | Tidak | — |
| `REJECTED_URUS_SETIA` | ENUM DB | Tidak | — |
| `IN_REVIEW` | Blade sahaja | — | Dalam Semakan (tidak boleh wujud dalam DB) |
| `APPROVED` | Blade sahaja | — | Lulus (tidak boleh wujud dalam DB) |
| `REJECTED` | Blade sahaja | — | Ditolak (tidak boleh wujud dalam DB) |

### Selepas (`App\Enums\ApplicationStatus`)

| Kes | Nilai DB | Label | Maksud | Pihak bertindak | Boleh disunting pemohon |
|---|---|---|---|---|---|
| `Draft` | `DRAFT` | Draf | Sedang disediakan oleh pemohon | Pemohon | Ya |
| `Submitted` | `SUBMITTED` | Dihantar | Menunggu semakan Urus Setia (penghantaran pertama **dan** semula) | Urus Setia | Tidak |
| `NeedsAmendment` | `NEEDS_AMENDMENT` | Tidak Lengkap | Dikembalikan dengan ulasan; perlu pindaan dan hantar semula | Pemohon | Ya |
| `Complete` | `COMPLETE` | Lengkap | Semakan kelengkapan lulus; menunggu keputusan Pra JTICT | Urus Setia / Pra JTICT | Tidak |
| `InJtict` | `IN_JTICT` | Dalam Proses JTICT | Diterima Pra JTICT; menunggu keputusan Mesyuarat JTICT | Urus Setia / JTICT | Tidak |
| `Closed` | `CLOSED` | Selesai | Keputusan muktamad Pra JTICT atau JTICT telah direkodkan | — | Tidak |

**Keputusan reka bentuk status:**

- **Tiada status "Dalam Proses Pra JTICT".** Maksudnya sama dengan Lengkap (Lengkap = menunggu Pra JTICT) dan tiada tindakan pengguna yang memindahkan permohonan dari Lengkap ke "Dalam Proses Pra JTICT". Menambahnya akan mewujudkan status bertindih tanpa pencetus. **Perlu pengesahan** (Seksyen 12).
- **Tiada status "Dihantar Semula".** Penghantaran semula menggunakan `SUBMITTED`. Bilangan dan masa penghantaran direkodkan dalam log audit (diuji), bukan sebagai status berasingan.
- **`NEEDS_AMENDMENT` dikekalkan sebagai nilai DB** dan hanya labelnya ditukar kepada "Tidak Lengkap". Ini mengelakkan pemetaan data.
- **Nilai lama** (`UNDER_REVIEW`, `APPROVED_URUS_SETIA`, `REJECTED_URUS_SETIA`) tidak dijadikan kes enum. Jika wujud, ia dipaparkan dengan selamat sebagai "Tidak Diketahui (NILAI)", tidak boleh disunting, dan halaman tidak gagal (diuji).
- **`status` sengaja tidak di-cast kepada enum** dalam model. Cast Laravel akan melontar `ValueError` bagi nilai lama dan mengubah jenis `$application->status` yang digunakan oleh kod dan ujian sedia ada. Model menyediakan `statusEnum()` (null-safe) dan `statusLabel()`.

---

## 4. Pemisahan Status Proses daripada Keputusan Pra JTICT dan JTICT

| Konsep | Penyimpanan | Nilai | Status PP1 |
|---|---|---|---|
| Status proses | `applications.status` (VARCHAR) + `ApplicationStatus` | 6 nilai di atas | [DILAKSANAKAN] |
| Keputusan Pra JTICT | **Belum wujud** | Diterima / Ditolak (ulasan wajib bagi Ditolak) | [REKA BENTUK] — lajur/rekod perlu dicipta dalam fasa Pra JTICT |
| Keputusan JTICT | `applications.jtict_decision` (ENUM sedia ada) + `jtict_remarks` | Lulus = `APPROVED`, Tidak Lulus = `REJECTED`, Kaji Semula = `REVIEW`, `PENDING` = belum diputuskan; `APPROVED_CONDITIONAL` tidak diperlukan | [REKA BENTUK] — lajur sedia ada boleh digunakan semula |

**Jaminan yang dilaksanakan dan diuji:**

- Enum status proses **tidak mengandungi** sebarang nilai atau label keputusan (`APPROVED`, `REJECTED`, `REVIEW`, `ACCEPTED`, `PENDING`, "Lulus", "Kaji Semula", dll.) — `test_process_status_does_not_contain_meeting_decisions`.
- Penghantaran semula tidak mengubah `jtict_decision` — `test_resubmission_after_amendment_returns_to_submitted_and_keeps_remarks_and_decision`.
- Papan pemuka tidak lagi memaparkan "Lulus" sebagai status, dan "Lengkap" menggunakan warna cyan, bukan hijau "Lulus".

**Hubungan status proses dengan keputusan (reka bentuk untuk fasa kemudian):**

| Peristiwa | Keputusan direkod | Status proses |
|---|---|---|
| Pra JTICT Diterima | Keputusan Pra JTICT = Diterima | `COMPLETE` → `IN_JTICT` |
| Pra JTICT Ditolak (ulasan wajib) | Keputusan Pra JTICT = Ditolak | `COMPLETE` → `CLOSED` |
| JTICT Lulus / Tidak Lulus / Kaji Semula (ulasan wajib) | `jtict_decision` + `jtict_remarks` | `IN_JTICT` → `CLOSED` |

Status proses hanya menunjukkan kedudukan aliran kerja; keputusan dibaca daripada lajur keputusan. Status `CLOSED` tidak menimpa keputusan.

---

## 5. Fail Ditambah atau Diubah

| Fail | Jenis | Tujuan |
|---|---|---|
| `app/Enums/ApplicationStatus.php` | Baharu | Sumber rujukan status, label, maksud, `editableCases()`, `allowedTransitions()`, `canTransitionTo()`, `labelFor()` (selamat untuk nilai lama), `options()` |
| `app/Models/Application.php` | Ubah | `EDITABLE_STATUSES` diterbitkan daripada enum; `statusEnum()`, `statusLabel()`; `targetJtictMeeting()` guna enum |
| `app/Services/ApplicationSubmissionService.php` | Ubah | Status ditetapkan melalui enum; pengawal `canTransitionTo(Submitted)` sebelum hantar |
| `app/Livewire/ApplicationList.php` | Ubah | Senarai Urus Setia guna `ApplicationStatus::Submitted`; penapis guna `tryFrom()`; pilihan penapis daripada enum |
| `resources/views/components/application-status-badge.blade.php` | Baharu | Lencana/titik status. Warna Tailwind kekal dalam Blade kerana `tailwind.config.js` tidak mengimbas `app/Enums` |
| `resources/views/livewire/application-list.blade.php` | Ubah | Buang peta status tempatan; pilihan penapis daripada enum; lencana komponen; petunjuk "Perlu pindaan & hantar semula" bagi Tidak Lengkap (pemohon). **Perubahan susun atur Urus Setia oleh pembangun yang belum di-commit dikekalkan.** |
| `resources/views/dashboard/urus-setia.blade.php` | Ubah | Kad status daripada enum (kecuali Draf); buang kad Lulus/Ditolak |
| `resources/views/livewire/view-application.blade.php` | Ubah | Lencana status dalam pengepala butiran |
| `database/migrations/2026_10_28_000001_change_status_to_string_on_applications_table.php` | Baharu | Lihat Seksyen 6 |
| `tests/Feature/ApplicationStatusTest.php` | Baharu | 10 ujian PP1 |
| `tests/Feature/UrusSetiaDashboardTest.php` | Ubah | Lihat Seksyen 8.3 |

Fail lain tidak disentuh, termasuk `ApplicationPolicy`. Polisi menggunakan `isEditable()`, yang kini diterbitkan daripada enum tanpa perubahan tingkah laku.

---

## 6. Migration dan Justifikasi

**`2026_10_28_000001_change_status_to_string_on_applications_table`**

| Perkara | Butiran |
|---|---|
| SQL (MySQL) | `alter table applications modify status varchar(30) not null default 'DRAFT'` ; `alter table applications add index applications_status_index(status)` |
| Sebab | ENUM DB tidak mengandungi `COMPLETE`, `IN_JTICT`, `CLOSED`. Mengekalkan senarai kedua dalam ENUM akan menduplikasi sumber rujukan (bercanggah dengan objektif 3.3). Corak sama dengan `item_category` (`2026_10_07_000007`). Indeks kerana senarai dan papan pemuka menapis ikut status (isu R6 PP0) |
| Kesan data | Tiada. MySQL `MODIFY` ENUM → VARCHAR mengekalkan rentetan. **Tiada pemetaan pukal** |
| `down()` | Menolak untuk kembali ke ENUM jika ada nilai di luar senarai lama (elak kehilangan data); kemudian buang indeks dan pulihkan ENUM |
| Prosedur | 1) Sandaran jadual `applications` (5 baris + `SHOW CREATE TABLE`) ke `db-backup-sebelum-status-pp1-20261028.sql` (diabaikan Git oleh `*.sql`). 2) `migrate --pretend` disemak. 3) `migrate --force` dijalankan. 4) Semua baris `INSERT` sebelum vs selepas dibandingkan: **identik** |
| Selepas | `status` = `varchar(30)`; taburan kekal `DRAFT: 2`, `SUBMITTED: 3` |

Tiada `migrate:fresh`, `refresh`, rollback, `db:wipe`, `DROP TABLE` atau `TRUNCATE` dijalankan. Tiada seeder dijalankan.

---

## 7. Pemetaan Status Lama ke Status Baharu

| Nilai lama | Nilai baharu | Tindakan data |
|---|---|---|
| `DRAFT` | `DRAFT` (Draf) | Tiada (sama) |
| `SUBMITTED` | `SUBMITTED` (Dihantar) | Tiada (sama) |
| `NEEDS_AMENDMENT` | `NEEDS_AMENDMENT` (label baharu: Tidak Lengkap) | Tiada (sama) |
| `UNDER_REVIEW` | Tiada padanan | 0 rekod. Tidak dipetakan; dipaparkan "Tidak Diketahui" jika muncul |
| `APPROVED_URUS_SETIA` | Tiada padanan yakin (mungkin `COMPLETE`) | 0 rekod. **Tidak dipetakan** — maksud asal mencampurkan kelulusan dengan semakan |
| `REJECTED_URUS_SETIA` | Tiada padanan yakin | 0 rekod. Tidak dipetakan |
| `IN_REVIEW`, `APPROVED`, `REJECTED` (Blade sahaja) | Dibuang daripada paparan | Tidak pernah wujud dalam DB |

---

## 8. Status Ujian Sebelum dan Selepas

Ujian dijalankan dengan `php artisan test --compact` (SQLite `:memory:`; DB pembangunan tidak disentuh).

### 8.1 Ringkasan

| | Sebelum PP1 | Selepas PP1 |
|---|---|---|
| Jumlah | 385 | 395 |
| Lulus | 385 | **395** |
| Gagal | 0 | **0** |
| Risky | 2 | 2 (sama; tidak berkaitan PP1) |
| Assertion | 2672 | 2765 |

Kod keluar `1` daripada PHPUnit disebabkan ujian risky, bukan kegagalan.

### 8.2 Ujian baharu — `tests/Feature/ApplicationStatusTest.php` (semua lulus)

| Ujian | Mengesahkan |
|---|---|
| `test_status_values_and_malay_labels` | Nilai dan label status (objektif 10.1, 10.2) |
| `test_process_status_does_not_contain_meeting_decisions` | Pemisahan daripada keputusan (10.9) |
| `test_allowed_transitions_follow_workflow` | Matriks penuh 6×6 peralihan; pemohon tidak boleh terus ke Lengkap (10.10) |
| `test_editable_statuses_come_from_enum` | `EDITABLE_STATUSES` / `isEditable()` selaras dengan enum (10.8) |
| `test_status_column_accepts_new_workflow_statuses` | Lajur menerima `COMPLETE`, `IN_JTICT`, `CLOSED` |
| `test_legacy_or_unknown_status_is_displayed_safely` | Rekod lama boleh dibaca; papan pemuka dan butiran tidak gagal (10.7) |
| `test_list_filter_uses_internal_values_and_ignores_invalid_values` | Penapis guna nilai dalaman; `APPROVED` diabaikan; petunjuk tindakan Tidak Lengkap (10.3) |
| `test_view_page_shows_process_status_label` | Label pada butiran (10.2) |
| `test_resubmission_after_amendment_returns_to_submitted_and_keeps_remarks_and_decision` | Tidak Lengkap → Dihantar (bukan Lengkap); `review_remarks` dikekalkan; `jtict_decision` tidak berubah; peralihan direkodkan dalam log audit dengan pengguna (10.5, 10.10) |
| `test_application_beyond_submission_cannot_be_submitted_again` | `SUBMITTED`/`COMPLETE`/`IN_JTICT`/`CLOSED` ditolak oleh service; status tidak berubah (10.8) |

Ujian sedia ada yang turut mengesahkan tiada regresi: `JtictSubmissionWindowTest` (syarat mesyuarat aktif, simpan draf tanpa mesyuarat, pindaan ke mesyuarat asal), `ApplicationDocumentTest` (draf, hantar, status boleh sunting), `ApplicationOfficerTest`, `PolicyDecisionsTest`, `SecurityHardeningTest` (akses peranan/agensi).

### 8.3 Ujian sedia ada yang dikemas kini

`tests/Feature/UrusSetiaDashboardTest::test_status_cards_count_applications_by_status_excluding_drafts`

- **Sebelum:** mencipta permohonan berstatus `APPROVED` dan menjangka kad "Lulus" = 1 dan kad "Perlu Pembetulan".
- **Sebab perubahan:** `APPROVED` bukan nilai status yang sah dalam DB MySQL (isu T1 PP0; ujian lulus hanya kerana SQLite tidak menguatkuasakan ENUM). "Lulus" ialah keputusan JTICT, bukan status proses — keperluan PP1 yang diluluskan.
- **Selepas:** guna `COMPLETE`; jangka "Dihantar" = 2, "Lengkap" = 1, "Tidak Lengkap" = 0, dan tiada kad "Lulus". Ujian ini masih menguji perkara yang sama (kiraan kad mengikut status, tidak termasuk draf).

### 8.4 Ujian yang belum berkaitan / belum dilaksanakan (di luar skop PP1)

Ditandakan **BELUM DILAKSANAKAN**, bukan lulus:

- Urus Setia menetapkan Tidak Lengkap dengan ulasan wajib.
- Pemohon melihat ulasan Urus Setia.
- Urus Setia menetapkan Lengkap.
- Notifikasi e-mel Tidak Lengkap / penghantaran semula.
- Keputusan Pra JTICT dan JTICT; peralihan ke `IN_JTICT` / `CLOSED`.
- Pilihan agensi Urus Setia, checkbox SST, CSV, salin Kaji Semula.

---

## 9. Pengesahan Data dan Fungsi Sedia Ada Dipelihara

- Semua 5 rekod permohonan identik sebelum dan selepas migration (perbandingan baris demi baris).
- Tiada rekod diubah oleh kod; tiada pemetaan pukal; `review_remarks`, `jtict_*` dan log audit tidak disentuh.
- Simpan Draf: tingkah laku tidak berubah (status `DRAFT` untuk rekod baharu; Tidak Lengkap kekal Tidak Lengkap apabila draf disimpan).
- Penghantaran dan syarat Mesyuarat JTICT aktif: tidak berubah; ujian sedia ada lulus.
- Kawalan akses: `ApplicationPolicy` tidak diubah; senarai Urus Setia masih `SUBMITTED` sahaja; penapis pemohon masih terhad kepada permohonan sendiri / dipertanggungjawabkan.
- Perubahan pembangun yang belum di-commit (UM12, UI log masuk, susun atur senarai Urus Setia, ujian berkaitan) dikekalkan. Satu-satunya fail ujian bertanda `M` yang saya ubah ialah `UrusSetiaDashboardTest.php`; hanya kaedah dalam 8.3 diubah dan ujian baharu pembangun dalam fail itu kekal.
- `.env` tidak diubah. Tiada dependency baharu. Tiada operasi Git.

---

## 10. Sokongan Reka Bentuk untuk Aliran Tidak Lengkap

| Keperluan | Sokongan semasa | Status |
|---|---|---|
| Urus Setia menetapkan Tidak Lengkap | Peralihan `SUBMITTED → NEEDS_AMENDMENT` ditakrif dalam enum | [REKA BENTUK] |
| Ulasan wajib | Lajur `review_remarks` wujud; tiada pengesahan atau borang | [BELUM DILAKSANAKAN] |
| Ulasan dikaitkan dengan permohonan | `review_remarks` pada rekod permohonan | [REKA BENTUK] |
| Ulasan terdahulu tidak dipadam secara senyap | Log audit `owen-it` merekod nilai lama/baharu `review_remarks` setiap kemas kini. Penghantaran semula tidak mengosongkan ulasan (diuji) | Separa — jejak teknikal wujud; paparan sejarah kepada pengguna belum ada |
| Pemohon melihat ulasan | Tiada paparan `review_remarks` | [BELUM DILAKSANAKAN] |
| Pemohon mengenal pasti tindakan | Label "Tidak Lengkap" + petunjuk "Perlu pindaan & hantar semula" dalam senarai pemohon | [DILAKSANAKAN] |
| Pemohon meminda | `NEEDS_AMENDMENT` boleh disunting (polisi `update` sedia ada); data dan dokumen tidak berubah dikekalkan | [DILAKSANAKAN — sedia ada] |
| Pemohon tidak boleh ubah ulasan / status | `review_remarks` dan `status` bukan sifat borang Livewire; status hanya ditetapkan oleh service | [DILAKSANAKAN — sedia ada] |
| Hantar semula → Dihantar, bukan Lengkap | `NEEDS_AMENDMENT → SUBMITTED` dikuatkuasakan; diuji | [DILAKSANAKAN] |
| Syarat penghantaran semasa hantar semula | Semua fasa disahkan semula; mesyuarat asal digunakan (tingkah laku sedia ada) | [DILAKSANAKAN — sedia ada] |
| Penghantaran semula direkod sebagai tindakan baharu | Log audit merekod `NEEDS_AMENDMENT → SUBMITTED`, pengguna dan masa (diuji) | [DILAKSANAKAN — guna mekanisme sedia ada] |
| Urus Setia menyemak semula | Permohonan kembali ke senarai Urus Setia (`SUBMITTED`); kitaran boleh berulang mengikut peta peralihan | [REKA BENTUK] — tindakan semakan belum wujud |

**Jurang sejarah semakan:** Log audit memenuhi keperluan minimum 5.5 (status sebelum/selepas, ulasan, masa, pengguna, penghantaran semula) **untuk tujuan audit**. Ia tidak sesuai dipaparkan terus kepada pemohon: format JSON teknikal, tidak dijana dalam konsol, dan mengandungi semua medan. Pilihan untuk fasa semakan (perlu keputusan, Seksyen 12):

- **A.** Paparkan sejarah ulasan dengan menapis rekod `audits` (`review_remarks` dalam `old_values`/`new_values`). Tiada jadual baharu, tetapi bergantung pada pakej audit.
- **B.** Jadual khusus `application_reviews` (peringkat, keputusan, ulasan, pengguna, masa). Jelas dan mudah dipaparkan, tetapi bertindih sebahagian dengan log audit.

Cadangan: **B**, kerana sejarah ulasan adalah data perniagaan yang dipaparkan kepada pemohon dan diperlukan juga untuk keputusan Pra JTICT / JTICT. Log audit kekal sebagai jejak teknikal.

---

## 11. Fungsi Aliran Tidak Lengkap yang Belum Dilaksanakan

| Fungsi | Fasa dicadangkan |
|---|---|
| Tindakan Urus Setia "Lengkap" / "Tidak Lengkap" (komponen, polisi `review`, peralihan melalui `canTransitionTo`) | PP2 |
| Borang ulasan wajib (pengesahan pelayan, min panjang) | PP2 |
| Sejarah semakan (pilihan A/B) | PP2 |
| Paparan ulasan kepada pemohon (borang edit + butiran) | PP2 |
| E-mel Tidak Lengkap kepada pemohon | PP2 (atau fasa e-mel berasingan) |
| Senarai Urus Setia memaparkan permohonan Tidak Lengkap / Lengkap (kini `SUBMITTED` sahaja) | PP2 |
| Keputusan Pra JTICT + lajur keputusan | Fasa Pra JTICT |
| Keputusan JTICT + e-mel | Fasa JTICT |

---

## 12. Risiko, Isu Terbuka dan Keputusan Perlu Pengesahan

**Keputusan perlu pengesahan:**

1. Adakah status "Dalam Proses Pra JTICT" diperlukan, atau "Lengkap" sudah memadai (reka bentuk semasa)?
2. Adakah Pra JTICT Ditolak dan semua keputusan JTICT (termasuk Kaji Semula) membawa kepada status `CLOSED` "Selesai"?
3. Sejarah semakan: pilihan A (log audit) atau B (jadual `application_reviews`)?
4. Selepas Tidak Lengkap, adakah penghantaran semula kekal ke mesyuarat asal walaupun mesyuarat telah berlalu (tingkah laku semasa)?
5. Penerima e-mel Tidak Lengkap: pemohon sahaja atau termasuk Pegawai Utama?

**Risiko / isu terbuka:**

- Ujian berjalan pada SQLite, yang tidak menguatkuasakan nilai status. Dengan VARCHAR, DB juga tidak lagi mengehadkan nilai. Integriti kini bergantung pada enum dan pengawal peralihan dalam kod. Semua penulisan status mesti melalui `ApplicationStatus` (dipatuhi dalam PP1).
- `ApplicationList` untuk Urus Setia hanya memaparkan `SUBMITTED`, jadi permohonan berstatus `COMPLETE`/`IN_JTICT` tidak akan kelihatan sehingga PP2 meluaskan senarai.
- Pengepala butiran memaparkan "Dihantar pada {created_at}" walaupun untuk draf (sedia ada; di luar skop).
- Isu PP0 yang belum diselesaikan kekal (K2, S1–S8, R1–R8 kecuali R6 yang telah diselesaikan melalui indeks status).
- 2 ujian risky sedia ada masih belum dikenal pasti.
- Sandaran `db-backup-sebelum-status-pp1-20261028.sql` berada dalam akar projek (diabaikan Git); padam atau pindah mengikut amalan pasukan.

---

## 13. Sengaja Tidak Dilaksanakan (Luar Skop PP1)

- Permohonan oleh Urus Setia bagi pihak agensi sasaran.
- Checkbox SST 8%.
- Templat dan import CSV.
- Fungsi semakan Lengkap / Tidak Lengkap oleh Urus Setia dan borang ulasan wajib.
- Paparan ulasan kepada pemohon.
- Notifikasi e-mel (ulasan, penghantaran semula, keputusan).
- Keputusan Pra JTICT (termasuk lajur) dan keputusan JTICT.
- Salin permohonan Kaji Semula.
- Enum keputusan (`PreJtictDecision`, `JtictDecision`) — tidak dicipta kerana tiada kod yang akan menggunakannya dalam PP1; akan dicipta bersama fungsi keputusan.
- Perubahan kepada `ApplicationPolicy`.

---

## 14. Cadangan Skop PP2

**PP2 — Semakan Urus Setia: Lengkap / Tidak Lengkap dan Penghantaran Semula**

**Prasyarat:** keputusan #3 (sejarah semakan) dan #5 (penerima e-mel) dalam Seksyen 12; kelulusan pengguna untuk memulakan PP2.

**Skop dicadangkan:**

1. Polisi `ApplicationPolicy::review()` — `pentadbir_urus_setia` sahaja, permohonan `SUBMITTED`.
2. Service `ApplicationReviewService` — peralihan melalui `ApplicationStatus::canTransitionTo()` dengan `lockForUpdate`; Tidak Lengkap memerlukan ulasan (pengesahan pelayan).
3. Sejarah semakan mengikut keputusan #3 (jika B: migration `application_reviews`).
4. Komponen/tindakan semakan pada `ViewApplication` untuk Urus Setia.
5. Paparan ulasan semasa dan sejarah kepada pemohon (butiran + borang edit ketika Tidak Lengkap).
6. Notifikasi e-mel Tidak Lengkap (queue, `afterCommit`).
7. Senarai Urus Setia diperluas dengan penapis status (Dihantar, Tidak Lengkap, Lengkap).
8. Ujian: kebenaran peranan, ulasan wajib, kitaran Tidak Lengkap → Dihantar → Tidak Lengkap → Lengkap, sejarah tidak hilang, e-mel (`Notification::fake()`), pemohon tidak boleh menetapkan Lengkap.

**Kebergantungan:** `ApplicationStatus` (PP1). Tidak bergantung pada SST, CSV atau pilihan agensi.

---

*PP2 tidak dimulakan. Menunggu semakan dan arahan pengguna.*
