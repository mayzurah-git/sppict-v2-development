# SP0 – Audit Struktur Sedia Ada dan Analisis Integrasi
## Modul Status Kemajuan Projek · SPPICT v2

| Perkara | Maklumat |
|---|---|
| Tarikh audit | 19/11/2026 |
| Kemas kini | 20/11/2026: keputusan pemilik I.2, I.7, I.12 serta keperluan baharu (tanda siap, projek berfasa, data v1 / Bank Projek). Lihat **Bahagian L**. Bahagian E, F, G, H, I, J dan K telah diselaraskan. |
| Jenis | Audit dan analisis sahaja (read-only) |
| Persekitaran | Pembangunan: `C:\Users\mayzurah\Herd\www\sppict-v2.1`, pangkalan data `sppictv2_db` |
| Branch / commit | `feature/pp4-sst-checkbox` @ `b07c1af` |
| Working tree | Bersih, kecuali satu fail tidak dijejak: `Prompt-Pengurusan-Log-Harian.md` (tidak disentuh) |
| Versi rangka kerja | Laravel 13.23.0 / PHP 8.4.27 (laporan setup UAT 17/11/2026). Spesifikasi menyebut Laravel 12; kod mematuhi corak Laravel 12+. |
| Keputusan | **SEDIA DENGAN SYARAT** (Bahagian K) |

**Kaedah pemeriksaan:**
- Membaca kod, migration dan view.
- Membaca struktur pangkalan data dengan `php artisan db:table` (read-only).
- Menjalankan pertanyaan `SELECT` sahaja melalui skrip sementara dalam folder scratchpad sesi (di luar projek).
- `php artisan route:list` (read-only).

**Tidak dilakukan:**
- Tiada migration, seeder atau ujian automatik dijalankan.
- Tiada kod diubah.
- Tiada branch ditukar dan tiada commit dibuat.

Label dapatan:
- **[FAKTA]**: disahkan daripada kod atau pangkalan data.
- **[CADANGAN]**: cadangan reka bentuk.
- **[BELUM DAPAT DISAHKAN]**: tidak dapat disahkan daripada bukti yang ada.

---

## Bahagian A – Ringkasan Eksekutif

### A.1 Keadaan semasa

1. **[FAKTA] Struktur asas pemantauan sudah wujud tetapi tidak digunakan.**
   - Jadual `projects`, `project_progress_reports` dan `mid_term_reviews` dicipta dalam `2026_08_04_000001_create_sppict_core_tables.php`, bersama model `Project`, `ProjectProgressReport` dan `MidTermReview`.
   - Ketiga-tiga jadual **kosong** (0 rekod).
   - Tiada kod yang mencipta rekod `projects`: tiada `Project::create` dalam `app/`.
2. **[FAKTA] Papan pemuka pemohon sudah ada paparan kemajuan.** `resources/views/dashboard/pengguna.blade.php` mempunyai bahagian "Senarai Status Kemajuan Projek" yang:
   - membaca `projects`, laporan bulanan terkini dan `status_indicator` (GREEN/AMBER/RED);
   - memaparkan kad tarikh akhir kemas kini kemajuan daripada `meetings.progress_update_deadline`.

   Paparan ini sentiasa kosong kerana tiada data.
3. **[FAKTA] Keputusan Lulus JTICT boleh dikenal pasti dengan tepat.**
   - Keputusan disimpan dalam `application_decisions` (tidak boleh diubah atau dipadam).
   - Relasi `Application::latestJtictDecision()` memulangkan keputusan JTICT terkini.
4. **[FAKTA] Pangkalan data pembangunan tiada keputusan JTICT langsung.**
   - Hanya ada 4 Pra JTICT `ACCEPTED` dan 1 `AMENDMENT_REQUIRED`.
   - Oleh itu tiada projek Lulus JTICT yang boleh digunakan untuk ujian manual sekarang.
5. **[FAKTA] Jenis Perolehan dipetakan secara satu-ke-satu kepada empat templat.** Pemetaan menggunakan master data `procurement_types` (4 rekod aktif dengan kod unik yang dikunci apabila digunakan).
6. **[FAKTA] Akses menggunakan Policy dan peranan, bukan permission Spatie.**
   - Permission `update-project-progress` dan `verify-project-progress` wujud dalam pangkalan data tetapi **tidak dirujuk** dalam kod.
   - `ApplicationPolicy::updateProgress()` wujud tetapi tidak cukup ketat (C.6).

### A.2 Kesediaan

Sistem **sedia dengan syarat** untuk SP1:
- Struktur pangkalan data boleh ditambah secara aditif.
- Integrasi dengan JTICT tidak memerlukan perubahan pada aliran keputusan.

Syaratnya ialah beberapa keputusan pemilik proses (Bahagian I). **Kemas kini 20/11/2026:** keputusan yang diperlukan untuk SP1 (I.2, I.7, I.12) telah dibuat (Bahagian L). Keputusan yang masih terbuka:
- layanan projek ≥ RM500,000 yang memerlukan JPICT (sebelum SP2);
- sumber tarikh jadual aktiviti;
- kriteria penutupan projek;
- kedudukan kemajuan kewangan dan pengesahan Urus Setia, yang bercanggah dengan PRD.

---

## Bahagian B – Struktur Database Sedia Ada

### B.1 Permohonan Projek (SP0.1-A)

| Jadual | Fungsi | PK | FK utama | Kolum berkaitan | Migration | Status |
|---|---|---|---|---|---|---|
| `applications` | Rekod permohonan (42 kolum) | `id` bigint + `uuid` char(36) unik | `agency_id`→agencies, `applicant_id`→users, `procurement_type_id`→procurement_types (**restrict**), `procurement_method_id`→procurement_methods (**restrict**), `jtict_meeting_id`/`jpict_meeting_id`→meetings, `copied_from_application_id`→applications | `reference_number` (unik), `title`, `application_category` (kod master), `procurement_type` (snapshot nama), `ceiling_cost`, `estimated_cost`, `subtotal_cost`, `sst_applicable`, `sst_rate`, `sst_amount`, `total_cost`, `expected_duration_months` (int), `outcome_code`, `status` varchar(30), `deleted_at` | `2026_08_04_000001`, `2026_10_02_000001/2`, `2026_10_07_000008/9`, `2026_10_28_000001`, `2026_11_03_000002`, `2026_11_19_000001` | Wujud |
| `application_details` | Perincian item (Fasa 3) | `id` + `uuid` | `application_id` (cascade), `project_category_id` (restrict) | `technical_specifications`, `unit_quantity`, `unit_cost`, `total_cost` | `2026_08_04_000001`, `2026_10_07_000007` | Wujud |
| `procurement_types` | Master Jenis Perolehan | `id` + `uuid` | — | `code` (unik), `name`, `sort_order`, `is_active`, `deleted_at` | `2026_10_07_000005` | Wujud |
| `procurement_methods` | Master Kaedah Perolehan | `id` + `uuid` | — | `code`, `name`, `is_active` | `2026_10_07_000006` | Wujud |
| `application_categories` | Master Kategori Permohonan | `id` + `uuid` | Dirujuk melalui `applications.application_category` = `code` (bukan FK) | `code`, `name`, `is_active` | `2026_10_07_000003` | Wujud |
| `project_categories` | Master Kategori Projek (Fasa 3) | `id` + `uuid` | — | `code`, `name`, `sort_order` | `2026_10_07_000004` | Wujud |

**[FAKTA] Data semasa:**
- 9 permohonan: DRAFT 3, SUBMITTED 2, IN_JTICT 4.
- 0 dipadam dan 0 salinan.
- **Tiada maklumat tarikh mula atau tamat projek dirancang dalam permohonan.** Hanya `expected_duration_months` disimpan.

### B.2 Keputusan Pra JTICT dan JTICT (SP0.1-B)

| Jadual | Fungsi | PK | FK | Kolum berkaitan | Migration | Status |
|---|---|---|---|---|---|---|
| `application_decisions` | Sejarah keputusan Pra JTICT dan JTICT (append-only) | `id` + `uuid` | `application_id`, `meeting_id`, `decided_by` | `stage` (`PRE_JTICT`/`JTICT`), `decision` (`ACCEPTED`, `AMENDMENT_REQUIRED`, `REJECTED`, `APPROVED`, `NOT_APPROVED`, `REVIEW`), `from_status`, `to_status`, `remarks`, `decision_date`, `created_at`. Indeks (`application_id`, `stage`, `created_at`). | `2026_11_03_000001` | Wujud |
| `application_reviews` | Sejarah semakan kelengkapan (PP2) | `id` + `uuid` | `application_id`, `user_id`, `jtict_meeting_id` | `from_status`, `to_status`, `remarks` | `2026_10_28_000002` | Wujud |
| `meetings` | Mesyuarat Pra JTICT/JTICT/JPICT | `id` + `uuid` | master nama/bilangan/tahun | `meeting_date`, `submission_opens_at`/`closes_at`, **`progress_update_deadline`**, `is_active` | `2026_08_04_000001`, `2026_10_05_000004`, `2026_10_06_000004`, `2026_10_07_000001/2` | Wujud |
| Kolum warisan `applications.jtict_decision`, `jpict_decision` | Enum lama | — | — | Semua 9 rekod: `PENDING` / `NOT_REQUIRED`. **Tidak digunakan** (komen dalam `App\Enums\ApplicationStatus`). | `2026_08_04_000001` | Wujud, warisan |

**[FAKTA] Data semasa:** `application_decisions` mengandungi PRE_JTICT `ACCEPTED` 4 dan `AMENDMENT_REQUIRED` 1. **Tiada rekod peringkat JTICT.**

### B.3 Pengguna dan Agensi (SP0.1-C)

| Jadual | Fungsi | Kaitan |
|---|---|---|
| `users` | Akaun (13 rekod) | `agency_id`→agencies, `position_id`, `status`, `must_change_password`, `last_login_at` |
| `agencies` | Jabatan/agensi (9 rekod) | Dirujuk oleh `applications.agency_id`, `projects.agency_id` |
| `roles`, `permissions`, `model_has_roles`, `role_has_permissions`, `model_has_permissions` | Spatie Permission | 4 peranan: `pengguna_biasa` (10 pengguna), `pentadbir_urus_setia` (1), `pengurusan` (1), `superadmin` (1) |
| `application_officers` | Sejarah Pegawai Utama/Kedua | `application_id` (cascade), `user_id`, `role` (`PRIMARY`/`SECONDARY`), `agency_id`, `started_at`, `ended_at`, `end_reason`. Pegawai aktif = `ended_at` null (`ApplicationOfficer::scopeActive`). Migration `2026_10_05_000001`. |

**[FAKTA] Hubungan pemohon:**
- `applications.applicant_id` ialah pendaftar rekod.
- Pegawai bertanggungjawab disimpan dalam `application_officers`.
- Kolum `primary_officer_*` dan `secondary_officer_*` dalam `applications` ialah warisan dan tidak digunakan lagi (`Application.php` `$fillable`, komen).

### B.4 Dokumen (SP0.1-D)

| Perkara | Dapatan |
|---|---|
| Jadual | `application_documents`: `id` + `uuid`, `application_id` (**cascade**), `document_type` **enum**(`PROPOSAL_PAPER`, `PRESENTATION_SLIDE_JTICT`, `PRESENTATION_SLIDE_JPICT`, `SUPPORTING_DOC`), `file_name`, `file_path`, `mime_type`, `file_size_kb`, soft delete. 15 rekod. |
| Storan | Disk `local` (`storage/app/private`), laluan `applications/{uuid}/documents` (`ApplicationSubmissionService::DOCUMENT_DISK`, baris 35 dan 452). Fail tidak awam. |
| Akses | `ApplicationDocumentController` menyemak `Gate::authorize('view', $application)`, kemudian strim fail (PDF inline, Word attachment). Route `application.document`. |
| Polisi muat naik | `Forms\ApplicationForm::DOCUMENT_FORMATS` (PDF/DOCX), maksimum 25 MB, semakan tandatangan fail `App\Rules\DocumentSignature`. |
| Kesesuaian untuk kemajuan | **Tidak sesuai digunakan semula secara langsung.** Jenis dokumen ialah enum tertutup yang terikat kepada permohonan, dan cascade ke permohonan. Corak storan, pengawal dan semakan tandatangan **boleh digunakan semula**. |

### B.5 Jadual pemantauan sedia ada (boleh diguna semula)

| Jadual | Kolum | Kekangan / isu |
|---|---|---|
| `projects` | `application_id`, `agency_id`, `contractor_name`, `actual_cost`, `start_date`, `end_date`, `actual_completion_date`, `status_indicator` enum(GREEN, AMBER, RED), `physical_progress_percent`, `financial_progress_percent`, `is_completed`, soft delete | **Tiada indeks unik pada `application_id`.** Risiko pendua (H.4). FK tanpa cascade. |
| `project_progress_reports` | `project_id` (cascade), `report_year`, `report_month`, `submitted_by`, `application_officer_id`, `physical_actual/planned`, `financial_actual/planned` decimal(5,2), `work_summary`, `issues_and_challenges`, `corrective_action_plan`, `verified_status` enum(PENDING, VERIFIED, REJECTED), `verified_by`, `verified_at` | **Tiada unik (`project_id`, `report_year`, `report_month`).** `physical_planned` wajib diisi secara manual (tiada pengiraan). |
| `mid_term_reviews` | Kajian Separuh Penggal | Di luar skop SP0–SP5 |

---

## Bahagian C – Struktur Kod Sedia Ada

### C.1 Pemetaan fungsi (SP0.2)

| Fungsi | Model | Controller / Service | Route | View |
|---|---|---|---|---|
| Permohonan Projek | `Application`, `ApplicationDetail`, `ApplicationOfficer` | Livewire `ApplicationForm`, Form Object `Forms\ApplicationForm`, `ApplicationSubmissionService`, `ApplicationCopyService`, `ProjectDetailsCsvService` | `application.create` (`/application/new`), `application.edit` (`/application/edit/{uuid}`), `application.view` (`/application/view/{uuid}`), `applications.index` (`/permohonan`) | `livewire/application-form*.blade.php`, `livewire/view-application.blade.php`, `livewire/application-list.blade.php` |
| Jenis Perolehan | `ProcurementType` | `MasterData\ProcurementTypeManagement` (extends `MasterDataManagement`) | `master-data.procurement-types` | `livewire/master-data/management.blade.php` |
| Semakan Urus Setia | `ApplicationReview` | `ApplicationReviewService`, `ViewApplication` | `application.view` | `view-application.blade.php` |
| Keputusan Pra JTICT | `ApplicationDecision` (stage PRE_JTICT) | `ApplicationDecisionService::decide()`, Livewire `PreJtictList`, `ViewApplication` | `pre-jtict.index` (`/urus-setia/pra-jtict`) | `livewire/pre-jtict-list.blade.php` |
| Keputusan JTICT | `ApplicationDecision` (stage JTICT) | `ApplicationDecisionService::decide()`, Livewire `JtictList`, `ViewApplication` | `jtict.index` (`/urus-setia/jtict`) | `livewire/jtict-list.blade.php`, `partials/jtict-status.blade.php` |
| Status Lulus JTICT | `Application::latestJtictDecision()` + `Enums\ApplicationDecision::Approved` | — | — | `dashboard/pengguna.blade.php` (kad "Permohonan Projek Lulus"), `application-list.blade.php` (lajur Keputusan) |
| Dokumen | `ApplicationDocument` | `ApplicationDocumentController`, `ApplicationSubmissionService` | `application.document` | `application-form/phase-4-documents.blade.php` |
| Pengguna & agensi | `User`, `Agency`, `Position`, `Grade` | `Users\*`, `Admin\UserManagement`, `OfficerHandoverService` | `users.*`, `admin.users`, `admin.agencies` | `livewire/users/*`, `livewire/admin/*` |
| Dashboard | `Project` (dibaca sahaja) | Tiada service. Logik dalam Blade. | `dashboard` | `dashboard.blade.php` → `dashboard/pengguna.blade.php`, `dashboard/urus-setia.blade.php`. Superadmin: "Dashboard Induk". Pengurusan: hanya status JTICT. |
| Laporan | — | **Tiada** | **Tiada** | **Tiada** |
| Status Kemajuan Projek | `Project`, `ProjectProgressReport` (tiada UI) | **Tiada** | **Tiada** | Paparan di papan pemuka pemohon sahaja |

### C.2 Middleware

`auth`, `verified`, `active` (`EnsureUserIsActive`), `password.changed` (`EnsurePasswordIsChanged`), `role:` (Spatie), `not-role:` (`EnsureUserDoesNotHaveRole`), `can:`.

**[FAKTA]** Laluan permohonan menggunakan `not-role:superadmin`, jadi **Superadmin tidak boleh membuka permohonan**.

### C.3 Polisi akses (`app/Policies/ApplicationPolicy.php`)

| Method | Ringkasan |
|---|---|
| `view` | Pemohon, Urus Setia, pengguna seagensi, atau pegawai aktif. **Pengurusan dan Superadmin tidak termasuk** (kecuali sebagai pengguna seagensi). |
| `update` | Status boleh disunting dan (pendaftar semasa Draf, atau pegawai aktif seagensi) |
| `decideJtict` | Urus Setia sahaja (`canRecordDecision`: bukan superadmin/pengurusan). Status `IN_JTICT`, Pra JTICT terkini `ACCEPTED`, **tiada keputusan JTICT sedia ada**, mesyuarat sah. |
| `copy` | Keputusan JTICT terkini `REVIEW`, satu salinan sahaja |
| `updateProgress` | `! isEditable()` + seagensi + pegawai aktif. **Tidak menyemak keputusan Lulus JTICT** (C.6). |

### C.4 Enum berkaitan

- `App\Enums\ApplicationStatus`: `DRAFT`, `SUBMITTED`, `NEEDS_AMENDMENT`, `COMPLETE`, `PRE_JTICT_AMENDMENT`, `IN_JTICT`, `CLOSED`.
- `App\Enums\ApplicationDecision`: Pra JTICT (`ACCEPTED`, `AMENDMENT_REQUIRED`, `REJECTED`) dan JTICT (`APPROVED` "Lulus", `NOT_APPROVED` "Tidak Lulus", `REVIEW` "Kaji Semula"). `targetStatus()`: **semua** keputusan JTICT menghasilkan `CLOSED`.
- `App\Enums\DecisionStage`: `PRE_JTICT`, `JTICT`.
- `App\Enums\OfficerRole`: Primary / Secondary.

### C.5 Audit trail

- **[FAKTA]** `owen-it/laravel-auditing` aktif pada `Application`, `ApplicationDetail`, `ApplicationDocument`, `ApplicationOfficer`, `Project`, `ProjectProgressReport`, `ProcurementType` dan lain-lain (jadual `audits`).
- **[FAKTA]** `ApplicationDecision` tidak diaudit melalui pakej. Sebaliknya ia **append-only**: `booted()` membaling `LogicException` pada `updating` dan `deleting`. Ini corak yang sesuai untuk sejarah kemajuan.

### C.6 Jurang kod

1. `ApplicationPolicy::updateProgress()` membenarkan sebarang status bukan-boleh-sunting, termasuk `SUBMITTED` dan `IN_JTICT`. Ia tidak menyemak Lulus JTICT. **Tidak boleh digunakan sebagai pagar kelayakan tanpa pembetulan.**
2. `Project::updateStatusIndicator()` menggunakan ambang 20% (GREEN/AMBER/RED) dan memanggil `save()` dalam kaedah pengiraan. Spesifikasi SP menggunakan varians bertanda (Mendahului / Mengikut / Lewat). Lihat percanggahan I.5.
3. Permission Spatie berkaitan kemajuan wujud tetapi tidak dirujuk. Corak sistem ialah Policy + `hasRole`.

---

## Bahagian D – Integrasi Permohonan Projek dan JTICT (SP0.3)

### D.1 Cara mengenal pasti Lulus JTICT

**[FAKTA]** Satu permohonan layak jika **ketiga-tiga** syarat dipenuhi:
1. `application_decisions` mempunyai rekod `stage = 'JTICT'` dan `decision = 'APPROVED'`.
2. Rekod itu ialah **keputusan JTICT terkini**: `Application::latestJtictDecision()` (`ofMany(['id' => 'max'])` dengan penapis `stage = JTICT`).
3. Permohonan tidak dipadam (`applications.deleted_at` null) dan `status = 'CLOSED'`. Semua keputusan JTICT menghasilkan `CLOSED`, jadi status sahaja **tidak** cukup.

Contoh pertanyaan Eloquent:

```php
Application::query()
    ->where('status', ApplicationStatus::Closed->value)
    ->whereHas('latestJtictDecision', fn ($q) => $q->where('decision', ApplicationDecision::Approved->value));
```

Corak ini sudah digunakan dalam `dashboard/pengguna.blade.php` baris 14.

### D.2 Lebih daripada satu keputusan bagi satu permohonan

| Soalan | Dapatan |
|---|---|
| Boleh ada >1 keputusan JTICT bagi satu permohonan? | **[FAKTA] Tidak melalui aplikasi.** `decideJtict()` menolak jika keputusan JTICT sudah wujud. `ApplicationDecisionService::decide()` mengunci baris permohonan (`lockForUpdate`, baris 81) dan menyemak semula dalam transaksi. Pangkalan data **tiada** kekangan unik (`application_id`, `stage`). Pertanyaan semasa: 0 permohonan dengan >1 rekod JTICT. |
| Keputusan Pra JTICT berulang? | Ya, sah (Ditolak dengan Pindaan, kemudian Diterima). Ini tidak menjejaskan JTICT. |
| Keputusan terkini | `latestJtictDecision()` (ID maksimum) |
| Kaji Semula / Tidak Lulus tersalah layak? | **[FAKTA] Tidak**, jika syarat D.1(1)–(2) digunakan. Status `CLOSED` dikongsi oleh semua keputusan JTICT dan Pra JTICT `REJECTED`, jadi **jangan sekali-kali** gunakan status sahaja. Kolum warisan `applications.jtict_decision` (sentiasa `PENDING`) **tidak boleh** digunakan. |
| Salinan Kaji Semula (PP4.3C) | Salinan ialah permohonan baharu dengan `copied_from_application_id`. Jika salinan diluluskan, **salinan** yang layak, bukan permohonan asal (keputusan asal = `REVIEW`). |
| ID projek berasingan? | `projects.id` + `projects.uuid` wujud, berasingan daripada permohonan. Hubungan `Application::project()` (hasOne) dan `Project::application()` sudah ditakrif. **Tiada nombor rujukan projek.** `reference_number` permohonan boleh dipaparkan. |

### D.3 Mendapatkan Jenis Perolehan

- **[FAKTA]** Gunakan `applications.procurement_type_id` → `procurement_types.code`.
- Jangan gunakan `applications.procurement_type`, iaitu snapshot nama yang mempunyai variasi (D.4 di bawah).
- `procurement_type_id` wajib semasa Hantar (`validateStep2`), jadi permohonan Lulus JTICT sentiasa mempunyai nilai. Ia nullable hanya bagi Draf.
- FK `restrict` menghalang master dipadam selagi dirujuk.

### D.4 Cadangan integrasi

**[CADANGAN]**
1. **Tiada perubahan pada aliran keputusan JTICT.** `ApplicationDecisionService` dan `application_decisions` tidak disentuh.
2. **Senarai projek layak dijana melalui pertanyaan D.1**, dengan skop baharu seperti `Application::scopeApprovedByJtict()`.
3. **Rekod pemantauan dicipta secara eksplisit dan idempotent** apabila Pegawai Utama klik "Mulakan Pemantauan" dan mengisi tarikh jadual. Penciptaan:
   - dilakukan dalam service baharu dengan `lockForUpdate` + `firstOrCreate`;
   - dilindungi indeks unik `application_id`;
   - menyalin `agency_id` dan memilih templat daripada `procurement_type.code`.
4. **Alternatif:** listener selepas commit pada keputusan JTICT `APPROVED`. Ini memerlukan sentuhan kecil pada `ApplicationDecisionService` dan bergantung pada keputusan I.1.

---

## Bahagian E – Pemetaan Empat Templat (SP0.4)

### E.1 Jenis Perolehan dalam sistem

**[FAKTA]** Disimpan dalam pangkalan data (master), bukan hard-coded:
- Diurus oleh Urus Setia di `/urus-setia/master-data/jenis-perolehan`.
- Kod dikunci apabila digunakan (`MasterDataManagement::save()`, baris 141).
- Rekod tidak boleh dipadam jika digunakan (baris 180).

| id | `code` | `name` (master) | Snapshot nama dalam `applications.procurement_type` (bilangan) |
|---|---|---|---|
| 1 | `pembekalan_perkhidmatan_one_off` | Pembekalan / Perkhidmatan Tidak Bermasa (One-Off) | sama (3) |
| 2 | `pembekalan_perkhidmatan_bermasa` | Pembekalan / Perkhidmatan Bermasa | sama (1) |
| 3 | `penyelenggaraan` | Penyelenggaraan | sama (2) |
| 4 | `pembangunan_aplikasi` | Pembangunan Aplikasi / Sistem | **"Pembangunan Aplikasi" (1), "Pembangunan Aplikasi Berfasa / Tidak Berfasa" (2)**: variasi nama lama |

**[FAKTA] Percanggahan nama:**
- Spesifikasi menyebut "Pembangunan Sistem / Aplikasi", manakala master menyebut "Pembangunan Aplikasi / Sistem".
- Snapshot lama juga berbeza.
- **Pemetaan mesti menggunakan `code`, bukan nama.**

**Keputusan I.12 (20/11/2026):**
- Nama diselaraskan kepada **"Pembangunan Sistem / Aplikasi"** bagi Master Data dan Templat D.
- **Master data:** Urus Setia menukar nama melalui skrin Master Data → Jenis Perolehan di setiap persekitaran (pembangunan, UAT, produksi). Perubahan ini direkodkan dalam log audit.
- **Seeder:** `ApplicationMasterDataSeeder`, yang masih menggunakan "Pembangunan Aplikasi", dan ujian berkaitan dikemas kini dalam SP1.
- **Kod `pembangunan_aplikasi` tidak berubah.**
- Snapshot nama pada permohonan sedia ada kekal seperti asal.

### E.2 Pemetaan templat

| Templat | Jenis Perolehan (`code`) | Bil. aktiviti | Jumlah pemberat |
|---|---|---:|---:|
| A – Pembekalan / Perkhidmatan Tidak Bermasa | `pembekalan_perkhidmatan_one_off` | 8 | 5+10+5+10+30+20+15+5 = **100%** ✔ |
| B – Pembekalan / Perkhidmatan Bermasa | `pembekalan_perkhidmatan_bermasa` | 8 | 5+10+5+10+15+30+15+10 = **100%** ✔ |
| C – Penyelenggaraan | `penyelenggaraan` | 5 | 25+25+5+10+35 = **100%** ✔ |
| D – Pembangunan Sistem / Aplikasi | `pembangunan_aplikasi` | 10 | 5+10+5+5+5+30+20+10+5+5 = **100%** ✔ |

Nama aktiviti dan pemberat diambil **tepat** daripada spesifikasi §4.2–§4.5. Tiada perubahan dicadangkan.

**[FAKTA – pemilik, 20/11/2026]**
- Templat SPPICT v1 **sama** dengan Templat A–D. Data v1 boleh dipetakan kepada versi 1 templat yang sama (L.4).
- Hanya **Templat D** menyokong projek berfasa (L.3).

### E.3 Kaedah pemilihan templat

**[CADANGAN]**
- Jadual templat menyimpan `procurement_type_id` (FK). Templat aktif dipilih ketika rekod pemantauan dicipta dan **dikunci** pada projek (`progress_template_id`).
- Jika Urus Setia menambah Jenis Perolehan baharu tanpa templat, projek tidak boleh dimulakan. Mesej jelas dipaparkan dan isu direkodkan. Ini dikawal dalam service, bukan dengan mengubah master data.
- Pengesahan jumlah 100% dibuat di tiga lapisan:
  - ujian automatik ke atas data templat;
  - semakan dalam seeder/migration data;
  - semakan pada masa templat diaktifkan.

---

## Bahagian F – Cadangan Struktur Database Baharu

**[CADANGAN]** Prinsip:
- Semua perubahan **aditif**, tanpa mengubah migration sedia ada.
- Gunakan `uuid` untuk route dan `SoftDeletes` untuk jadual utama (AGENTS.md).
- Jadual sejarah bersifat **append-only** (corak `ApplicationDecision`).
- Peratus disimpan sebagai `decimal(5,2)`.

### F.0 Guna semula `projects` (**diputuskan I.2**)

`projects` kosong, sudah dipautkan kepada `Application::project()`, `mid_term_reviews` dan papan pemuka pemohon. Ia setara dengan cadangan `project_progress`. Keputusan: **guna semula `projects` sebagai rekod pemantauan projek**, dengan migration tambahan.

**[FAKTA]** Definisi semasa (`2026_08_04_000001`, baris 164–185):
- `application_id` dan `project_manager_id` ialah **NOT NULL**.
- `agency_id` sudah wujud.

| Tambahan / perubahan pada `projects` | Jenis | Tujuan |
|---|---|---|
| `application_id` → **nullable** + indeks **unik** | — | Satu permohonan = satu projek. Nullable bagi projek v1 yang tiada permohonan v2 (L.4). MySQL membenarkan banyak NULL dalam indeks unik. |
| `project_manager_id` → **nullable** | — | Pegawai bertanggungjawab diambil daripada `application_officers`. Projek v1 tiada pengguna v2 yang setara. |
| `progress_template_id` | FK → `progress_templates` (restrict) | Versi templat dikunci |
| `is_phased` | bool default false | Berfasa / Tidak Berfasa. Hanya dibenarkan jika templat `allows_phases` (Templat D). |
| `source` | varchar(10) default `V2` | `V2` / `V1` (Bank Projek) |
| `legacy_reference` | varchar nullable, unik | Rujukan projek dalam SPPICT v1 |
| `structure_locked_at` | timestamp nullable | Diisi apabila aktiviti pertama ditanda siap. Struktur fasa dan aktiviti dikunci (L.3). |
| `planned_start_date`, `planned_end_date` | date | Jadual keseluruhan. `start_date`/`end_date` sedia ada boleh dinamakan maksud "dirancang", dengan keputusan I.3. |
| `actual_progress_percent`, `planned_progress_percent`, `variance_percent` | decimal(5,2) / decimal(6,2) | Nilai cache terkini. Sumber sebenar ialah aktiviti. `physical_progress_percent` sedia ada boleh digunakan untuk `actual`. `planned` dan `variance` **nullable** (tiada tarikh sasaran = tiada nilai, contohnya projek sejarah v1). |
| `progress_evaluated_at` | date | Tarikh penilaian untuk nilai cache |
| `status` | varchar(30) | Kitaran projek (contoh `NOT_STARTED`, `IN_PROGRESS`, `PENDING_CLOSURE`, `COMPLETED`, `CLOSED`, **`HISTORICAL`**), berasingan daripada peratus (I.6). `HISTORICAL` = rekod sejarah v1, baca sahaja. |
| `started_by`, `closed_by`, `closed_at`, `closure_remarks` | FK users / timestamp / text | Jejak tindakan |

**Nota akses:** `ProjectPolicy` mesti menggunakan `projects.agency_id`, bukan agensi daripada permohonan, kerana projek v1 tiada permohonan.

Kolum lama:
- `status_indicator` enum(GREEN, AMBER, RED) dan `is_completed` dikekalkan untuk keserasian papan pemuka sedia ada sehingga SP5. Kemudian diterbitkan daripada `variance` dan `status`.
- `financial_progress_percent` dan `actual_cost` dikekalkan sementara menunggu keputusan I.4.

### F.1 `progress_templates`

| Kolum | Jenis | Nota |
|---|---|---|
| `id`, `uuid` | PK, char(36) unik | |
| `code` | varchar | `A`, `B`, `C`, `D` atau slug |
| `version` | unsigned int | Bermula 1 |
| `name` | varchar | |
| `procurement_type_id` | FK → procurement_types (restrict) | Pemetaan E.2 |
| `allows_phases` | bool default false | `true` bagi Templat D sahaja (L.3) |
| `is_active` | bool | |
| `effective_from` | date nullable | |
| timestamps, `deleted_at` | | |

- **Unik:** (`code`, `version`).
- **Satu versi aktif bagi setiap Jenis Perolehan:** dikawal dalam service. Alternatif MySQL: kolum dijana `active_key` yang unik.
- **Audit:** Auditable.

**Versi:**
- Templat **tidak pernah diubah** selepas digunakan oleh projek.
- Perubahan dibuat dengan mencipta versi baharu.
- Projek sedia ada kekal dengan versi asal.

### F.2 `progress_template_activities`

| Kolum | Jenis | Nota |
|---|---|---|
| `id`, `uuid` | | |
| `progress_template_id` | FK (cascade) | |
| `sequence` | unsigned small int | |
| `name` | varchar | |
| `weight` | decimal(5,2) | Jumlah per templat = 100.00 |
| `requires_document` | bool | Matriks §5.5 |
| `document_label` | varchar nullable | Contoh "Surat Setuju Terima" |

- **Unik:** (`progress_template_id`, `sequence`).
- **Kunci:** tidak boleh disunting jika templat telah digunakan.
- **Tiada `schedule_type`:** dengan keputusan I.7, semua aktiviti menggunakan satu tarikh sasaran (L.2).

### F.2A `project_phases` (baharu, L.3)

Setiap projek mempunyai sekurang-kurangnya satu fasa. Projek **Tidak Berfasa** = satu fasa sahaja. Dengan cara ini, pengiraan adalah seragam bagi kedua-dua jenis projek.

| Kolum | Jenis | Nota |
|---|---|---|
| `id`, `uuid` | | |
| `project_id` | FK → projects (restrict) | |
| `phase_number` | unsigned tinyint | Bermula 1 |
| `name` | varchar nullable | Contoh "Fasa 1". Lalai dijana. |
| `actual_progress_percent`, `planned_progress_percent` | decimal(5,2), nullable bagi `planned` | Nilai cache fasa |
| timestamps, `deleted_at` | | |

- **Unik:** (`project_id`, `phase_number`).
- **Projek Tidak Berfasa:** satu baris dengan `phase_number = 1`.
- **Projek Berfasa:** bilangan fasa ditentukan oleh Pegawai Utama atau Pegawai Kedua. Had atas dicadangkan 10, boleh diubah melalui konfigurasi.

### F.3 `project_progress_activities` (aktiviti projek, snapshot)

| Kolum | Jenis | Nota |
|---|---|---|
| `id`, `uuid` | | |
| `project_id` | FK → projects (restrict) | Untuk pertanyaan pantas dan Policy |
| `project_phase_id` | FK → project_phases (restrict) | Fasa aktiviti ini |
| `progress_template_activity_id` | FK (restrict) | |
| `sequence`, `name`, `weight`, `requires_document` | snapshot | Pemberat projek tidak berubah walaupun templat berubah |
| `target_date` | date nullable | **Satu tarikh sasaran sahaja** (I.7). Wajib bagi aktiviti yang belum siap dalam projek v2. Kosong bagi rekod sejarah v1. |
| `is_completed` | bool default false | **Tanda siap** (L.1). Tiada peratus separa. |
| `actual_completion_date` | date nullable | Wajib apabila `is_completed = true`. Tidak boleh pada masa hadapan. Data v1 masuk ke kolum ini (L.4). |
| `completed_by`, `completed_at` | FK users nullable / timestamp | Siapa dan bila aktiviti ditanda siap |
| timestamps, `deleted_at` | | |

- **Unik:** (`project_phase_id`, `progress_template_activity_id`). Aktiviti yang sama boleh wujud dalam fasa berlainan, tetapi hanya sekali dalam satu fasa.
- **Indeks:** `project_id`, `project_phase_id`.
- **Pengesahan:** setiap fasa mesti mempunyai sekurang-kurangnya satu aktiviti.
- **Status aktiviti diterbitkan, bukan disimpan:**
  - Selesai = `is_completed`;
  - Lewat = belum siap dan `target_date` < tarikh penilaian;
  - Belum Siap = selainnya.

### F.4 `project_progress_reports` (sedia ada, tambah)

- Indeks **unik** (`project_id`, `report_year`, `report_month`). Selamat ditambah kerana jadual kosong.
- Tambah:
  - `status` (`DRAFT`/`SUBMITTED`) dan `submitted_at`;
  - `actual_progress_percent`, `planned_progress_percent`, `variance_percent` (snapshot pada tarikh penilaian);
  - `evaluation_date`, `follow_up_actions`.
- Kolum `physical_*` sedia ada boleh dipetakan kepada `actual`/`planned` (I.2).
- Laporan yang dihantar **dikunci**. Pembetulan dibuat dengan rekod pembetulan, bukan menimpa (F.5).
- `verified_*` dikekalkan tidak digunakan sehingga keputusan I.5.

### F.5 `project_progress_report_activities` (tambahan, snapshot laporan)

Spesifikasi §5.4A memerlukan pencapaian setiap aktiviti dalam laporan bulanan.

| Kolum | Nota |
|---|---|
| `project_progress_report_id` (cascade), `project_progress_activity_id` | |
| `is_completed`, `actual_completion_date`, `target_date`, `is_planned_due` | Snapshot pada tarikh penilaian (`is_planned_due` = tarikh sasaran ≤ tarikh penilaian) |

**Unik:** (`report_id`, `activity_id`).

### F.6 `project_progress_updates` (sejarah, append-only)

| Kolum | Nota |
|---|---|
| `id`, `uuid` | |
| `project_progress_activity_id` | FK (restrict) |
| `project_progress_report_id` | nullable |
| `change_type` | varchar(20): `COMPLETED` (tanda siap), `UNCOMPLETED` (batal tanda), `COMPLETION_DATE`, `TARGET_DATE` |
| `old_value`, `new_value` | varchar nullable (tarikh/bool dalam bentuk teks) |
| `remarks` | text |
| `updated_by` | FK users |
| `corrects_update_id` | FK nullable, untuk pembetulan |
| `created_at` | |

- **Tiada `updated_at` dan tiada soft delete.**
- Model membaling `LogicException` pada `updating` dan `deleting`, mengikut corak `ApplicationDecision`.
- **Indeks:** (`project_progress_activity_id`, `created_at`).

### F.7 `project_progress_attachments`

| Kolum | Nota |
|---|---|
| `id`, `uuid` | |
| `project_id` | FK |
| `project_progress_activity_id` | nullable |
| `project_progress_report_id` | nullable |
| `project_progress_update_id` | nullable |
| `document_label` | |
| `file_name`, `file_path`, `mime_type`, `file_size_kb` | |
| `uploaded_by` | |
| timestamps, `deleted_at` | |

- Sekurang-kurangnya satu daripada `activity`, `report` atau `update` mesti diisi (semakan service).
- **Storan:** disk `local`, laluan `projects/{uuid}/progress`.
- **Akses:** controller baharu dengan Policy projek, mengikut corak `ApplicationDocumentController`.
- **Format:** PDF sahaja (AGENTS.md) atau PDF/DOCX (I.8).
- **Indeks:** `project_id`, `project_progress_activity_id`, `project_progress_report_id`.
- **Tidak menyentuh** `application_documents` dan polisinya.

### F.8 Strategi migration

1. Migration baharu sahaja, bertarikh selepas `2026_11_19_000001`. Tiada pengubahsuaian migration lama.
2. Indeks unik pada `projects.application_id` dan laporan (projek, tahun, bulan) boleh ditambah dengan selamat kerana kedua-dua jadual kosong. Tambah semakan pra-syarat (`count()` pendua = 0) dalam migration sebagai langkah berjaga-jaga di UAT dan produksi.
3. Data templat (4 templat, 31 aktiviti, `allows_phases` = true bagi Templat D sahaja) dimasukkan melalui migration data atau seeder idempoten, dengan ujian jumlah = 100.
   - Perubahan `application_id` dan `project_manager_id` kepada nullable pada `projects` selamat dibuat kerana jadual kosong. Tambah semakan pra-syarat yang sama seperti langkah 2.
   - Pemindahan data v1 (Bank Projek) **tidak termasuk dalam SP1–SP5**. Ia memerlukan fasa berasingan (L.4).
4. Semua `down()` membuang tambahan sahaja.
5. Uji dahulu pada salinan pangkalan data. **UAT (`sppictv2_uat`) perlu migration yang sama** apabila kod dibawa ke sana.

---

## Bahagian G – Cadangan Integrasi UI/UX

**[CADANGAN]** Ikut tema peranan sedia ada (`App\Support\RoleTheme`: Pengguna biasa = biru langit, Urus Setia = hijau, Superadmin = indigo) dan komponen Tailwind sedia ada.

| Skrin | Pengguna | Kandungan |
|---|---|---|
| **Senarai Projek** (`/projek`) | Pemohon: projek agensi sendiri. Urus Setia/Pengurusan: semua (penapis agensi, templat, status, varians) | No. rujukan, tajuk, agensi, Jenis Perolehan/templat, kemajuan sebenar vs dirancang, varians (Mendahului/Mengikut/Lewat), laporan bulan terkini, status |
| **Maklumat Projek** (`/projek/{uuid}`) | Semua yang dibenarkan | Ringkasan permohonan (pautan ke `application.view`), keputusan JTICT, pegawai, jadual keseluruhan, kad kemajuan dan varians, carta ringkas |
| **Mulakan Pemantauan** | Pegawai Utama atau Pegawai Kedua aktif | Pengesahan templat (baca sahaja). Templat D: pilihan **Berfasa / Tidak Berfasa**. Jika Berfasa: bilangan fasa dan pilihan aktiviti bagi setiap fasa. Isi tarikh mula dan tamat projek, serta **satu tarikh sasaran** bagi setiap aktiviti. Struktur boleh dipinda sehingga aktiviti pertama ditanda siap. |
| **Kemajuan Aktiviti** | Pegawai aktif: sunting. Lain-lain: baca | Jadual aktiviti mengikut fasa: pemberat (baca sahaja), tarikh sasaran, **kotak tanda Siap + tarikh sebenar siap**, status (Selesai / Lewat / Belum Siap), dokumen wajib (lencana), sejarah perubahan (lipat). Kemajuan setiap fasa dan kemajuan keseluruhan projek. |
| **Laporan Kemajuan Bulanan** | Pegawai aktif: cipta/hantar. Lain-lain: baca | Bulan/tahun (satu sahaja bagi setiap bulan), snapshot automatik, isu dan kekangan, tindakan susulan, dokumen, butang Hantar (kunci) |
| **Dashboard** | Pemohon (sedia ada, disambung), Urus Setia, Pengurusan | Bilangan projek mengikut status dan varians, senarai projek lewat, laporan bulanan belum dihantar berbanding `meetings.progress_update_deadline` |

**Titik masuk:**
- Pautan "Pemantauan Projek" pada `view-application` apabila keputusan JTICT = Lulus.
- Menu navigasi baharu.
- Kad sedia ada di `dashboard/pengguna.blade.php`, dikemas kini kepada data baharu dalam SP5.

---

## Bahagian H – Risiko dan Mitigasi

| # | Risiko | Kategori | Tahap | Mitigasi |
|---|---|---|---|---|
| 1 | Perubahan pada jadual `applications` merosakkan aliran permohonan/JTICT | Database | **Sederhana** | Tiada kolum baharu pada `applications`. Semua tambahan dalam jadual projek. Suite ujian penuh (593 ujian) dijalankan setiap fasa. |
| 2 | Hubungan `applications`↔`projects` tidak unik | Database | **Tinggi** | Indeks unik `projects.application_id`, `firstOrCreate` dalam transaksi dengan `lockForUpdate` |
| 3 | Keputusan JTICT berganda atau sejarah menyebabkan kelayakan salah | Integrasi | **Sederhana** | Gunakan `latestJtictDecision` + `APPROVED` + tidak dipadam. Jangan guna `status` atau kolum warisan `jtict_decision`. Ujian untuk Kaji Semula, Tidak Lulus dan salinan. Pertimbangkan semakan integriti (tiada >1 JTICT setiap permohonan) dalam ujian. |
| 4 | Data projek berganda (projek atau laporan bulanan) | Database | **Tinggi** | Indeks unik (`application_id`) dan (`project_id`, `report_year`, `report_month`), serta pengesahan service |
| 5 | Pengguna mengakses projek agensi lain melalui UUID | Akses | **Kritikal** | `ProjectPolicy` baharu (semakan agensi + pegawai aktif) pada setiap tindakan Livewire dan route. Ujian merentas agensi (AGENTS.md §4.4). |
| 6 | Perubahan pemberat templat menjejaskan projek sedia ada | Pengiraan | **Tinggi** | Versi templat + snapshot pemberat dalam `project_progress_activities`. Templat yang digunakan dikunci. Pemberat bukan sifat Livewire awam. |
| 7 | Sejarah kemajuan dipadam atau ditimpa | Database | **Tinggi** | `project_progress_updates` append-only (guard model), laporan dihantar dikunci, pembetulan sebagai rekod baharu, audit trail |
| 8 | Dokumen tidak dapat diakses atau tersalah paut | Dokumen | **Sederhana** | Storan private, controller dengan Policy, FK eksplisit kepada aktiviti/laporan, semakan tandatangan fail, soft delete sahaja |
| 9 | Pengiraan sebenar dan dirancang tidak konsisten antara skrin | Pengiraan | **Tinggi** | Satu service pengiraan tulen (tiada `save()` di dalamnya, berbeza dengan `Project::updateStatusIndicator()`), aritmetik dalam ratus/sen, ujian unit bagi contoh L.2 dan L.3 serta sempadan tarikh |
| 10 | Status Selesai tidak selaras dengan kemajuan 100% (contoh Penyelenggaraan 100% semasa kontrak bermula) | Pengiraan | **Tinggi** | Status kitaran berasingan daripada peratus, penutupan memerlukan kriteria eksplisit (I.6). Paparan "100% (templat) – Dalam Pelaksanaan Kontrak". |
| 11 | Projek ≥ RM500k dipantau sebelum kelulusan JPICT | Integrasi | **Tinggi** | Keputusan I.1. Modul JPICT belum wujud. |
| 12 | Superadmin dan Pengurusan tiada akses kepada permohonan/projek (`not-role:superadmin`, `view` policy) | Akses | **Sederhana** | `ProjectPolicy::view` khusus mengikut keputusan I.9, tanpa mengubah `ApplicationPolicy` |
| 13 | Jenis Perolehan baharu ditambah tanpa templat | Integrasi | **Sederhana** | Service menyekat permulaan dengan mesej jelas, dan amaran di skrin master data (SP5 atau kemudian) |
| 14 | Tiada data Lulus JTICT dalam pembangunan untuk ujian manual | Integrasi | **Sederhana** | Data ujian melalui ujian automatik (RefreshDatabase). UAT merekod keputusan JTICT sebenar melalui aliran PP4.3A. Tiada penulisan terus ke pangkalan data pembangunan. |
| 15 | Kod lama (`Project::updateStatusIndicator`, papan pemuka) bercanggah dengan logik baharu | Pengiraan | **Rendah** | Ganti secara terkawal dalam SP4/SP5 dengan ujian paparan |
| 16 | Struktur fasa dipinda selepas kemajuan direkodkan, menyebabkan peratus berubah secara mendadak | Pengiraan | **Tinggi** | Struktur dikunci apabila aktiviti pertama ditanda siap (`structure_locked_at`). Hanya Urus Setia boleh membuka semula, dengan catatan wajib dan audit trail. |
| 17 | Projek bukan Templat D ditetapkan sebagai berfasa | Integrasi | **Sederhana** | Semakan `progress_templates.allows_phases` dalam service dan ujian. Pilihan Berfasa tidak dipaparkan bagi templat lain. |
| 18 | Data v1 tersalah tafsir: tarikh siap sebenar dianggap tarikh sasaran | Data | **Tinggi** | Tarikh v1 hanya dimasukkan ke `actual_completion_date`. Projek sejarah tiada `planned`/`variance` (dipapar "Tiada data rancangan"). |
| 19 | Projek v1 (tanpa permohonan v2) terlepas daripada kawalan akses agensi | Akses | **Tinggi** | `ProjectPolicy` menggunakan `projects.agency_id`. Ujian akses bagi projek tanpa `application_id`. |
| 20 | Tanda siap dibatalkan tanpa jejak | Database | **Sederhana** | Setiap tanda/batal tanda direkodkan dalam `project_progress_updates` (append-only) dengan catatan wajib bagi pembatalan |

---

## Bahagian I – Senarai Keputusan / Pengesahan Diperlukan

Keputusan yang telah dimuktamadkan dalam spesifikasi SP0 tidak dibangkitkan semula:
- templat dan pemberat;
- formula kemajuan sebenar;
- pengemaskini ialah Pemohon;
- Urus Setia dan Pengurusan tidak mengubah pencapaian;
- hanya Lulus JTICT yang layak.

| # | Perkara | Cadangan |
|---|---|---|
| I.1 | **Projek ≥ RM500,000.** PRD §3.1 dan AGENTS.md §3 mensyaratkan kelulusan muktamad JPICT, tetapi modul JPICT belum wujud. Adakah projek ini layak dipantau selepas Lulus JTICT, atau tunggu JPICT? | Layak selepas Lulus JTICT (ikut spesifikasi SP0), dengan label "Menunggu JPICT" sehingga modul JPICT wujud |
| I.2 | **Guna semula `projects` dan `project_progress_reports`** (tambah kolum) atau cipta jadual baharu `project_progress` seperti nama cadangan? | ✅ **Diputuskan 20/11/2026: guna semula** (F.0) |
| I.3 | **Sumber tarikh jadual:** tarikh mula dan tamat projek serta tarikh setiap aktiviti diisi oleh Pemohon semasa "Mulakan Pemantauan"? Boleh dipinda selepas itu? Siapa meluluskan pindaan jadual? | Pemohon isi. Pindaan direkodkan dalam sejarah (tiada kelulusan) |
| I.4 | **Kemajuan kewangan:** PRD §7.5 dan kolum sedia ada (`financial_*`) menyebutnya, tetapi spesifikasi SP tidak. Diperlukan? | Tangguh. Kekalkan kolum, tiada UI dalam SP1–SP4. |
| I.5 | **Indikator dan pengesahan:** PRD menyebut lampu Hijau/Kuning/Merah (lewat 1–19% / ≥ 20%) dan "Urus Setia mengesahkan kemajuan". Spesifikasi SP menyebut varians Mendahului/Mengikut/Lewat dan Urus Setia **melihat** sahaja. Mana yang terpakai? Adakah lampu RAG masih dipaparkan berdasarkan varians? | Varians SP sebagai status utama. RAG diterbitkan daripada varians (0 / −0.01 hingga −19.99 / ≤ −20) untuk keserasian papan pemuka. Tiada pengesahan Urus Setia. |
| I.6 | **Kriteria penutupan (Selesai):** contohnya 100% + Akuan Siap Kerja dimuat naik + tarikh siap sebenar. Siapa menutup? Bagi Penyelenggaraan (100% apabila Dokumen Kontrak selesai), bila status Selesai? | Pemohon mohon tutup, Urus Setia sahkan penutupan. Penyelenggaraan selesai pada tarikh tamat kontrak. |
| I.7 | **Jenis jadual aktiviti:** aktiviti mana DURATION dan mana MILESTONE? | ✅ **Diputuskan 20/11/2026:** satu tarikh sasaran bagi setiap aktiviti, seperti v1. Tiada pengelasan DURATION/MILESTONE. Dirancang = pemberat aktiviti yang tarikh sasarannya ≤ tarikh penilaian (L.2). |
| I.8 | **Format dokumen sokongan kemajuan:** PDF sahaja (AGENTS.md) atau PDF/DOCX/imej? Saiz maksimum? Dokumen wajib menyekat status Selesai aktiviti atau hanya diperlukan pada 100%? | PDF sahaja, 25 MB. Wajib apabila aktiviti 100%. |
| I.9 | **Akses Superadmin dan Pengurusan:** kedua-duanya kini tidak boleh membuka permohonan. Bolehkah mereka melihat Senarai dan Maklumat Projek (baca sahaja)? | Ya, baca sahaja |
| I.10 | **Laporan bulanan:** tarikh akhir setiap bulan (contoh hari ke-10 bulan berikutnya) atau ikut `meetings.progress_update_deadline` sedia ada? Laporan bulan terdahulu yang tertinggal boleh dihantar lewat? | Ikut `progress_update_deadline`. Laporan lewat dibenarkan dengan tanda "Lewat". |
| I.11 | **Pegawai yang boleh mengemas kini:** Pegawai Utama sahaja, atau Utama dan Kedua? Pendaftar asal? | Pegawai Utama dan Kedua aktif seagensi (selaras `updateProgress` sedia ada). Pemilik telah menyatakan bahawa pilihan Berfasa dibuat oleh Pegawai Utama atau Pegawai Kedua (L.3). |
| I.12 | **Nama paparan Jenis Perolehan 4:** "Pembangunan Aplikasi / Sistem" (master) berbanding "Pembangunan Sistem / Aplikasi" (spesifikasi). Nama templat D? | ✅ **Diputuskan 20/11/2026:** "Pembangunan Sistem / Aplikasi" bagi Templat D **dan** Master Data (E.1) |
| I.13 | **Bank Projek (v1):** cara data v1 diperoleh (eksport pangkalan data v1 / fail), padanan agensi, pegawai bertanggungjawab bagi projek v1 yang masih berjalan, dan kriteria projek "berjalan" berbanding "sejarah" | Diputuskan sebelum fasa Bank Projek. Tidak menyekat SP1–SP5. |

---

## Bahagian J – Pelan Pembangunan

Branch dicadangkan: `feature/sp-status-kemajuan`, dibuka daripada `feature/pp4-sst-checkbox`, atau `main` selepas PP4.3–PP4.5 digabungkan.

### SP1 – Struktur Database dan Templat Pemberat

| Perkara | Butiran |
|---|---|
| Skop | Migration F.1, F.2 dan tambahan `projects` (F.0: unik dan nullable `application_id`, nullable `project_manager_id`, `is_phased`, `source`, `legacy_reference`, `structure_locked_at`). Data 4 templat / 31 aktiviti (`allows_phases` Templat D). Model `ProgressTemplate`, `ProgressTemplateActivity`. Skop `Application::scopeApprovedByJtict()`. Seeder `ApplicationMasterDataSeeder`: nama "Pembangunan Sistem / Aplikasi" (I.12) dan ujian berkaitan. |
| Kebergantungan | Keputusan I.2, I.7, I.12: ✅ semua telah dibuat (20/11/2026) |
| Risiko | H.2, H.6, H.17 |
| Ujian | Jumlah pemberat setiap templat = 100. Pemetaan 4 kod Jenis Perolehan. Hanya Templat D `allows_phases`. Kelayakan: Lulus layak; Kaji Semula, Tidak Lulus, Pra JTICT Ditolak dan dipadam tidak layak. Salinan diluluskan layak. Migration `up`/`down` pada pangkalan data ujian. |
| Kriteria penerimaan | Templat dimuat, tiada perubahan pada `applications` dan aliran JTICT, suite penuh lulus |

### SP2 – Aktiviti dan Jadual Projek

| Perkara | Butiran |
|---|---|
| Skop | Migration F.2A dan F.3. Service "Mulakan Pemantauan" (idempotent, lock). Pilihan Berfasa / Tidak Berfasa (Templat D), bilangan fasa dan aktiviti setiap fasa. Kunci struktur dan buka semula oleh Urus Setia. `ProjectPolicy` (berasaskan `projects.agency_id`). Skrin Senarai Projek dan Maklumat Projek. Borang satu tarikh sasaran setiap aktiviti. Pautan dari `view-application`. |
| Kebergantungan | SP1. Keputusan I.1, I.3, I.9, I.11. |
| Risiko | H.4, H.5, H.12, H.16, H.17, H.19 |
| Ujian | Projek tidak berganda (dua klik serentak). Snapshot pemberat. Tidak Berfasa = satu fasa. Berfasa ditolak bagi templat selain D. Setiap fasa sekurang-kurangnya satu aktiviti. Aktiviti tidak berulang dalam satu fasa. Struktur terkunci selepas tanda siap pertama. Akses merentas agensi ditolak (UUID). Superadmin/Pengurusan ikut I.9. Jenis Perolehan tanpa templat disekat. |
| Kriteria penerimaan | Pegawai aktif boleh memulakan pemantauan, menetapkan fasa dan jadual. Pengguna agensi lain mendapat 403. |

### SP3 – Laporan Bulanan dan Kemas Kini Aktiviti

| Perkara | Butiran |
|---|---|
| Skop | Migration F.4–F.7. **Tanda siap aktiviti + tarikh sebenar siap** dengan catatan, serta batal tanda dengan catatan wajib. Muat naik dokumen (wajib mengikut matriks §5.5). Laporan bulanan (satu sahaja setiap bulan, snapshot, Hantar dan kunci). Controller dokumen projek. |
| Kebergantungan | SP2. Keputusan I.8, I.10. |
| Risiko | H.7, H.8, H.20 |
| Ujian | Tanda siap memerlukan tarikh sebenar siap (bukan masa hadapan). Batal tanda direkodkan. Dokumen wajib sebelum tanda siap. Laporan bulan sama ditolak. Laporan dihantar tidak boleh diubah. Akses dokumen merentas agensi ditolak. Fail bukan PDF ditolak. |
| Kriteria penerimaan | Kemas kini aktiviti tidak menggantikan laporan bulanan. Setiap perubahan ada rekod sejarah. |

### SP4 – Pengiraan, Validasi dan Sejarah

| Perkara | Butiran |
|---|---|
| Skop | Service pengiraan tulen mengikut L.2 dan L.3: kemajuan fasa (sebenar dan dirancang, berkadar dengan pemberat aktiviti dipilih); kemajuan projek = purata sama rata fasa; varians dan status. Nilai cache pada `project_phases` dan `projects`. Paparan sejarah dan pembetulan. Kriteria penutupan. |
| Kebergantungan | SP3. Keputusan I.5, I.6. (I.7 ✅) |
| Risiko | H.9, H.10, H.15, H.18 |
| Ujian | Contoh L.2 dan L.3. Tarikh penilaian sebelum, pada dan selepas tarikh sasaran. Fasa dengan jumlah pemberat bukan 100 (pengiraan berkadar). Purata dua fasa. Tidak Berfasa = nilai fasa tunggal. Tiada tarikh sasaran → dirancang/varians kosong. Pembundaran. Penutupan tanpa kriteria ditolak. Penyelenggaraan 100% ≠ Selesai automatik. |
| Kriteria penerimaan | Nilai sama di semua skrin dan laporan. Masa berlalu tidak menambah kemajuan sebenar. |

### SP5 – Dashboard dan Laporan

| Perkara | Butiran |
|---|---|
| Skop | Kemas kini papan pemuka pemohon (sambung ke data baharu). Papan pemuka Urus Setia dan Pengurusan (status, varians, laporan tertunggak). Eksport (`maatwebsite/excel` / `dompdf`) jika diluluskan. Persaraan `Project::updateStatusIndicator()`. |
| Kebergantungan | SP4. Keputusan I.5, I.9, I.10. |
| Risiko | H.12, H.15 |
| Ujian | Bilangan dan penapis mengikut peranan. Tiada data agensi lain bagi pemohon. Paparan kosong. Prestasi pertanyaan (eager loading). |
| Kriteria penerimaan | Papan pemuka mencerminkan nilai pengiraan SP4. Akses ikut peranan. |

---

## Bahagian K – Rumusan dan Keputusan SP0

### Keputusan: **2. SEDIA DENGAN SYARAT**

**Bukti kesediaan:**
- Kelayakan Lulus JTICT boleh ditentukan dengan tepat tanpa mengubah aliran keputusan (D.1–D.2). Sejarah keputusan dilindungi (append-only, lock, satu keputusan JTICT setiap permohonan).
- Jenis Perolehan disimpan sebagai master dengan kod stabil dan dipetakan satu-ke-satu kepada empat templat. Semua jumlah pemberat = 100% (E.2).
- Jadual pemantauan sedia ada kosong, jadi tambahan aditif (termasuk indeks unik) boleh dibuat tanpa risiko data (F.8).
- Corak sedia ada boleh digunakan semula: Policy, storan private, guard append-only, Auditable.

**Syarat sebelum SP1 dimulakan:**
1. ✅ Keputusan **I.2**, **I.7** dan **I.12** telah dibuat pada 20/11/2026 (Bahagian L). **Tiada lagi keputusan yang menyekat SP1.** SP1 hanya bermula selepas kelulusan eksplisit pemilik.
2. Keputusan **I.1** (JPICT ≥ RM500k) sebelum SP2.
3. Keputusan **I.3, I.5, I.6, I.9** sebelum SP2–SP4, mengikut kebergantungan dalam Bahagian J.
4. Pengujian manual bergantung pada keputusan JTICT sebenar di UAT, kerana pembangunan tiada data Lulus JTICT. Ujian automatik akan mencipta data sendiri.
5. Keputusan **I.13** (Bank Projek) sebelum fasa Bank Projek dimulakan. Fasa ini berasingan daripada SP1–SP5.

---

## Bahagian L – Keputusan Pemilik dan Keperluan Tambahan (20/11/2026)

### L.1 Kaedah kemas kini aktiviti: tanda siap

| Perkara | Keputusan |
|---|---|
| Cara kemas kini | Pegawai **menanda aktiviti yang telah siap** dan mengisi **tarikh sebenar siap**, seperti SPPICT v1 |
| Peratus separa | **Tiada.** Setiap aktiviti 0% (belum siap) atau 100% (siap). |
| Percanggahan | Keputusan ini **mengatasi spesifikasi SP §5.1**, yang membenarkan pencapaian 0–100%, dan contoh "Penghantaran 30% × 50% = 15%". |
| Batal tanda | Dibenarkan dengan catatan wajib, direkodkan dalam sejarah (F.6) |

### L.2 Jadual dan pengiraan (I.7)

Setiap aktiviti mempunyai **satu tarikh sasaran**. Kemajuan dirancang mengikut cara yang sama dengan kemajuan sebenar (langkah 0 → 100%), supaya status adil.

| Ukuran | Formula (bagi satu fasa) |
|---|---|
| **Sebenar** | Σ pemberat aktiviti **ditanda siap** ÷ Σ pemberat aktiviti dalam fasa × 100 |
| **Dirancang** | Σ pemberat aktiviti dengan **tarikh sasaran ≤ tarikh penilaian** ÷ Σ pemberat aktiviti dalam fasa × 100 |
| **Varians** | Sebenar − Dirancang. Positif = Mendahului, 0 = Mengikut Jadual, negatif = Lewat. |
| **Aktiviti lewat** | Belum ditanda siap dan tarikh sasaran telah berlalu |

Bagi projek Tidak Berfasa, fasa tunggal mengandungi semua aktiviti templat (jumlah 100), jadi formula ini sama dengan Σ pemberat.

**Contoh (Templat A, tarikh penilaian 15 Mac):**
- Tarikh sasaran yang telah berlalu: Tender (5), Perolehan (10), SST (5) dan Kickoff (10). **Dirancang = 30%.**
- Ditanda siap: Tender, Perolehan dan SST. **Sebenar = 20%.**
- **Varians = −10%, status Lewat.** Mesyuarat Kickoff ditandakan sebagai aktiviti lewat.

### L.3 Projek berfasa (Model 2)

| Perkara | Keputusan |
|---|---|
| Templat | **Pembangunan Sistem / Aplikasi (Templat D) sahaja.** Templat lain sentiasa Tidak Berfasa. |
| Siapa memilih | **Pegawai Utama atau Pegawai Kedua** aktif, semasa Mulakan Pemantauan |
| Bilangan fasa | Ditentukan oleh pegawai (had atas dicadangkan 10) |
| Aktiviti setiap fasa | Pegawai memilih aktiviti daripada Templat D bagi setiap fasa. Aktiviti yang sama boleh dipilih dalam lebih daripada satu fasa. |
| Model pengiraan | **Model 2: setiap fasa berjumlah 100%** |
| Pemberat dalam fasa | Jika jumlah pemberat aktiviti yang dipilih bukan 100, sistem **mengira secara berkadar** (L.2) |
| Kemajuan projek | **Purata sama rata** kemajuan semua fasa (sebenar dan dirancang) |
| Pindaan struktur | Bilangan fasa dan aktiviti setiap fasa **boleh dipinda sehingga aktiviti pertama ditanda siap**. Selepas itu struktur dikunci, dan hanya **Urus Setia** boleh membuka semula, dengan catatan dan audit trail. |
| SPPICT v1 | Tiada fasa direkodkan dalam v1. Ini penambahbaikan v2. |

**Contoh (Templat D, 2 fasa):**
- **Fasa 1:** semua 10 aktiviti (jumlah 100). Ditanda siap: Tender, Perolehan, SST, Kickoff, Kontrak dan Pembangunan (5+10+5+5+5+30 = 60). **Sebenar = 60%.**
- **Fasa 2:** Pembangunan (30), UAT/PAT (20) dan FAT (10), jumlah 60. Pembangunan ditanda siap. **Sebenar = 30 ÷ 60 = 50%.**
- **Projek:** (60 + 50) ÷ 2 = **55%.**

### L.4 Data SPPICT v1 dan Bank Projek

| Perkara | Maklumat pemilik |
|---|---|
| Tarikh dalam v1 | **Tarikh sebenar aktiviti siap**, bukan tarikh sasaran |
| Templat v1 | **Sama** dengan Templat A–D |
| Status projek v1 | Ada yang **masih berjalan** (diteruskan dalam v2) dan ada yang **rekod sejarah sahaja** |
| Fasa dalam v1 | Tiada |

**Kesan reka bentuk (sudah dimasukkan dalam F.0 dan F.3):**
- **Hubungan dengan permohonan:**
  - `projects.application_id` nullable, kerana projek v1 tiada permohonan v2.
  - Akses berasaskan `projects.agency_id`.
  - Projek v1 dikenal pasti melalui `source = V1` dan `legacy_reference`.
- **Tarikh v1:** dimasukkan ke `actual_completion_date`, dengan `is_completed = true`.
- **Rekod sejarah:**
  - `status = HISTORICAL`, baca sahaja, satu fasa.
  - Tiada kemajuan dirancang atau varians; dipaparkan "Tiada data rancangan".
- **Projek v1 masih berjalan:**
  - Pegawai mengisi tarikh sasaran bagi aktiviti yang belum siap.
  - Varians dikira selepas tarikh sasaran diisi.
- **Skop:** pemindahan data dan skrin Bank Projek ialah **fasa berasingan** selepas SP1–SP5, tertakluk kepada keputusan I.13. Contoh data v1 (eksport jadual atau tangkapan skrin) diperlukan untuk mengesahkan padanan nama aktiviti.

### L.5 Ringkasan status keputusan

| # | Status |
|---|---|
| I.2, I.7, I.12 | ✅ Diputuskan (20/11/2026) |
| Tanda siap (L.1), projek berfasa (L.3), data v1 (L.4) | ✅ Diputuskan (20/11/2026) |
| I.1 | Terbuka. Diperlukan sebelum SP2. |
| I.3, I.4, I.5, I.6, I.8, I.9, I.10, I.11 | Terbuka. Diperlukan mengikut kebergantungan dalam Bahagian J. |
| I.13 | Terbuka. Diperlukan sebelum fasa Bank Projek. |

---

## Lampiran – Pengesahan Tiada Perubahan

| Perkara | Status |
|---|---|
| Kod aplikasi diubah | **Tidak** |
| Pangkalan data diubah | **Tidak** (pertanyaan `SELECT` dan `db:table` sahaja) |
| Migration dibuat / dijalankan | **Tidak** |
| Seeder dijalankan | **Tidak** |
| Rekod data disentuh | **Tidak** |
| Ujian automatik dijalankan | **Tidak** |
| Branch ditukar / commit | **Tidak** |
| Fail dicipta | Laporan ini sahaja (`docs/audit/` baharu). Skrip pertanyaan sementara berada dalam folder scratchpad sesi, di luar projek. |
| Kemas kini 20/11/2026 | Laporan ini sahaja dikemas kini. Tiada perubahan kod, pangkalan data atau master data. |
