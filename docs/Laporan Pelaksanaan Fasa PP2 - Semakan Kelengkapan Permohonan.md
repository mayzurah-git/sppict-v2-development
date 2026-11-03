# Laporan Pelaksanaan Fasa PP2 — Semakan Kelengkapan Permohonan

**Sistem:** SPPICT v2.1
**Tarikh:** 28 Oktober 2026
**Branch:** `feature/ui-design` (tiada operasi Git dilakukan)
**Rujukan:** `docs/Laporan Pelaksanaan Fasa PP1 - Status Proses Permohonan.md`

---

## 1. Ringkasan Pelaksanaan

PP2 telah dilaksanakan dan diuji. Fungsi berikut kini berfungsi:

| Fungsi | Status |
|---|---|
| Senarai Urus Setia memaparkan permohonan menunggu semakan (lalai Dihantar) dengan penapis status | Dilaksanakan |
| Tindakan **Tandakan Lengkap** / **Tandakan Tidak Lengkap** dengan modal pengesahan | Dilaksanakan |
| Ulasan wajib bagi Tidak Lengkap (disahkan di pelayan) | Dilaksanakan |
| Sejarah semakan dan penghantaran semula dalam jadual baharu `application_reviews` | Dilaksanakan |
| Pemohon melihat ulasan (butiran permohonan + borang pindaan) dan sejarah semakan | Dilaksanakan |
| Pindaan dan penghantaran semula → `SUBMITTED`, kembali kepada semakan Urus Setia | Dilaksanakan |
| Peraturan mesyuarat semasa penghantaran semula (keputusan 3.4) | Dilaksanakan |
| E-mel Tidak Lengkap kepada Pemohon dan Pegawai Utama | Dilaksanakan (diuji dengan `Notification::fake()`; **belum diuji dengan SMTP sebenar**) |

**Ujian:** 413 lulus, 0 gagal, 2 risky. Dua ujian risky ini sama seperti dalam baseline PP1 dan berada dalam modul lain.

**Migration:** satu migration tambahan (jadual baharu sahaja) telah dijalankan pada DB pembangunan. Data `applications` identik sebelum dan selepas migration.

Antara muka belum disemak secara manual dalam pelayar. Pengesahan dibuat melalui ujian Livewire/HTTP sahaja.

---

## 2. Keadaan Asal Berdasarkan PP1 (Langkah A)

Disahkan terhadap kod semasa sebelum perubahan:

- `App\Enums\ApplicationStatus` wujud dengan peta peralihan `SUBMITTED → NEEDS_AMENDMENT | COMPLETE` dan `NEEDS_AMENDMENT → SUBMITTED`. Tiada perubahan diperlukan pada enum.
- `applications.status` = `varchar(30)` (migration PP1, batch 21). Data: 2 `DRAFT`, 3 `SUBMITTED`.
- Tiada jadual `application_reviews` atau jadual lain dengan fungsi yang sama. `applications.review_remarks` wujud tetapi tidak digunakan.
- **Mesyuarat aktif:** `Meeting::scopeOpenForSubmission()` (`is_active = true` dan hari ini dalam `submission_opens_at`–`submission_closes_at`) bersama `scopeJtict()` (`meeting_names.accepts_applications = true`). Hubungan permohonan melalui `applications.jtict_meeting_id`. `MeetingForm` menghalang tempoh terbuka yang bertindih.
- **Pemohon:** `applications.applicant_id → users.email`.
- **Pegawai Utama:** `application_officers` (`ended_at IS NULL`, `role = PRIMARY`) → `user_id → users.email`, dengan snapshot `application_officers.email`. Relasi sedia ada: `Application::primaryOfficer()`.
- **Notifikasi:** corak `MeetingAnnouncement` (`ShouldQueue` + `afterCommit()`) dan `OfficerHandoverService::notify()` (try/catch + log). `QUEUE_CONNECTION=database`.

**Percanggahan dengan keputusan 3.4 (ditemui dan diperbetulkan):** `Application::targetJtictMeeting()` mengekalkan mesyuarat asal untuk `NEEDS_AMENDMENT` **walaupun mesyuarat itu telah ditutup**. Tingkah laku ini juga dikodkan dalam partial `jtict-status` dan dalam ujian `JtictSubmissionWindowTest::test_needs_amendment_resubmits_to_original_meeting_even_when_closed`. Ketiga-tiganya dikemas kini mengikut keputusan 3.4 (lihat Seksyen 8 dan 12.3).

---

## 3. Fail Ditambah atau Diubah

### Baharu

| Fail | Sebab |
|---|---|
| `database/migrations/2026_10_28_000002_create_application_reviews_table.php` | Jadual sejarah semakan (Seksyen 4) |
| `app/Models/ApplicationReview.php` | Model rekod sejarah; tidak boleh dikemas kini atau dipadam; label tindakan |
| `app/Services/ApplicationReviewService.php` | Logik semakan: pengesahan ulasan, transaksi + kunci, polisi, e-mel selepas commit, penentuan penerima |
| `app/Notifications/ApplicationMarkedIncomplete.php` | E-mel Tidak Lengkap (queue, `afterCommit`, `failed()` dilog) |
| `tests/Feature/ApplicationReviewTest.php` | 18 ujian PP2 |

### Diubah

| Fail | Perubahan |
|---|---|
| `app/Models/Application.php` | `reviews()`, `latestAmendmentReview()`; `targetJtictMeeting()` mengikut 3.4; `requiresMeetingSelection()` baharu |
| `app/Policies/ApplicationPolicy.php` | `review()` baharu |
| `app/Services/ApplicationSubmissionService.php` | `submit()` menerima `$selectedMeetingId`; `resolveSubmissionMeeting()` baharu; semak semula mesyuarat selepas dikunci; rekod sejarah penghantaran semula |
| `app/Services/SubmissionClosedException.php` | Mesej berbeza bagi Tidak Lengkap (kini tidak menyebut "disimpan sebagai draf") |
| `app/Livewire/ApplicationForm.php` | `resubmissionMeetingId`; computed `requiresMeetingSelection`, `eligibleMeetings`, `isAmendment`, `amendmentReview`, `amendmentRemarks`; `targetMeeting` mengikut pilihan |
| `app/Livewire/ViewApplication.php` | Tindakan semakan (`openReviewModal`, `submitReview`), sejarah semakan, ulasan semasa |
| `app/Livewire/ApplicationList.php` | Urus Setia: penapis status (lalai Dihantar, tanpa Draf), penanda "dihantar semula" |
| `resources/views/livewire/view-application.blade.php` | Panel semakan, ulasan untuk pemohon + pautan pindaan, sejarah semakan, modal pengesahan |
| `resources/views/livewire/application-form.blade.php` | Kotak ulasan Tidak Lengkap pada borang pindaan |
| `resources/views/livewire/application-form/phase-5-review-submit.blade.php` | Pilihan mesyuarat untuk penghantaran semula / mesej tiada mesyuarat |
| `resources/views/livewire/partials/jtict-status.blade.php` | Buang cabang "kekal pada mesyuarat asal walaupun ditutup" |
| `resources/views/livewire/application-list.blade.php` | Penapis status untuk Urus Setia; penanda "Dihantar semula selepas pindaan". Perubahan susun atur pembangun yang belum di-commit dikekalkan |
| `tests/Feature/JtictSubmissionWindowTest.php` | Satu ujian diganti (Seksyen 12.3) |

Tiada perubahan pada SST, CSV, keputusan Pra JTICT/JTICT, fungsi salin, Modul Pengguna, `.env` atau dependency. `public/build` dibina semula (`npm run build`; diabaikan Git) untuk kelas warna baharu.

---

## 4. Struktur `application_reviews`

| Lajur | Jenis (MySQL, disahkan) | Catatan |
|---|---|---|
| `id` | bigint unsigned PK | |
| `uuid` | char(36), unique | Konvensyen projek |
| `application_id` | bigint unsigned, FK → `applications` (NO ACTION) | |
| `from_status` | varchar(30) | Nilai `ApplicationStatus` sebelum tindakan |
| `to_status` | varchar(30) | `COMPLETE` / `NEEDS_AMENDMENT` (semakan) atau `SUBMITTED` (hantar semula) |
| `remarks` | text, null | Wajib bagi `NEEDS_AMENDMENT` |
| `user_id` | bigint unsigned, FK → `users` (NO ACTION) | Urus Setia yang menyemak, atau pemohon yang menghantar semula |
| `jtict_meeting_id` | bigint unsigned null, FK → `meetings` (NO ACTION) | Mesyuarat permohonan ketika tindakan; merekod pertukaran mesyuarat semasa hantar semula |
| `created_at` | timestamp, default CURRENT_TIMESTAMP | Masa tindakan |

Indeks: `(application_id, created_at)`, unique `uuid`.

**Keputusan reka bentuk:**

- **Satu jadual untuk semakan dan penghantaran semula.** Matlamat 6 meminta kedua-duanya direkodkan. Jenis tindakan diterbitkan daripada `from_status`/`to_status`, tanpa lajur pendua.
- **Tiada `updated_at`.** Rekod tidak boleh diubah: `ApplicationReview` melontar `LogicException` pada `updating` dan `deleting`.
- **FK tanpa cascade.** Sejarah menghalang pemadaman kekal permohonan atau pengguna. Kedua-dua model menggunakan soft delete, jadi operasi biasa tidak terjejas.
- **`review_remarks` dikekalkan** untuk keserasian. Medan ini menyimpan ulasan semakan **terkini** (null bagi Lengkap tanpa ulasan). Paparan sejarah menggunakan `application_reviews`; `review_remarks` hanya digunakan sebagai sandaran bagi rekod lama tanpa sejarah.
- **Log audit owen-it** kekal merekod perubahan pada `applications` sebagai jejak teknikal yang berasingan. `ApplicationReview` sendiri tidak di-audit kerana ia sudah merupakan rekod sejarah yang tidak boleh diubah.

---

## 5. Peraturan Peralihan Status yang Dilaksanakan

| Dari | Ke | Oleh | Dikuatkuasakan di |
|---|---|---|---|
| `DRAFT` | `SUBMITTED` | Pemohon | `ApplicationSubmissionService::submit()` (PP1), memerlukan mesyuarat aktif |
| `SUBMITTED` | `COMPLETE` | Urus Setia | `ApplicationReviewService::review()` + `ApplicationPolicy::review()` |
| `SUBMITTED` | `NEEDS_AMENDMENT` | Urus Setia (ulasan wajib) | Sama |
| `NEEDS_AMENDMENT` | `SUBMITTED` | Pemohon | `submit()` + peraturan mesyuarat 3.4 + rekod sejarah |

Setiap peralihan menyemak `ApplicationStatus::canTransitionTo()` di pelayan. `COMPLETE → IN_JTICT/CLOSED` tidak dilaksanakan. Tiada kod PP2 yang mengandaikan keputusan JTICT (termasuk Kaji Semula) akan menjadi `CLOSED` (keputusan 3.2).

---

## 6. Aliran Semakan Lengkap dan Tidak Lengkap

1. Urus Setia membuka permohonan `SUBMITTED` (pautan **Semak** dalam senarai). Panel **Semakan Kelengkapan** dipaparkan hanya jika `@can('review')`.
2. Klik **Tandakan Lengkap** atau **Tandakan Tidak Lengkap**. `openReviewModal()` menyemak `authorize('review')` dan hanya menerima `COMPLETE` / `NEEDS_AMENDMENT` (nilai lain → 422).
3. Modal pengesahan memaparkan ruangan ulasan (wajib bagi Tidak Lengkap, pilihan bagi Lengkap) dan penerangan kesan keputusan.
4. **Sahkan Keputusan** → `submitReview()`:
   - Jika status sudah berubah (klik berganda atau Urus Setia lain), paparan dikemas kini dengan mesej. Tiada rekod atau e-mel pendua.
   - `ApplicationReviewService::review()`:
     - Ulasan dinormalkan (`strip_tags` + `trim`) dan disahkan: wajib bagi Tidak Lengkap, 10–5000 aksara.
     - Dalam `DB::transaction`: `lockForUpdate` → semak semula `review` dan `canTransitionTo` → kemas kini `status` + `review_remarks` → cipta `ApplicationReview`.
     - Selepas commit, e-mel dihantar jika keputusan Tidak Lengkap.
5. Keputusan Lengkap tidak menghantar e-mel dan tidak menyentuh `jtict_decision`.

---

## 7. Aliran Pembetulan dan Penghantaran Semula

1. **Pemohon** melihat ulasan dalam:
   - Paparan butiran: kotak ulasan dan butang **Kemas Kini & Hantar Semula** (jika `@can('update')`).
   - Borang pindaan: kotak ulasan di atas borang.
2. **Sejarah Semakan** dipaparkan secara kronologi (terkini dahulu, ditanda "Terkini").
   - Pemohon dan pengguna agensi melihat peranan sahaja ("Urus Setia" / "Pemohon"). Nama individu hanya dipaparkan kepada Urus Setia.
3. Pindaan menggunakan borang sedia ada. Polisi `update` (pemohon sahaja, status Draf/Tidak Lengkap) tidak diubah. Ulasan dan status bukan medan borang, jadi pemohon tidak boleh mengubahnya.
4. **Hantar semula:**
   - Semua lima fasa disahkan semula dan peraturan mesyuarat (Seksyen 8) dikenakan.
   - Status menjadi `SUBMITTED`, dan rekod `NEEDS_AMENDMENT → SUBMITTED` dicipta.
   - Ulasan terdahulu kekal.
5. Permohonan kembali ke senarai Urus Setia dengan penanda **Dihantar semula selepas pindaan**. Kitaran boleh berulang, dan setiap semakan menghasilkan rekod baharu (diuji: 5 rekod untuk kitaran TL → hantar → TL → hantar → Lengkap).

---

## 8. Peraturan Pemilihan Mesyuarat (Keputusan 3.4)

Logik: `Application::requiresMeetingSelection()` dan `ApplicationSubmissionService::resolveSubmissionMeeting()`.

| Keadaan | Tingkah laku |
|---|---|
| Mesyuarat asal masih `openForSubmission` | Dikekalkan. Borang memaparkan "Penghantaran semula pindaan ke mesyuarat asal" |
| Mesyuarat asal tidak lagi dibuka, dan ada Mesyuarat JTICT dibuka | Dropdown **wajib** (mesyuarat JTICT yang dibuka sahaja). Butang Hantar dinyahaktifkan sehingga dipilih. Pelayan menolak ID di luar senarai yang dibuka (mesyuarat asal, tidak aktif, JPICT, ID rekaan) dengan ralat `resubmissionMeetingId` |
| Tiada mesyuarat yang layak | Penghantaran disekat. Pindaan disimpan dan status kekal `NEEDS_AMENDMENT`. Mesej: "Tiada Mesyuarat JTICT aktif yang layak untuk penghantaran semula…" |
| Mesyuarat ditutup ketika borang dibuka | Mesyuarat dikunci (`lockForUpdate`) dan `isOpenForSubmission()` disemak semula sebelum dihantar |

Tiada mesyuarat diaktifkan semula atau diubah secara automatik (diuji). Peraturan penghantaran Draf tidak berubah (`Meeting::currentJtict()`).

---

## 9. Penerima dan Mekanisme E-mel

- **Kelas:** `App\Notifications\ApplicationMarkedIncomplete` (`ShouldQueue`, `afterCommit()`, saluran `mail`), mengikut corak `MeetingAnnouncement`.
- **Kandungan:**
  - Nama sistem SPPICT, No. Rujukan, tajuk permohonan dan status **Tidak Lengkap**.
  - Ulasan Urus Setia.
  - Arahan untuk log masuk, menyemak ulasan, membuat pembetulan dan menghantar semula.
  - Pautan `route('application.view', uuid)`. Pautan ini dijana oleh sistem dan memerlukan log masuk serta polisi `view`.
- **Penerima** (`ApplicationReviewService::incompleteRecipients()`):
  1. Pemohon: `applicant->email`.
  2. Pegawai Utama aktif: `user->email`. Jika akaun tiada atau telah dipadam, snapshot `application_officers.email` digunakan melalui on-demand notification.
  - Alamat yang tiada atau tidak sah (`FILTER_VALIDATE_EMAIL`) dilangkau dan dilog sebagai `warning`, tanpa alamat dalam log.
  - Alamat yang sama (contohnya pemohon juga Pegawai Utama, iaitu lalai sistem) hanya menerima **satu** e-mel.
  - Urus Setia tidak menerima e-mel ini.
- **Kegagalan:**
  - Ralat semasa menghantar ke queue ditangkap per penerima dan dilog sebagai `error`.
  - Kegagalan dalam worker dilog melalui `failed()` dan direkodkan dalam `failed_jobs`.
  - Status dan sejarah tidak terjejas (diuji).
- **Penting:** `QUEUE_CONNECTION=database`. E-mel hanya dihantar apabila queue worker (`php artisan queue:work`) berjalan, sama seperti Makluman Mesyuarat.

---

## 10. Kawalan Akses dan Validasi

| Kawalan | Pelaksanaan |
|---|---|
| Siapa boleh menyemak | `ApplicationPolicy::review()`: `canUseSystem()`, peranan `pentadbir_urus_setia`, **bukan** `superadmin`/`pengurusan` (walaupun memegang dua peranan), status `SUBMITTED` |
| Skop Urus Setia | Semua agensi, selaras dengan polisi `view` sedia ada |
| Semakan pelayan | Polisi disemak di `openReviewModal`, `submitReview` dan semula dalam service dengan rekod dikunci |
| Manipulasi payload | Keputusan selain `COMPLETE`/`NEEDS_AMENDMENT` → 422. Pemohon memanggil tindakan semakan → 403. `resubmissionMeetingId` disahkan semula di pelayan |
| Pemohon | Tidak boleh menetapkan Lengkap (tiada laluan; polisi `review`). Pindaan hanya oleh pemilik (rakan seagensi, agensi lain dan Urus Setia mendapat 403 — diuji) |
| Sejarah | Tidak boleh diubah atau dipadam di peringkat model |
| Pendedahan | Ulasan hanya pada halaman yang dilindungi polisi `view` (pengguna agensi lain → 403). Nama penyemak tidak dipaparkan kepada pemohon |
| Ulasan | `strip_tags` + `trim`; 10–5000 aksara; dipaparkan ter-escape (`{{ }}`) |

---

## 11. Migration dan Bukti Pemeliharaan Data

**`2026_10_28_000002_create_application_reviews_table`**

- `up()`: hanya `CREATE TABLE` + FK + indeks pada jadual baharu (disemak melalui `migrate --pretend`). Tiada jadual sedia ada diubah.
- `down()`: **menolak** untuk membuang jadual jika ia mengandungi sejarah (diuji). Jika jadual kosong, ia dibuang.

**Prosedur dan bukti pada DB pembangunan MySQL:**

1. `migrate:status`: semua migration lain `Ran` (batch 21 terakhir). Hanya migration ini `Pending`. Tiada ketidakpadanan.
2. `migrate --pretend`: SQL disemak (6 kenyataan; semuanya pada `application_reviews`).
3. Petikan `applications` (SELECT sahaja) diambil sebelum migration, kemudian `migrate --force` (batch 22), kemudian petikan diambil sekali lagi. Perbandingan baris demi baris: **identik** (5 rekod; `DRAFT: 2`, `SUBMITTED: 3`).
4. `SHOW COLUMNS` + `information_schema.REFERENTIAL_CONSTRAINTS`: struktur seperti Seksyen 4; FK `NO ACTION`; 0 baris.

Tiada `migrate:fresh/refresh/rollback`, `db:wipe`, `DROP`, `TRUNCATE` atau seeder dijalankan. Tiada data permohonan, pengguna, mesyuarat atau audit diubah. Tiada pemetaan status pukal. Sandaran SQL tidak dipulihkan.

**Tidak disahkan:** tiada semakan manual dibuat ke atas aliran semakan sebenar pada DB pembangunan (tiada rekod semakan dicipta di sana).

---

## 12. Ujian

### 12.1 Keputusan

| | PP1 (baseline) | PP2 |
|---|---|---|
| Ujian | 395 | **413** |
| Lulus | 395 | **413** |
| Gagal | 0 | **0** |
| Risky | 2 | **2** (sama) |
| Assertion | 2765 | 2929 |

**Ujian risky** (sedia ada, modul Pengurusan Pengguna, di luar skop, tidak diubah). Kedua-duanya dilaporkan oleh PHPUnit sebagai *"This test did not perform any assertions"*:

- `SuperadminUserManagementTest::test_sensitive_fields_and_locked_properties_cannot_be_set`
- `UserRoleManagementTest::test_urus_setia_cannot_open_superadmin_self_or_pending_users`

### 12.2 Ujian baharu — `ApplicationReviewTest` (18, semua lulus)

| Keperluan | Ujian |
|---|---|
| 11.1 Lengkap | `test_urus_setia_can_mark_submitted_application_complete_without_email` |
| 11.2 Ulasan wajib | `test_incomplete_requires_meaningful_remarks` (kosong, ruang, HTML kosong, terlalu pendek, terlalu panjang) |
| 11.2 Tidak Lengkap + penerima | `test_urus_setia_marks_incomplete_and_applicant_and_primary_officer_are_notified`, `test_incomplete_email_content`, `test_applicant_who_is_also_primary_officer_receives_one_email`, `test_officer_without_account_uses_stored_email_and_invalid_email_is_skipped` |
| 11.2 Kegagalan e-mel | `test_email_failure_does_not_undo_review` |
| 10 Permintaan berganda | `test_repeated_review_request_does_not_create_duplicate_record_or_email` |
| 11.3 Kitaran pindaan | `test_full_amendment_cycle_keeps_history` |
| 11.3 Mesyuarat | `test_resubmission_is_blocked_without_open_meeting`, `test_tampered_meeting_selection_is_rejected` |
| 11.3 Pemilikan | `test_only_owner_can_amend_incomplete_application` |
| 11.4 Akses | `test_only_urus_setia_can_review_submitted_applications`, `test_review_is_only_available_for_submitted_status`, `test_review_history_cannot_be_modified_or_deleted`, `test_review_remarks_are_not_exposed_to_unauthorised_users` |
| 6.1 Senarai | `test_urus_setia_list_defaults_to_submitted_and_filters_by_status` |
| Migration | `test_migration_down_refuses_to_drop_existing_history` |

### 12.3 Ujian sedia ada yang diubah

`JtictSubmissionWindowTest::test_needs_amendment_resubmits_to_original_meeting_even_when_closed` diganti dengan `test_needs_amendment_with_closed_original_meeting_must_choose_open_meeting`.

- **Sebab:** ujian lama mengesahkan tingkah laku yang kini dilarang oleh keputusan 3.4 (hantar semula ke mesyuarat yang telah ditutup).
- **Ujian baharu:** apabila mesyuarat asal ditutup, pilihan mesyuarat dipaparkan, butang Hantar dinyahaktifkan, penghantaran tanpa pilihan ditolak, dan penghantaran dengan mesyuarat aktif berjaya ke mesyuarat baharu.

Tiada ujian lain diubah atau dipadam. Ujian PP1 (`ApplicationStatusTest`, termasuk hantar semula ke mesyuarat asal yang masih dibuka) kekal lulus tanpa perubahan.

---

## 13. Isu Belum Selesai, Risiko dan Batasan

1. **E-mel bergantung pada queue worker** (`QUEUE_CONNECTION=database`). Jika worker tidak berjalan, e-mel tertunggak dalam `jobs`. Belum diuji dengan SMTP sebenar.
2. **Ujian SMTP Modul Pengguna** (self-register, notifikasi Urus Setia) masih isu berasingan dan tidak disentuh.
3. **Urus Setia boleh menyemak permohonan yang dicipta sendiri.** Polisi tidak menghalang konflik kepentingan. Perlu keputusan dasar jika ia perlu disekat.
4. Mesej log kegagalan SMTP mengandungi `$e->getMessage()` (corak sama dengan `OfficerHandoverService`). Mesej pengecualian SMTP mungkin mengandungi alamat penerima.
5. Antara muka belum diuji secara manual dalam pelayar. Disyorkan ujian penerimaan pengguna (UAT) untuk modal semakan dan pilihan mesyuarat.
6. Rekod lama berstatus `NEEDS_AMENDMENT` tanpa sejarah (kini 0 dalam DB pembangunan) memaparkan `review_remarks` sebagai sandaran.
7. Senarai Urus Setia memaparkan satu status pada satu masa. Tiada pilihan "Semua status". Draf Urus Setia sendiri masih tidak tersenarai (isu S1 PP0).
8. Isu PP0 lain (K2 agensi sasaran Urus Setia, SST, CSV, S2–S8) kekal.

---

## 14. Cadangan Skop PP3 dan Perkara Menunggu Kelulusan

**Pilihan A — PP3: Keputusan Pra JTICT** (meneruskan aliran kerja)

- Simpan keputusan Diterima / Ditolak dan ulasan (wajib bagi Ditolak). Ini memerlukan lajur atau jadual baharu, contohnya meluaskan `application_reviews` dengan peringkat, atau jadual keputusan berasingan — **perlu keputusan**.
- Peralihan `COMPLETE → IN_JTICT` (Diterima) atau `COMPLETE → CLOSED` (Ditolak). Perlu pengesahan sama ada Ditolak Pra JTICT bermaksud `CLOSED`.
- Polisi dan ujian setara PP2. Senarai Urus Setia sudah menyokong penapis `COMPLETE`.

**Pilihan B — PP3: Urus Setia memohon bagi pihak agensi sasaran** (isu kritikal K2 dalam PP0), bebas daripada aliran keputusan.

**Menunggu kelulusan:**

1. Pilihan skop PP3 (A atau B).
2. Struktur penyimpanan keputusan Pra JTICT.
3. Sama ada Urus Setia perlu disekat daripada menyemak permohonan yang dicipta sendiri.
4. Pengesahan penghantaran e-mel sebenar (SMTP + queue worker) dalam persekitaran ujian.

---

*PP3 tidak dimulakan. Menunggu semakan dan arahan pengguna.*
