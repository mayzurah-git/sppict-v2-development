# Laporan Pelaksanaan PP4.1B: Struktur Asas Keputusan Pra JTICT dan JTICT

**Sistem:** SPPICT v2.1 (Laravel 13, Livewire 3, MySQL 8.0.46)
**Tarikh:** 3 November 2026
**Rujukan:**
- Laporan Audit PP4.0;
- Laporan Audit PP4.1A;
- Prompt PP4.1 dan PP4.1B;
- keputusan pemilik sistem K1 hingga K5.

**Status keseluruhan:** **Berjaya dengan catatan.** Kod, migration dan ujian telah siap. **Migration sebenar belum dijalankan dan memerlukan kelulusan pemilik sistem.**

---

## 1. Ringkasan Pelaksanaan

| Perkara | Status |
|---|---|
| 2 migration tambahan disediakan (`application_decisions`, `copied_from_application_id`) | Berjaya |
| Status `PRE_JTICT_AMENDMENT` ("Pindaan Pra JTICT") melalui enum PHP; tiada migration | Berjaya |
| Peraturan mesyuarat PP2 diluaskan kepada Pindaan Pra JTICT (K1(i)) | Berjaya |
| Rekod "Dihantar Semula" dalam `application_reviews` bagi Pindaan Pra JTICT (K1(ii)) | Berjaya |
| Enum `DecisionStage` dan `ApplicationDecision`; model `ApplicationDecision` yang tidak boleh diubah | Berjaya |
| `ApplicationDecisionService::decide()` lengkap (K2); **tidak dipanggil oleh mana-mana route atau UI** | Berjaya |
| Polisi `decidePreJtict()` dan `decideJtict()` | Berjaya |
| Ujian automatik (SQLite): 24 ujian baharu; suite penuh 464 lulus, 0 gagal | Berjaya |
| `php artisan migrate --pretend` | Berjaya (SQL dalam Seksyen 5) |
| Sandaran MySQL | **Belum dilaksanakan** (arahan disediakan dalam Seksyen 15) |
| `php artisan migrate` pada DB pembangunan | **Memerlukan kelulusan pemilik sistem** |
| Ujian MySQL | **Belum dilaksanakan** (selepas migration diluluskan) |

---

## 2. Senarai Fail Baharu

| Fail | Tujuan |
|---|---|
| `database/migrations/2026_11_03_000001_create_application_decisions_table.php` | Jadual sejarah keputusan |
| `database/migrations/2026_11_03_000002_add_copied_from_application_id_to_applications_table.php` | Lajur hubungan salinan |
| `app/Enums/DecisionStage.php` | Peringkat `PRE_JTICT` dan `JTICT` |
| `app/Enums/ApplicationDecision.php` | 6 kod keputusan |
| `app/Models/ApplicationDecision.php` | Model rekod keputusan (tidak boleh diubah) |
| `app/Services/ApplicationDecisionService.php` | Validasi dan `decide()` |
| `tests/Feature/ApplicationDecisionTest.php` | 24 ujian PP4.1B |
| `docs/Laporan Pelaksanaan PP4.1B - Struktur Asas Keputusan Pra JTICT dan JTICT.md` | Laporan ini |

## 3. Senarai Fail yang Diubah

Semua perubahan ialah **tambahan yang disasarkan**. Perubahan PP1–PP3 yang belum di-commit dalam fail yang sama dikekalkan.

| Fail | Perubahan |
|---|---|
| `app/Enums/ApplicationStatus.php` | Kes `PreJtictAmendment`, label, penerangan, peralihan `COMPLETE → PRE_JTICT_AMENDMENT` dan `PRE_JTICT_AMENDMENT → SUBMITTED`, `editableCases()`, kaedah baharu `isAmendment()`, kemas kini komen (rujukan `jtict_decision` digantikan dengan `application_decisions`) |
| `app/Models/Application.php` | `EDITABLE_STATUSES` (+1 status); `targetJtictMeeting()` dan `requiresMeetingSelection()` menggunakan `isAmendment()`; relationship `decisions()`, `latestPreJtictDecision()`, `latestJtictDecision()`, `copiedFromApplication()`, `copies()` |
| `app/Models/ApplicationReview.php` | `isResubmission()` mengenal pasti penghantaran semula daripada kedua-dua status pindaan; komen |
| `app/Services/ApplicationSubmissionService.php` | Satu syarat: rekod "Dihantar Semula" jika status sebelum ialah status pindaan (`isAmendment()`) |
| `app/Services/SubmissionClosedException.php` | Mesej penghantaran semula menggunakan label status. Mesej Tidak Lengkap **kekal sama perkataan demi perkataan**. |
| `app/Policies/ApplicationPolicy.php` | Kaedah baharu `decidePreJtict()`, `decideJtict()`, dan pembantu peribadi `canRecordDecision()`. **Kaedah sedia ada tidak diubah.** |
| `tests/Feature/ApplicationStatusTest.php` | 2 ujian dikemas kini (Seksyen 11.3) |

**Tidak disentuh:**
- `.env`, `config/*`, `routes/*`, komponen Livewire dan view;
- `ApplicationReviewService`, notifikasi dan Modul Mesyuarat;
- struktur `application_reviews` dan `meetings`.

Fail `config/auth.php`, `config/sppict.php`, `routes/auth.php` dan `ViewApplication.php` berstatus "diubah" dalam Git akibat fasa terdahulu. Fail-fail itu **tidak diubah** dalam PP4.1B.

---

## 4. Struktur Migration

### 4.1 `application_decisions`

| Medan | Jenis | Ketetapan |
|---|---|---|
| `id` | bigint unsigned AI | PK |
| `uuid` | char(36) | Unique; dijana secara automatik (`HasUuids`) |
| `application_id` | bigint unsigned | FK → `applications.id`, ON DELETE **NO ACTION** |
| `stage` | varchar(20) | `PRE_JTICT` / `JTICT` |
| `decision` | varchar(30) | 6 kod (Seksyen 6) |
| `from_status`, `to_status` | varchar(30) | Ditentukan oleh pelayan |
| `remarks` | text NULL | Ulasan |
| `decision_date` | date | Berasingan daripada `created_at` |
| `meeting_id` | bigint unsigned NULL | FK → `meetings.id`, NO ACTION |
| `decided_by` | bigint unsigned | FK → `users.id`, NO ACTION |
| `created_at` | timestamp, lalai CURRENT_TIMESTAMP | Tiada `updated_at` |

- Indeks: `(application_id, stage, created_at)`, `(stage, decision)` dan `uuid` unique. Indeks `meeting_id` dan `decided_by` dicipta secara automatik oleh MySQL untuk FK.
- Tiada ENUM MySQL dan tiada CHECK constraint.
- Enjin: lalai pelayan, iaitu **InnoDB** (`@@default_storage_engine` disahkan). Collation `utf8mb4_unicode_ci`, sama seperti jadual sedia ada.
- `down()` melontar `RuntimeException` jika jadual mengandungi rekod.

### 4.2 `applications.copied_from_application_id`

- `bigint unsigned NULL`, diletakkan selepas `jpict_remarks`.
- FK → `applications.id`, **NO ACTION** (bukan cascade, bukan set null).
- Tiada indeks unique dan tiada backfill; 8 rekod sedia ada kekal `NULL`.
- **Tidak** dimasukkan ke `$fillable`. Ini disahkan oleh ujian: mass assignment diabaikan.
- `down()` melontar `RuntimeException` jika ada nilai bukan `NULL`. Jika tiada, ia membuang FK dan lajur sahaja.

---

## 5. SQL daripada `php artisan migrate --pretend`

`migrate:status` menunjukkan dua migration baharu berstatus **Pending**, dan tiada migration lain yang tertunggak.

```sql
-- 2026_11_03_000001_create_application_decisions_table
create table `application_decisions` (`id` bigint unsigned not null auto_increment primary key, `uuid` char(36) not null, `application_id` bigint unsigned not null, `stage` varchar(20) not null, `decision` varchar(30) not null, `from_status` varchar(30) not null, `to_status` varchar(30) not null, `remarks` text null, `decision_date` date not null, `meeting_id` bigint unsigned null, `decided_by` bigint unsigned not null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `application_decisions` add constraint `application_decisions_application_id_foreign` foreign key (`application_id`) references `applications` (`id`);
alter table `application_decisions` add constraint `application_decisions_meeting_id_foreign` foreign key (`meeting_id`) references `meetings` (`id`);
alter table `application_decisions` add constraint `application_decisions_decided_by_foreign` foreign key (`decided_by`) references `users` (`id`);
alter table `application_decisions` add index `application_decisions_application_id_stage_created_at_index`(`application_id`, `stage`, `created_at`);
alter table `application_decisions` add index `application_decisions_stage_decision_index`(`stage`, `decision`);
alter table `application_decisions` add unique `application_decisions_uuid_unique`(`uuid`);

-- 2026_11_03_000002_add_copied_from_application_id_to_applications_table
alter table `applications` add `copied_from_application_id` bigint unsigned null after `jpict_remarks`;
alter table `applications` add constraint `applications_copied_from_application_id_foreign` foreign key (`copied_from_application_id`) references `applications` (`id`);
```

**Analisis kesan:**

| Pernyataan | Kesan kepada rekod sedia ada | Destructive? |
|---|---|---|
| `create table application_decisions` dan FK/indeksnya | Tiada; jadual baharu kosong | Tidak |
| `alter table applications add copied_from_application_id ... null` | 8 rekod mendapat nilai `NULL`; tiada nilai lain berubah. MySQL 8 InnoDB: jadual 8 rekod, masa dan kunci boleh diabaikan. | Tidak |
| `add constraint applications_copied_from_application_id_foreign` | Semua nilai `NULL`, jadi tiada pelanggaran FK | Tidak |

- Tiada `DROP`, `TRUNCATE`, `DELETE`, `UPDATE` atau `MODIFY` lajur sedia ada.
- **Batasan `--pretend`:** ia memaparkan SQL tetapi tidak mengesahkan bahawa pelayan akan menerimanya, contohnya kebenaran pengguna DB untuk `ALTER` atau `REFERENCES`. Ini hanya dapat disahkan apabila migration dijalankan.

---

## 6. Model, Enum dan Relationship

| Komponen | Ringkasan |
|---|---|
| `ApplicationStatus::PreJtictAmendment` | Nilai `PRE_JTICT_AMENDMENT`, label "Pindaan Pra JTICT", boleh disunting, peralihan → `SUBMITTED` sahaja |
| `ApplicationStatus::isAmendment()` | Benar bagi `NEEDS_AMENDMENT` dan `PRE_JTICT_AMENDMENT` |
| `DecisionStage` | `PreJtict` / `Jtict`; `label()`, `decisions()`, `requiredStatus()` (`COMPLETE` / `IN_JTICT`) |
| `ApplicationDecision` (enum) | `ACCEPTED` Diterima, `AMENDMENT_REQUIRED` Ditolak dengan Pindaan, `REJECTED` Ditolak, `APPROVED` Lulus, `NOT_APPROVED` Tidak Lulus, `REVIEW` Kaji Semula; `stage()`, `label()`, `targetStatus()`, `requiresRemarks()`, `forStage()` |
| `ApplicationDecision` (model) | `HasUuids`, `UPDATED_AT = null`, `$fillable` terhad (tiada `uuid` atau `created_at`), cast enum dan tarikh. Event `creating` menolak pasangan stage–decision yang tidak sah. `updating` dan `deleting` melontar `LogicException`. Relationship `application()`, `decidedBy()` dan `meeting()` (`withTrashed`). |
| `Application` | `decisions()`, `latestPreJtictDecision()`, `latestJtictDecision()`, `copiedFromApplication()` (`withTrashed`), `copies()` |

Enum `ApplicationDecision` dan model `ApplicationDecision` berkongsi nama, mengikut prompt. Ia berada dalam namespace berbeza (`App\Enums` dan `App\Models`), dan alias `Decision` digunakan dalam service dan ujian.

---

## 7. Pelaksanaan `ApplicationDecisionService`

```php
decide(Application $application, DecisionStage $stage, ApplicationDecision|string|null $decision,
       ?string $remarks, CarbonInterface|string|null $decisionDate, User $user, ?int $meetingId = null): ApplicationDecision
```

| Langkah | Pelaksanaan |
|---|---|
| 1 | Keputusan mesti wujud dan sepadan dengan `$stage`. Rentetan yang tidak sah ditolak (`decision`). |
| 2 | Ulasan: tag HTML dibuang. Wajib bagi `AMENDMENT_REQUIRED`, `APPROVED`, `NOT_APPROVED` dan `REVIEW`; 10–5000 aksara (`remarks`). |
| 3 | `decisionDate`: format `Y-m-d`, tidak melebihi hari ini (`decisionDate`) |
| 4 | `DB::transaction()` + `Application::lockForUpdate()` |
| 5 | Pengguna diambil semula daripada DB. Polisi `decidePreJtict` atau `decideJtict` disemak **di dalam transaksi**, bersama status semasa = `requiredStatus()` dan `canTransitionTo()` (`AuthorizationException`). |
| 6 | **Pra JTICT:** tarikh ≥ tarikh rekod PP2 **Lengkap terakhir**. Jika tiada rekod Lengkap, keputusan ditolak. `meeting_id` = `jtict_meeting_id` permohonan (tidak diubah); `$meetingId` yang berbeza ditolak. |
| 7 | **JTICT:** mesyuarat permohonan mesti wujud, tidak dipadam dan merupakan mesyuarat JTICT. `$meetingId` mesti sepadan. Tarikh ≥ `meetings.meeting_date`. |
| 8 | Rekod baharu dicipta: `stage`, `from_status`, `to_status` dan `decided_by` ditentukan oleh pelayan |
| 9 | Hanya `applications.status` dikemas kini. `jtict_meeting_id`, `review_remarks` dan `jtict_decision` tidak disentuh. |
| 10 | Rekod dikembalikan. Sebarang kegagalan membatalkan keseluruhan transaksi. |

Keputusan → status: Diterima → `IN_JTICT`; Ditolak dengan Pindaan → `PRE_JTICT_AMENDMENT`; Ditolak, Lulus, Tidak Lulus dan Kaji Semula → `CLOSED`.

**Tidak dilakukan:** tiada e-mel, tiada rekod `projects`, tiada salinan permohonan, dan tiada penulisan `jtict_decision`.

---

## 8. Pelaksanaan `ApplicationPolicy`

| Kaedah | Syarat |
|---|---|
| `decidePreJtict` | `canUseSystem()` (aktif, tiada kata laluan sementara), peranan `pentadbir_urus_setia`, **bukan** `superadmin` atau `pengurusan` (walaupun memegang peranan berganda), status `COMPLETE` |
| `decideJtict` | Syarat peranan yang sama, status `IN_JTICT`, **dan** keputusan Pra JTICT terkini = `ACCEPTED` (dibaca terus daripada DB) |

`view()`, `update()`, `review()` dan kaedah lain tidak diubah.

**K5 (paparan sejarah):** belum ada paparan. Ia akan mengikut `view()` sedia ada tanpa nama pegawai, seperti diputuskan, dalam fasa UI.

---

## 9. Sokongan Kitaran Pindaan Pra JTICT

```
COMPLETE ──decide(AMENDMENT_REQUIRED, ulasan wajib)──► PRE_JTICT_AMENDMENT  (mesyuarat tidak diubah)
   ▲                                                         │ pemohon / pegawai aktif meminda (polisi update sedia ada)
   │                                                         ▼
   └── PP2 Lengkap (ApplicationReviewService) ◄── SUBMITTED ◄── hantar semula (ID, UUID, no. rujukan sama)
```

**Peraturan mesyuarat (K1(i)):** `targetJtictMeeting()` dan `requiresMeetingSelection()` kini menggunakan `isAmendment()`. Ini definisi "mesyuarat dibuka" PP2 yang sama (`isOpenForSubmission()`); tiada definisi baharu.
- Jika mesyuarat asal masih dibuka, ia dikekalkan.
- Jika tidak, pemohon mesti memilih mesyuarat JTICT aktif. Medan pilihan dalam borang sedia ada muncul secara automatik kerana ia menggunakan kaedah yang sama.
- Jika tiada mesyuarat dibuka, pindaan disimpan dan status kekal "Pindaan Pra JTICT".

Tingkah laku `NEEDS_AMENDMENT` tidak berubah: semua ujian PP2 sedia ada lulus tanpa diubah.

---

## 10. Rekod "Dihantar Semula" dalam `application_reviews`

- `ApplicationSubmissionService::submit()` mencipta rekod `application_reviews` (`from_status = PRE_JTICT_AMENDMENT`, `to_status = SUBMITTED`, `user_id` = penghantar, `jtict_meeting_id` = mesyuarat penghantaran). Corak dan medannya sama seperti Tidak Lengkap.
- `ApplicationReview::isResubmission()` mengenal pasti rekod ini, jadi `actionLabel()` memaparkan "Dihantar Semula".
- `actorRoleLabel()` dan `isReviewDecision()` tidak berubah.
- **Struktur `application_reviews` tidak diubah.** Rekod lama tidak dikemas kini atau dipadam; rekod baharu hanya ditambah.
- Tiada keputusan Pra JTICT atau JTICT disimpan dalam `application_reviews`.

---

## 11. Keputusan Ujian

### 11.1 Ujian baharu: `tests/Feature/ApplicationDecisionTest.php` (SQLite): **24 lulus, 0 gagal, 263 assertion**

| # | Ujian | Liputan prompt (Bahagian I) | Keputusan |
|---|---|---|---|
| 1 | Decision table and copy column structure | I1.1, I1.2, I1.4, I1.5, I1.7, I1.8 | Lulus |
| 2 | Copy column is nullable and not mass assignable | I1.5, C2.7 | Lulus |
| 3 | Uuid is generated and relationships work | I1.3, F3 dan F4 | Lulus |
| 4 | Migrations refuse to drop existing history | C1.9, C2.9 | Lulus |
| 5 | Pre jtict amendment status is editable by applicant and officer | I2.12 | Lulus |
| 6 | Full pre jtict amendment cycle keeps identity and history | I2.13, I2.14, I2.16, I2.17, I3.27 | Lulus |
| 7 | Pre jtict amendment with closed meeting requires open meeting selection | I2.15 | Lulus |
| 8 | Pre jtict amendment without open meeting keeps status | I2.13 | Lulus |
| 9 | Decision must match stage | I3.18, I3.30 | Lulus |
| 10 | Model rejects mismatched stage and decision | I3.18 | Lulus |
| 11 | Pre jtict decision only for complete status | I3.19 | Lulus |
| 12 | Jtict decision requires in jtict and accepted pre jtict decision | I3.20, I3.21 | Lulus |
| 13 | Each decision sets expected status | I3.28, G3 dan G4 | Lulus |
| 14 | Remarks are required according to owner decision | I3.22 | Lulus |
| 15 | Decision date cannot be in the future | I3.23 | Lulus |
| 16 | Pre jtict date not before latest pp2 complete date | I3.24 | Lulus |
| 17 | Jtict date not before meeting date and meeting must match | I3.25, I3.26 | Lulus |
| 18 | Jtict decision refused when meeting is deleted | G4 | Lulus |
| 19 | Failed transaction leaves no partial decision | I3.29 | Lulus |
| 20 | Repeated decision does not create duplicate | G1 | Lulus |
| 21 | Only active urus setia may record decisions | I4.31 hingga I4.34 | Lulus |
| 22 | Decision records cannot be updated or deleted | F3.8, G5 | Lulus |
| 23 | Decisions do not touch legacy fields other records email or copies | I1.6, I5.35 hingga I5.39 | Lulus |
| 24 | No decision route or ui is exposed | I5.40 | Lulus |

I2.9 hingga I2.11 (status dan peralihan) diuji dalam `ApplicationStatusTest`.

### 11.2 Suite penuh (SQLite): **464 lulus, 0 gagal, 1 risky**

- Ujian risky itu sudah wujud sebelum PP4.1B dan tiada kaitan dengannya.
- Termasuk semua ujian PP1, PP2 (`ApplicationReviewTest`, `JtictSubmissionWindowTest`), PP3 (`OnBehalfApplicationTest`) dan modul lain.

### 11.3 Ujian sedia ada yang dipinda (dengan justifikasi)

| Ujian | Pindaan | Justifikasi |
|---|---|---|
| `ApplicationStatusTest::test_status_values_and_malay_labels` | Tambah `'PRE_JTICT_AMENDMENT' => 'Pindaan Pra JTICT'` | Keperluan baharu K1: status ditambah secara sah. Ujian menyemak senarai tepat. |
| `ApplicationStatusTest::test_allowed_transitions_follow_workflow` | Tambah `COMPLETE → PRE_JTICT_AMENDMENT` dan `PRE_JTICT_AMENDMENT → SUBMITTED` | Peralihan baharu yang diperlukan prompt (D1). Semua pasangan lain masih diuji sebagai **tidak** dibenarkan. |

`test_process_status_does_not_contain_meeting_decisions` **tidak** dipinda dan masih lulus. Label "Pindaan Pra JTICT" bukan label keputusan.

### 11.4 Semakan lain

| Semakan | Keputusan |
|---|---|
| `php -l` bagi 14 fail baharu atau diubah | Tiada ralat sintaks |
| Analisis statik | Tiada PHPStan/Larastan dalam projek |
| Laravel Pint `--test` (semak sahaja, tanpa mengubah fail) | Melaporkan isu gaya (jarak `.` dan `!`). **Kod sedia ada seperti `ApplicationReviewService` gagal atas sebab gaya yang sama**, jadi projek tidak diformat dengan Pint. Kod baharu mengikut gaya fail sedia ada. Pint tidak dijalankan untuk memformat semula. |

---

## 12. Ujian SQLite berbanding MySQL

| Jenis | Status |
|---|---|
| SQLite in-memory (`phpunit.xml`) | **Berjaya:** 464/464 lulus. FK SQLite didayakan dalam ujian, dan metadata FK (`NO ACTION`) disemak. |
| MySQL (`sppictv2_db`) | **Belum dilaksanakan.** Hanya `migrate --pretend` (SQL) dan semakan struktur baca sahaja. **Ujian SQLite tidak membuktikan** bahawa migration, FK dan kunci `lockForUpdate` berfungsi pada MySQL. |

---

## 13. Semakan Keselamatan dan Integriti Data

| Perkara | Pengesahan |
|---|---|
| Nilai `stage`, `from_status`, `to_status` dan `decided_by` tidak boleh dimanipulasi | Ditentukan oleh pelayan; tiada parameter untuknya |
| Pasangan stage–decision tidak sah | Ditolak di service dan model (`creating`) |
| Keputusan oleh peranan lain atau akaun tidak aktif | Ditolak oleh polisi di dalam transaksi |
| Keputusan berganda atau serentak | Kunci rekod + semakan status semula; ujian rekod lapuk (stale) lulus |
| Melangkau peringkat (JTICT tanpa Pra JTICT Diterima) | Ditolak |
| Rekod keputusan diubah atau dipadam melalui model | `LogicException` |
| **Batasan** | Operasi pukal query builder (`ApplicationDecision::query()->update()`, `DB::table()`) memintas event model. Ini sama seperti `application_reviews`, dan dilaporkan dalam PP4.1A R4. |
| Ulasan | Tag HTML dibuang; disimpan sebagai teks biasa |

**Pengesahan pangkalan data selepas kerja (skrip baca sahaja):**
- `application_decisions` **tidak wujud**, dan `copied_from_application_id` **tidak wujud**;
- 43 migration, batch maksimum 23;
- `applications` 8 (COMPLETE 2, DRAFT 2, SUBMITTED 4), `application_reviews` 8, `meetings` 2, `users` 13, `jobs` 0, `failed_jobs` 0;
- `jtict_decision` = PENDING bagi 8 rekod;
- `applications.updated_at` maksimum 2026-10-30 16:51:19 dan `audits.id` maksimum 348, kedua-duanya sama seperti sebelum PP4.1A.

**Data DB pembangunan tidak berubah.**

| Pengesahan wajib | Keputusan |
|---|---|
| Struktur dan rekod `application_reviews` tidak diubah | ✅ |
| `applications.jtict_decision` dikekalkan (ENUM asal; tidak ditulis) | ✅ |
| Rekod permohonan sedia ada tidak diubah | ✅ |
| Tiada rekod permohonan, keputusan atau salinan ujian dicipta dalam DB pembangunan | ✅ (ujian hanya pada SQLite in-memory) |

---

## 14. Pengesahan Tiada Perubahan Konfigurasi dan Operasi

- Tiada perubahan pada `.env`, SMTP, queue, `config/*` atau persekitaran.
- Tiada `migrate`, `migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe` atau seeder.
- Tiada SQL `DROP`, `TRUNCATE`, `DELETE` atau `UPDATE`.
- Tiada operasi Git.
- Tiada route, komponen Livewire, view, notifikasi, fungsi Salin Permohonan atau Modul Kemajuan Projek.
- **Migration sebenar belum dijalankan.**

---

## 15. Langkah Menjalankan Migration (MEMERLUKAN KELULUSAN PEMILIK SISTEM)

Arahan disediakan sahaja; **tiada yang dijalankan.**

**1. Sandaran.** Masukkan kata laluan DB apabila diminta; jangan taip kata laluan dalam arahan.

```powershell
mysqldump -u <pengguna_db> -p --single-transaction --routines --triggers sppictv2_db > db-backup-sebelum-pp4-1b-20261103.sql
```

**2. Sahkan sandaran:**
- saiz fail lebih daripada 0;
- fail mengandungi `CREATE TABLE \`applications\``, `\`application_reviews\`` dan `\`audits\``;
- baris terakhir ialah `-- Dump completed`.

**3. Jalankan migration:**

```powershell
php artisan migrate --pretend   # semak semula SQL sama seperti Seksyen 5
php artisan migrate             # BUKAN fresh/refresh/rollback
```

**4. Selepas migration:**
- `migrate:status` menunjukkan kedua-dua migration berstatus **Ran**;
- skrip semakan baca sahaja membandingkan bilangan rekod dan cap masa dengan Seksyen 13;
- jalankan `SHOW CREATE TABLE application_decisions` dan semak FK pada `applications`.

---

## 16. Perkara yang Belum Selesai

| Perkara | Status |
|---|---|
| Sandaran MySQL dan `php artisan migrate` | Memerlukan kelulusan pemilik sistem |
| Pengesahan struktur selepas migration pada MySQL | Belum dilaksanakan |
| Warna lencana status "Pindaan Pra JTICT" (kini kelabu lalai). Ditangguhkan supaya CSS tidak perlu dibina semula (`npm run build`). | Belum dilaksanakan (PP4.2) |
| Paparan ulasan Pra JTICT dalam borang pindaan dan butiran (`ApplicationForm::isAmendment()` dan `ViewApplication::amendmentRemarks()` masih khusus untuk Tidak Lengkap) | Belum dilaksanakan (PP4.2/PP4.3; UI di luar skop PP4.1B) |
| Kad "Pindaan Pra JTICT" di papan pemuka Urus Setia dan pilihan penapis senarai | Muncul secara automatik (bilangan 0), seperti dijangka dalam PP4.1A |
| Data lama: `SPPICT/2026/0003` dan `0004` (COMPLETE) **mempunyai** rekod PP2 Lengkap (29/10/2026), jadi layak untuk Pra JTICT apabila UI tersedia. Tarikh keputusan minimum ialah 29/10/2026. | Untuk makluman |

---

## 17. Cadangan Langkah Seterusnya

1. **Pemilik sistem:** semak SQL dalam Seksyen 5, dan luluskan sandaran serta `php artisan migrate`.
2. Selepas migration, jalankan pengesahan MySQL (Seksyen 15, langkah 4) dan kemas kini laporan ini.
3. **PP4.2 (antara muka Urus Setia):**
   - senarai Pra JTICT dan JTICT dengan carian dan penapis;
   - panel keputusan dan modal pengesahan yang memanggil `ApplicationDecisionService::decide()`;
   - sejarah keputusan, iaitu nama pegawai untuk Urus Setia sahaja (K5);
   - warna lencana status;
   - paparan "Selesai · Lulus (JTICT)".
4. **PP4.3:**
   - paparan pemohon (ulasan Pra JTICT dalam borang pindaan);
   - notifikasi e-mel;
   - fungsi Salin Permohonan menggunakan `copied_from_application_id`.

---

*PP4.1B dihentikan di sini. Menunggu kelulusan pemilik sistem sebelum menjalankan `php artisan migrate` pada pangkalan data pembangunan.*
