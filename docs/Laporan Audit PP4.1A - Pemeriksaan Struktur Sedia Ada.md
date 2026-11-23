# Laporan Audit PP4.1A: Pemeriksaan Struktur Sedia Ada

**Sistem:** SPPICT v2.1 (Laravel 13, Livewire 3, MySQL 8.0.46)
**Tarikh:** 3 November 2026
**Rujukan:** Prompt PP4.1 (Seksyen 13 dan 15), Laporan Audit PP4.0
**Status fasa:** **Berjaya.** PP4.1A selesai. PP4.1B belum dimulakan dan menunggu arahan pemilik sistem.
**Kawalan:**
- tiada perubahan kod, migration, data, `.env` atau konfigurasi;
- tiada operasi Git;
- pertanyaan DB hanya `SHOW` dan `SELECT`, melalui skrip baca sahaja di folder sementara (scratchpad).

Label: **[Disahkan]** daripada kod atau DB · **[Cadangan]** · **[Belum Pasti]** · **[Keputusan Pemilik Sistem]** · **[Risiko]**

---

## 1. Ringkasan

1. **[Disahkan] Pendekatan additive migration boleh digunakan dengan selamat.**
   - Jadual `application_decisions` dan lajur `copied_from_application_id` belum wujud.
   - Tiada migration tertunggak; 43 migration telah dijalankan, dan yang terakhir ialah batch 23.
   - Kedua-dua perubahan hanya **menambah**: satu jadual baharu dan satu lajur nullable. Tiada lajur sedia ada diubah dan tiada data ditulis.
2. **[Disahkan] Status baharu `PRE_JTICT_AMENDMENT` tidak memerlukan migration.** `applications.status` ialah `VARCHAR(30)` (19 aksara diperlukan). Ia ditambah melalui enum PHP sahaja.
3. **[Disahkan] Struktur tarikh mesyuarat mencukupi** untuk validasi keputusan JTICT. `meetings.meeting_date` bertipe `DATE NOT NULL`.
4. **[Keputusan Pemilik Sistem] Empat perkara perlu disahkan sebelum PP4.1B** (Seksyen 7):
   - K1: sejauh mana kitaran Pindaan Pra JTICT disokong dalam kod PP2;
   - K2: skop service (validasi sahaja, atau termasuk kaedah rekod keputusan);
   - K3: lajur `uuid`;
   - K4: had tarikh keputusan.

---

## 2. Struktur Sebenar yang Ditemui

### 2.1 Pangkalan data (MySQL pembangunan `sppictv2_db`) [Disahkan]

| Jadual | Enjin / collation | Jenis `id` | Rekod |
|---|---|---|---|
| `applications` | InnoDB / utf8mb4_unicode_ci | `bigint unsigned` | 8 (DRAFT 2, SUBMITTED 4, COMPLETE 2) |
| `meetings` | InnoDB / utf8mb4_unicode_ci | `bigint unsigned` | 2 |
| `users` | InnoDB / utf8mb4_unicode_ci | `bigint unsigned` | 13 |
| `application_reviews` | InnoDB / utf8mb4_unicode_ci | `bigint unsigned` | 8 |

| Medan | Jenis sebenar |
|---|---|
| `applications.status` | `varchar(30)` NOT NULL, lalai `DRAFT` |
| `applications.jtict_meeting_id` | `bigint unsigned` NULL, FK → `meetings` (ON DELETE NO ACTION) |
| `applications.jtict_decision` | `enum('PENDING','APPROVED','APPROVED_CONDITIONAL','REVIEW','REJECTED')` NOT NULL, lalai `PENDING` |
| `applications.deleted_at` | `timestamp` NULL (soft delete) |
| `meetings.meeting_date` | `date` **NOT NULL** |
| `meetings.deleted_at` | `timestamp` NULL (soft delete) |

**Peraturan FK sedia ada yang merujuk `applications` dan `meetings`:**

| FK | ON DELETE |
|---|---|
| `application_reviews.application_id`, `application_reviews.jtict_meeting_id` | NO ACTION (sejarah dilindungi) |
| `applications.jtict_meeting_id`, `applications.jpict_meeting_id` | NO ACTION |
| `projects.application_id` | NO ACTION |
| `application_details`, `application_documents`, `application_officers` (`application_id`) | CASCADE |

Rekod COMPLETE semasa:
- `SPPICT/2026/0003`: mesyuarat #1, bertarikh 23/01/2026;
- `SPPICT/2026/0004`: mesyuarat #2, bertarikh 23/11/2026.

### 2.2 Konvensyen projek [Disahkan]

| Perkara | Konvensyen | Contoh |
|---|---|---|
| Migration | Nama `YYYY_MM_DD_00000N_...`; `foreignId()->constrained()` tanpa cascade bagi sejarah; `down()` enggan memadam jadual sejarah yang berisi data | `2026_10_28_000002_create_application_reviews_table.php` |
| Status dan kod | `VARCHAR` + enum PHP (bukan ENUM MySQL) | `2026_10_28_000001_change_status_to_string_on_applications_table.php` |
| Rekod sejarah | `HasUuids` (`uuid` unique), `UPDATED_AT = null`, dan `LogicException` pada `updating` atau `deleting` | `app/Models/ApplicationReview.php` |
| Audit | Model utama (`Application`, `Meeting`) menggunakan owen-it `Auditable`; `ApplicationReview` tidak, kerana rekodnya sendiri ialah sejarah | |
| Enum | `app/Enums/*` (`ApplicationStatus`, `OfficerRole`, `UserStatus`, `InactiveReason`) dengan `label()` dan `options()` | |
| Service | `app/Services/*`: transaksi, `lockForUpdate`, semakan polisi semula di dalam transaksi | `ApplicationReviewService` |
| Kebenaran | Peranan + `ApplicationPolicy` (permission Spatie tidak digunakan dalam kod) | `ApplicationPolicy::review()` |
| Ujian | PHPUnit, `RefreshDatabase`, SQLite `:memory:` (`phpunit.xml`); tiada factory `Application` (setiap ujian ada `makeApplication()` sendiri) | `tests/Feature/ApplicationStatusTest.php` |

### 2.3 Kod yang bergantung pada status [Disahkan]

| Lokasi | Tingkah laku semasa | Kesan status `PRE_JTICT_AMENDMENT` |
|---|---|---|
| `ApplicationStatus::editableCases()` / `allowedTransitions()` | Draf dan Tidak Lengkap boleh disunting dan dihantar | **Perlu ditambah**, supaya pindaan dan hantar semula dibenarkan |
| `Application::EDITABLE_STATUSES` | Pemalar, diuji sama dengan `editableCases()` | **Perlu ditambah**. `ApplicationPolicy::update()` dan `isEditable()` kemudian berfungsi secara automatik. |
| `ApplicationSubmissionService::submit()` | `canTransitionTo(Submitted)` daripada enum. Rekod `application_reviews` "Dihantar Semula" **hanya jika status sebelum = `NEEDS_AMENDMENT`**. | Hantar semula dibenarkan secara automatik, **tetapi tiada rekod sejarah dalam `application_reviews`** melainkan kod PP2 diubah (K1) |
| `Application::targetJtictMeeting()` / `requiresMeetingSelection()` | Peraturan PP2 "kekal pada mesyuarat asal jika masih dibuka; jika tidak, pilih mesyuarat aktif" **hanya bagi `NEEDS_AMENDMENT`**. Status lain menggunakan `Meeting::currentJtict()`. | Tanpa pelarasan, permohonan Pindaan Pra JTICT **dipindahkan secara automatik ke mesyuarat yang sedang dibuka**. Ini bercanggah dengan prompt 9.7 ("kekal mengikut ketetapan PP2"). (K1) |
| `SubmissionClosedException` | Mesej khas bagi `NEEDS_AMENDMENT` | Mesej draf dipaparkan (kosmetik) |
| `ViewApplication::amendmentRemarks()`, `ApplicationForm::isAmendment()` | Ulasan Tidak Lengkap PP2 | UI; ditangguhkan ke PP4.2 atau PP4.3 |
| Lencana status (`application-status-badge`) | Warna bagi 6 status; status lain kelabu | Kelabu sehingga warna ditambah |
| Papan pemuka Urus Setia, `ApplicationList` | Kad dan penapis dibina daripada `ApplicationStatus::cases()` | Kad "Pindaan Pra JTICT" (0) dan pilihan penapis **muncul secara automatik** |

**[Disahkan] Nota penting:** dalam PP4.1, tiada route atau UI yang boleh merekod keputusan. Oleh itu **tiada permohonan sebenar boleh mencapai status `PRE_JTICT_AMENDMENT`** dalam PP4.1. Kod yang belum dilaraskan tidak akan mencetuskan tingkah laku salah sebelum PP4.2.

### 2.4 Ujian sedia ada yang terjejas [Disahkan]

| Ujian | Sebab | Tindakan |
|---|---|---|
| `ApplicationStatusTest::test_status_values_and_malay_labels` | Senarai tepat status | Tambah `PRE_JTICT_AMENDMENT => 'Pindaan Pra JTICT'` |
| `ApplicationStatusTest::test_allowed_transitions_follow_workflow` | Setiap pasangan peralihan | Tambah `COMPLETE → PRE_JTICT_AMENDMENT` dan `PRE_JTICT_AMENDMENT → SUBMITTED` |
| `ApplicationStatusTest::test_editable_statuses_come_from_enum` | `EDITABLE_STATUSES` = enum | Lulus selepas pemalar dikemas kini (tiada perubahan ujian) |
| `ApplicationStatusTest::test_process_status_does_not_contain_meeting_decisions` | Label status bukan label keputusan | "Pindaan Pra JTICT" **tidak bercanggah**; tiada perubahan |
| `UrusSetiaDashboardTest::test_status_cards_...` | Regex `>Tidak Lengkap<` | Tidak bercanggah dengan label baharu |

Dua ujian pertama perlu dikemas kini **kerana keperluan sebenar berubah** (status baharu), bukan untuk memaksa ujian lulus. Justifikasi akan dilaporkan dalam laporan PP4.1.

---

## 3. Cadangan Perubahan Terperinci

### 3.1 Migration 1: `2026_11_03_000001_create_application_decisions_table.php` [Cadangan]

```php
Schema::create('application_decisions', function (Blueprint $table): void {
    $table->id();
    $table->uuid('uuid')->unique();                                   // K3: konvensyen projek
    $table->foreignId('application_id')->constrained('applications'); // NO ACTION: sejarah dilindungi
    $table->string('stage', 20);                                      // App\Enums\DecisionStage
    $table->string('decision', 30);                                   // App\Enums\ApplicationDecision
    $table->string('from_status', 30);
    $table->string('to_status', 30);
    $table->text('remarks')->nullable();
    $table->date('decision_date');
    $table->foreignId('meeting_id')->nullable()->constrained('meetings'); // NO ACTION
    $table->foreignId('decided_by')->constrained('users');               // NO ACTION
    $table->timestamp('created_at')->useCurrent();                       // tiada updated_at

    $table->index(['application_id', 'stage', 'created_at']);
    $table->index(['stage', 'decision']);
    // meeting_id dan decided_by: indeks dicipta secara automatik oleh FK
});
```

`down()` mengikut corak `application_reviews`: **enggan** membuang jadual jika ia mengandungi rekod.

**Kenapa VARCHAR, bukan ENUM MySQL atau CHECK constraint:**
- Ia konsisten dengan keputusan PP1 bagi `status`.
- Pilihan keputusan boleh ditambah tanpa migration.
- Kesahihan `stage` dan `decision` dikuatkuasakan di dua lapisan: model (`creating`) dan service (Seksyen 3.3).

**[Belum Pasti]** CHECK constraint di peringkat DB boleh ditambah (MySQL 8.0.16+ menyokongnya), tetapi sintaks dan ujiannya berbeza antara SQLite dan MySQL. Ia tidak dicadangkan.

### 3.2 Migration 2: `2026_11_03_000002_add_copied_from_application_id_to_applications_table.php` [Cadangan]

```php
Schema::table('applications', function (Blueprint $table): void {
    $table->foreignId('copied_from_application_id')->nullable()->after('jpict_remarks')
        ->constrained('applications'); // NO ACTION: bukan cascade, bukan set null
});
```

- Rekod sedia ada kekal `NULL`. Tiada backfill.
- **ON DELETE NO ACTION**, bukan `nullOnDelete` seperti dalam cadangan PP0. Hubungan asal–salinan ialah rekod sejarah dan tidak boleh hilang secara senyap. Permohonan menggunakan soft delete, jadi pemadaman kekal jarang berlaku.
- Indeks unique (satu salinan sahaja) **tidak** ditambah sekarang. Ia bergantung pada keputusan fasa Salin Permohonan.
- `down()`: enggan membuang lajur jika ada nilai bukan `NULL`.
- **[Disahkan]** Menambah lajur nullable pada InnoDB MySQL 8 tidak mengubah data sedia ada. Bagi 8 rekod, kesan masa dan kunci jadual boleh diabaikan.

### 3.3 Enum, model dan relationship [Cadangan]

| Fail | Kandungan |
|---|---|
| `app/Enums/ApplicationStatus.php` (diubah) | Kes `PreJtictAmendment = 'PRE_JTICT_AMENDMENT'`, label "Pindaan Pra JTICT", penerangan, peralihan `Complete → [InJtict, PreJtictAmendment, Closed]` dan `PreJtictAmendment → [Submitted]`, serta `editableCases()`. Komen docblock yang merujuk `jtict_decision` dikemas kini. |
| `app/Enums/DecisionStage.php` (baharu) | `PreJtict = 'PRE_JTICT'`, `Jtict = 'JTICT'`, label, `decisions()`, dan `requiredStatus()` (`COMPLETE` / `IN_JTICT`) |
| `app/Enums/ApplicationDecision.php` (baharu) | 6 kod keputusan; `stage()`, `label()`, `targetStatus()`, `requiresRemarks()` (mengikut jadual 8.1 prompt), `forStage()` |
| `app/Models/ApplicationDecision.php` (baharu) | `HasUuids`, `UPDATED_AT = null`, `$fillable` terhad, cast enum. `creating` menolak pasangan stage–decision yang tidak sepadan. `updating` dan `deleting` melontar `LogicException`. Relationship `application()`, `decidedBy()` (`withTrashed`) dan `meeting()` (`withTrashed`). |
| `app/Models/Application.php` (diubah) | `EDITABLE_STATUSES`, `decisions()`, `copiedFromApplication()`, `copies()`. `copied_from_application_id` **tidak** dimasukkan ke `$fillable` dan hanya ditetapkan oleh service salinan kelak. |

**[Risiko] Had perlindungan "immutable":** event model tidak dicetuskan oleh query builder secara pukal, contohnya `ApplicationDecision::query()->update(...)` atau `DB::table(...)->delete()`. Corak ini sama dengan `application_reviews` dan dilindungi oleh disiplin kod. Trigger DB **tidak** dicadangkan kerana ia di luar konvensyen projek dan tidak diuji pada SQLite.

### 3.4 Service dan Policy [Cadangan]

**`app/Services/ApplicationDecisionService.php`** (baharu), mengikut corak `ApplicationReviewService`. Validasi:

| # | Peraturan | Sumber |
|---|---|---|
| 1 | `decision` mesti sepadan dengan `stage` | Prompt 4.1 |
| 2 | Pra JTICT: status `COMPLETE`. JTICT: status `IN_JTICT` **dan** keputusan Pra JTICT terkini = `ACCEPTED` | Prompt 8.3, 8.4, 8.10 |
| 3 | Ulasan wajib bagi `AMENDMENT_REQUIRED`, `APPROVED`, `NOT_APPROVED` dan `REVIEW`; panjang 10–5000 aksara seperti PP2; tag HTML dibuang | Prompt 8.1 |
| 4 | JTICT: `meeting_id` = `applications.jtict_meeting_id` (mesyuarat JTICT yang tidak dipadam), dan `decision_date ≥ meetings.meeting_date` | Prompt 9.1, 9.2, 9.5 |
| 5 | Pra JTICT: `decision_date` dimasukkan oleh Urus Setia; `meeting_id` = mesyuarat JTICT permohonan pada masa keputusan (rujukan sahaja) | Prompt 9.3 |
| 6 | Pengguna: `can('decidePreJtict')` atau `can('decideJtict')` | Prompt 8.7, 10 |
| 7 | `decided_by`, `from_status`, `to_status` dan `stage` ditentukan oleh pelayan, bukan input | Keselamatan payload |

**`ApplicationPolicy`** (kaedah baharu sahaja; kaedah sedia ada tidak diubah):
- `decidePreJtict`: `canUseSystem`, peranan `pentadbir_urus_setia`, bukan `superadmin` atau `pengurusan`, status `COMPLETE`.
- `decideJtict`: syarat yang sama, status `IN_JTICT`, dan keputusan Pra JTICT terkini = `ACCEPTED`.

**Tiada route, komponen Livewire, view atau notifikasi** ditambah dalam PP4.1.

---

## 4. Senarai Fail yang Dijangka Terlibat

**Baharu:**

| Fail |
|---|
| `database/migrations/2026_11_03_000001_create_application_decisions_table.php` |
| `database/migrations/2026_11_03_000002_add_copied_from_application_id_to_applications_table.php` |
| `app/Enums/DecisionStage.php` |
| `app/Enums/ApplicationDecision.php` |
| `app/Models/ApplicationDecision.php` |
| `app/Services/ApplicationDecisionService.php` |
| `tests/Feature/ApplicationDecisionTest.php` (ujian A–E prompt Seksyen 11) |
| `docs/Laporan Pelaksanaan PP4.1 - Struktur Asas Keputusan Pra JTICT dan JTICT.md` |

**Diubah:**

| Fail | Perubahan |
|---|---|
| `app/Enums/ApplicationStatus.php` | Status baharu dan peralihan |
| `app/Models/Application.php` | `EDITABLE_STATUSES`, relationship baharu. Tambahan K1 jika dipilih: `targetJtictMeeting()` dan `requiresMeetingSelection()` |
| `app/Policies/ApplicationPolicy.php` | 2 kaedah baharu |
| `resources/views/components/application-status-badge.blade.php` | Satu baris warna (pilihan; elak lencana kelabu) |
| `tests/Feature/ApplicationStatusTest.php` | 2 ujian dikemas kini (Seksyen 2.4) |
| Tambahan K1 jika dipilih: `app/Services/ApplicationSubmissionService.php`, `app/Models/ApplicationReview.php` | Rekod sejarah hantar semula dan labelnya |

**Tidak disentuh:**
- `application_reviews` (struktur dan rekod);
- `ApplicationReviewService` (semakan PP2);
- `applications.jtict_decision`;
- Modul Mesyuarat;
- route, `.env` dan konfigurasi.

---

## 5. Risiko dan Kesan terhadap Modul PP Sedia Ada

| # | Risiko / kesan | Tahap | Kawalan |
|---|---|---|---|
| R1 | Status baharu muncul secara automatik sebagai kad papan pemuka dan pilihan penapis (bilangan 0) | Rendah | Dijangka dan tidak berbahaya. Kad tersebut ialah status proses, bukan "dashboard keputusan". |
| R2 | Hantar semula daripada Pindaan Pra JTICT tidak direkod dalam `application_reviews`, dan mesyuarat berpindah secara automatik, jika K1 = "minimum" | Sederhana (hanya apabila PP4.2 membolehkan keputusan) | K1 |
| R3 | Kemas kini dua ujian `ApplicationStatusTest` | Rendah | Dijustifikasikan; suite penuh dijalankan |
| R4 | Perlindungan immutable boleh dipintas oleh query builder pukal | Rendah | Sama seperti PP2; dilaporkan |
| R5 | Migration pada MySQL tidak dibuktikan oleh ujian SQLite | Rendah | `php artisan migrate --pretend` disemak dahulu; sandaran DB (`mysqldump`) sebelum `migrate`; perbandingan bilangan dan cap masa rekod sebelum dan selepas |
| R6 | FK `NO ACTION` menghalang pemadaman kekal permohonan, mesyuarat atau pengguna yang mempunyai keputusan | Rendah; **dikehendaki** | Soft delete digunakan |
| R7 | Prompt 10: "pemohon hanya melihat keputusan permohonannya sendiri", tetapi polisi `view` sedia ada turut membenarkan rakan agensi dan pegawai | Tiada kesan dalam PP4.1 (tiada UI) | Perlu diputuskan sebelum PP4.3 (K5) |
| R8 | Pra JTICT **Ditolak** tanpa ulasan wajib (keputusan pemilik sistem). Audit PP4.0 mencadangkan wajib. | Dasar | Dipatuhi seperti ditetapkan; dicatat untuk rujukan |

---

## 6. Pengesahan Pendekatan Additive Migration

| Semakan | Keputusan |
|---|---|
| Jadual `application_decisions` belum wujud | ✅ Disahkan |
| Lajur `copied_from_application_id` belum wujud | ✅ Disahkan |
| Tiada migration tertunggak yang boleh berjalan bersama | ✅ Disahkan |
| Jenis FK (`bigint unsigned`) dan enjin (InnoDB) serasi | ✅ Disahkan |
| Tiada `ALTER` pada lajur sedia ada, tiada `DROP`, tiada pengisian data | ✅ Reka bentuk |
| Status baharu tanpa migration (VARCHAR) | ✅ Disahkan |
| `down()` tidak memusnahkan sejarah secara senyap | ✅ Reka bentuk (corak `application_reviews`) |

**Kesimpulan:** pendekatan additive migration **boleh digunakan**.

Langkah yang dicadangkan apabila PP4.1B diluluskan:
1. Tulis migration.
2. Jalankan `migrate --pretend` dan laporkan SQL kepada anda.
3. Ambil sandaran DB.
4. Jalankan `php artisan migrate` (bukan `fresh`, `refresh` atau `rollback`) **hanya selepas anda bersetuju**.

---

## 7. Perkara yang Memerlukan Keputusan Pemilik Sistem

| # | Soalan | Pilihan | Cadangan |
|---|---|---|---|
| **K1** | Sejauh mana kitaran Pindaan Pra JTICT disokong dalam PP4.1? | **(a) Minimum:** enum, peralihan dan `EDITABLE_STATUSES` sahaja; pelarasan PP2 ditangguhkan ke PP4.2. **(b) Lengkap:** termasuk (i) peraturan mesyuarat PP2 juga terpakai bagi Pindaan Pra JTICT (`Application`); dan (ii) hantar semula direkod dalam `application_reviews` sebagai "Dihantar Semula" (`ApplicationSubmissionService`, `ApplicationReview`). Ini menambah **rekod** (bukan struktur) dalam `application_reviews`. | **(b)(i) dalam PP4.1.** (b)(ii) memerlukan persetujuan anda kerana prompt melarang perubahan fungsi PP2. Tanpanya, sejarah hantar semula Pindaan Pra JTICT hanya ada dalam log audit owen-it. |
| **K2** | Skop service dalam PP4.1 | **(a)** Validasi sahaja. **(b)** Validasi **dan** kaedah `decide()` yang lengkap (transaksi, kunci rekod, rekod keputusan, ubah status), diuji tetapi **tidak dipanggil** oleh mana-mana route atau UI. | **(b).** Ujian integriti sejarah (prompt 11 C) dan "status berubah" boleh dibuat dengan sebenar, dan PP4.2 hanya membina UI. |
| **K3** | Lajur `uuid` dalam `application_decisions` (tiada dalam jadual prompt) | Tambah / tidak | **Tambah.** Konvensyen semua jadual sejarah, dan rujukan selamat dalam URL kelak. |
| **K4** | Had tarikh keputusan | (a) `decision_date` tidak boleh pada masa hadapan (kedua-dua peringkat); (b) Pra JTICT tidak lebih awal daripada tarikh permohonan ditetapkan Lengkap | **(a) dan (b)** |
| **K5** | (Untuk PP4.3) Siapa melihat keputusan: pendaftar sahaja, atau semua yang dibenarkan oleh polisi `view` (pegawai dan rakan agensi)? | | Ikut polisi `view` sedia ada, tanpa nama pegawai Urus Setia |

---

*PP4.1A sahaja. Tiada perubahan kod, migration, seeder, data, `.env`, konfigurasi atau operasi Git dilakukan. Kerja dihentikan untuk menunggu arahan pemilik sistem sebelum PP4.1B.*
