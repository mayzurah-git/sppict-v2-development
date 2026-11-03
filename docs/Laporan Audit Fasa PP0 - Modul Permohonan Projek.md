# Laporan Audit Fasa PP0 — Modul Permohonan Projek

**Sistem:** SPPICT v2.1
**Tarikh audit:** 27 Oktober 2026
**Jenis fasa:** Audit sahaja (tiada perubahan kod, migration, data atau operasi Git)

Petunjuk dalam laporan ini:

- **[FAKTA]** — disahkan melalui pembacaan kod, skema pangkalan data sebenar atau ujian.
- **[CADANGAN]** — reka bentuk atau tindakan yang dicadangkan untuk fasa seterusnya.
- **[BELUM PASTI]** — tidak dapat disahkan daripada kod, perlu keputusan pemilik sistem.

---

## 1. Ringkasan Eksekutif

Modul Permohonan Projek telah melaksanakan **borang lima fasa, simpan draf, penghantaran dengan syarat Mesyuarat JTICT aktif, pengiraan SST di pelayan, muat naik dokumen ke storan private, dan pengurusan Pegawai Bertanggungjawab** dengan kawalan yang baik. Ujian sedia ada lulus sepenuhnya (385/385).

Walau bagaimanapun, **separuh kedua aliran kerja belum dibina**. Perkara berikut **tiada langsung** dalam kod:

| Fungsi | Status |
|---|---|
| Pilihan jabatan/agensi sasaran oleh Urus Setia | **Belum dilaksanakan** — agensi sentiasa diambil daripada akaun pengguna |
| Templat & import CSV perincian projek | **Belum dilaksanakan** |
| Checkbox SST | **Belum dilaksanakan** — SST 8% sentiasa dikenakan |
| Semakan Urus Setia (Lengkap / Tidak Lengkap) | **Belum dilaksanakan** — tiada komponen, tindakan atau polisi |
| Keputusan Pra JTICT (Diterima / Ditolak) | **Belum dilaksanakan** — tiada medan pangkalan data |
| Keputusan Mesyuarat JTICT (Lulus / Tidak Lulus / Kaji Semula) | **Belum dilaksanakan** — hanya kolum enum warisan yang tidak digunakan |
| Salin permohonan Kaji Semula | **Belum dilaksanakan** |
| E-mel pemakluman kepada pemohon (Tidak Lengkap / keputusan JTICT) | **Belum dilaksanakan** |

Terdapat juga **ketidakselarasan status**: paparan senarai dan papan pemuka Urus Setia menggunakan status `APPROVED`, `REJECTED`, `IN_REVIEW` yang **tidak wujud** dalam enum `applications.status` sebenar.

Fasa yang paling sesuai untuk dimulakan selepas audit ialah **PP1 — Penetapan model status & aliran kerja (keputusan + migration tambahan)**, kerana semua fungsi semakan, keputusan, e-mel dan salin bergantung padanya. Lihat Seksyen 20.

---

## 2. Maklumat Persekitaran dan Branch

| Perkara | Nilai |
|---|---|
| Branch | `feature/ui-design` |
| Commit terkini | `840d4da` Reka bentuk semula UI log masuk dan papan pemuka Urus Setia |
| Perubahan belum di-commit | 7 fail diubah suai + 2 fail baharu (UM12 / UI) — **dikekalkan tanpa sentuhan** |
| Laravel | 13.23.0 |
| PHP | 8.4.27 (`upload_max_filesize=30M`, `post_max_size=35M`, `memory_limit=128M`) |
| Livewire | v3.8.3 (Volt untuk halaman auth) |
| Pakej berkaitan | `maatwebsite/excel` 3.1.69 **dipasang tetapi tidak digunakan** di mana-mana; `owen-it/laravel-auditing`; `spatie/laravel-permission` |
| Pangkalan data pembangunan | MySQL `sppictv2_db` (dibaca sahaja menggunakan `SHOW COLUMNS` / `SELECT`) |
| Pangkalan data ujian | SQLite `:memory:` (phpunit.xml) — selamat; DB pembangunan tidak disentuh |
| Mel | `MAIL_MAILER=smtp`, `QUEUE_CONNECTION=database`; ujian guna `array` + `sync` |
| Status migration | Semua migration telah dijalankan (batch terakhir 20) |

Data semasa (DB pembangunan): 5 permohonan (3 `SUBMITTED`, 2 `DRAFT`), semua `jtict_decision = PENDING`, `review_remarks` dan `jtict_remarks` kosong pada semua rekod, `sst_rate = 8.00` pada semua rekod. Satu Mesyuarat JTICT (id 2) sedang dibuka: 21/10/2026 – 13/11/2026.

---

## 3. Inventori Fail, Komponen dan Model

### 3.1 Laluan (routes/web.php)

| Route | Sasaran | Middleware / kawalan |
|---|---|---|
| `GET /application/new` (`application.create`) | `App\Livewire\ApplicationForm` | `auth, verified, active, password.changed`, `can:create,Application` |
| `GET /permohonan/baru` | `pages.permohonan-baru` (view) | `can:create,Application` |
| `GET /application/edit/{uuid}` | `ApplicationForm` | `not-role:superadmin` + `authorize('update')` dalam `mount()` |
| `GET /application/view/{uuid}` | `App\Livewire\ViewApplication` | `not-role:superadmin` + `authorize('view')` |
| `GET /application/{applicationUuid}/documents/{documentUuid}` | `ApplicationDocumentController` | `Gate::authorize('view')` |

### 3.2 Komponen dan kelas

| Fail | Peranan |
|---|---|
| [app/Livewire/ApplicationForm.php](../app/Livewire/ApplicationForm.php) | Komponen borang 5 fasa: navigasi, draf, hantar, dokumen, pegawai |
| [app/Livewire/Forms/ApplicationForm.php](../app/Livewire/Forms/ApplicationForm.php) | Form Object: medan, pengesahan setiap fasa, `costSummary()` (SST) |
| [app/Services/ApplicationSubmissionService.php](../app/Services/ApplicationSubmissionService.php) | `submit()`, `saveDraft()`, nombor rujukan, `syncDetails()`, `syncOfficers()`, `storeDocuments()` |
| [app/Services/SubmissionClosedException.php](../app/Services/SubmissionClosedException.php) | Dilontar apabila tiada Mesyuarat JTICT aktif semasa hantar |
| [app/Services/OfficerHandoverService.php](../app/Services/OfficerHandoverService.php) | Pertukaran pegawai selepas dihantar + notifikasi kepada Urus Setia |
| [app/Livewire/ViewApplication.php](../app/Livewire/ViewApplication.php) | Paparan permohonan + modal tukar pegawai |
| [app/Livewire/ApplicationList.php](../app/Livewire/ApplicationList.php) | Senarai permohonan (pemohon / Urus Setia) |
| [app/Policies/ApplicationPolicy.php](../app/Policies/ApplicationPolicy.php) | `create`, `view`, `update`, `manageOfficers`, `updateProgress` |
| [app/Http/Controllers/ApplicationDocumentController.php](../app/Http/Controllers/ApplicationDocumentController.php) | Strim dokumen dari storan private |
| [app/Rules/DocumentSignature.php](../app/Rules/DocumentSignature.php) | Semakan tandatangan kandungan PDF/DOCX |
| [app/Notifications/ResponsibleOfficerChanged.php](../app/Notifications/ResponsibleOfficerChanged.php) | E-mel pertukaran pegawai (tidak di-queue) |
| [app/Notifications/MeetingAnnouncement.php](../app/Notifications/MeetingAnnouncement.php) | E-mel makluman mesyuarat (`ShouldQueue`) |
| [config/sppict.php](../config/sppict.php) | `sst_rate` (env `SST_RATE`, lalai 8) |

### 3.3 Paparan

`resources/views/livewire/application-form.blade.php` dan partial `application-form/phase-1-project-details`, `phase-2-financial-details`, `phase-3-item-details`, `phase-4-documents`, `phase-5-review-submit`, `partials/officer-card`; `view-application.blade.php`; `application-list.blade.php`; `partials/jtict-status.blade.php`; `dashboard/urus-setia.blade.php`.

### 3.4 Model dan jadual

| Model | Jadual | Nota |
|---|---|---|
| `Application` | `applications` | SoftDeletes, HasUuids, Auditable |
| `ApplicationDetail` | `application_details` | Perincian Fasa 3 |
| `ApplicationDocument` | `application_documents` | Dokumen Fasa 4 |
| `ApplicationOfficer` | `application_officers` | Pegawai Fasa 5 dengan sejarah |
| `Meeting`, `MeetingName`, `MeetingNumber`, `MeetingYear` | `meetings`, `meeting_names`, … | `meeting_names.accepts_applications` menandakan JTICT |
| `ProjectCategory`, `ApplicationCategory`, `ProcurementType`, `ProcurementMethod` | master data | Diselenggara Urus Setia |

---

## 4. Status Pelaksanaan Fasa 1 hingga Fasa 5

### Fasa 1 — Maklumat Am — **LENGKAP**

[FAKTA] `validateStep1()`: `title` wajib (maks 255), `application_category` wajib dan mesti wujud dalam `application_categories.code` yang aktif (kecuali nilai yang telah disimpan pada rekod ini), `objectives` dan `project_scope` wajib (min 10 aksara). Disimpan dalam `applications.title`, `application_category` (kod), `objectives`, `project_scope`. Dipulihkan dalam `ApplicationForm::mount()`.

### Fasa 2 — Maklumat Perolehan — **LENGKAP (dengan nota)**

[FAKTA] `validateStep2()`: jenis & kaedah perolehan wajib (FK master aktif), `ceiling_cost` ≥ 1000, `estimated_cost` ≥ 1000 dan ≤ `ceiling_cost`, `expected_duration_months` ≥ 1, `outcome_code` wajib. Nama master disimpan sebagai snapshot (`procurement_type`, `procurement_method`) bersama FK.

[FAKTA] Medan "Rujukan Kelulusan Peruntukan" dilaksanakan sebagai `outcome_code` dengan label "Rujukan Kelulusan" (placeholder "Bajet Mengurus"). Nama kolum (`outcome_code` — asalnya "Kod Hasil / Vot") tidak sepadan dengan maksud medan. Tiada had panjang dalam pengesahan (kolum `varchar(255)`).

### Fasa 3 — Perincian Projek — **SEPARA LENGKAP**

[FAKTA] Struktur borang: `form.details = [['project_category_id' => int, 'items' => [['technical_specifications', 'unit_quantity', 'unit_cost'], ...]], ...]` — item dikumpul mengikut Kategori Projek. Pengesahan `validateStep3()`: kategori wajib (FK master aktif), spesifikasi wajib (min 3), kuantiti integer ≥ 1, kos seunit numeric ≥ 0, dan **Jumlah Keseluruhan termasuk SST tidak boleh melebihi Anggaran Kos Siling** (`details_total`).

[FAKTA] Belum ada: templat CSV, import CSV, checkbox SST. "Jumlah (RM)" setiap item hanya dikira dalam Blade untuk paparan; nilai yang disimpan dikira semula dalam `syncDetails()`.

### Fasa 4 — Muat Naik Dokumen — **LENGKAP (dengan nota)**

[FAKTA] Kertas Cadangan **wajib** (PDF/DOCX), Slaid Pembentangan **pilihan** (PDF sahaja). Lihat Seksyen 9.

### Fasa 5 — Pegawai Bertanggungjawab — **LENGKAP (bergantung pada agensi)**

[FAKTA] Pegawai Utama wajib, Pegawai Kedua pilihan (`has_secondary_officer`), mesti berbeza. Calon = pengguna aktif dalam agensi permohonan. Lihat Seksyen 9.

### Pemerhatian umum borang

- [FAKTA] Pengesahan setiap fasa berlaku apabila klik "Seterusnya" dan **semua lima fasa disahkan semula di pelayan** dalam `submitApplication()`.
- [FAKTA] `saveDraft()` **tidak** menjalankan pengesahan fasa (direka bentuk supaya draf boleh separa). Akibatnya nilai seperti kos seunit negatif atau sangat besar boleh disimpan dalam draf (tiada had maksimum; kolum `decimal(15,2)`), dan limpahan nombor akan menyebabkan ralat SQL yang dipaparkan kepada pengguna melalui `'Gagal menyimpan draf: ' . $e->getMessage()`.
- [FAKTA] `$applicationId` ditanda `#[Locked]` — tidak boleh diubah dari pelayar (diuji dalam `test_application_id_cannot_be_tampered_from_browser`).

---

## 5. Analisis Peranan Pengguna Biasa dan Urus Setia

### 5.1 Pengguna Biasa

| Aspek | Dapatan |
|---|---|
| Penentuan agensi | [FAKTA] `ApplicationSubmissionService::applicantAgencyId()` mengambil `$user->agency_id`; mesti wujud dan aktif, jika tidak ralat `form.agency` |
| Pemilikan | [FAKTA] `applications.applicant_id = $user->id`, `agency_id` = agensi pengguna; ditetapkan hanya semasa rekod baharu dicipta |
| Lihat | [FAKTA] `ApplicationPolicy::view`: pemohon, **mana-mana pengguna dalam agensi yang sama**, pegawai aktif, atau Urus Setia |
| Kemaskini / draf / hantar | [FAKTA] `ApplicationPolicy::update`: pemohon sahaja dan status `DRAFT` / `NEEDS_AMENDMENT`; disemak dalam `mount()`, `deleteDocument()` dan semula dalam `resolveApplication()` (dengan `lockForUpdate`) |
| Akses permohonan pihak lain | [FAKTA] Tidak boleh mengubah. **Boleh melihat** permohonan rakan seagensi, termasuk **draf** mereka melalui URL UUID (polisi `view` tidak menyemak status). Senarai hanya memaparkan milik sendiri / yang dipertanggungjawabkan |

### 5.2 Urus Setia

| Aspek | Dapatan |
|---|---|
| Pilihan jabatan/agensi sasaran | [FAKTA] **Tiada.** Tiada medan, dropdown atau pengesahan agensi sasaran dalam borang, Form Object atau Service. `grep agency` dalam paparan borang hanya menemui paparan ralat `form.agency` |
| Agensi yang disimpan | [FAKTA] Agensi **akaun Urus Setia sendiri** (`$user->agency_id`) |
| Calon pegawai Fasa 5 | [FAKTA] `officerCandidates()` menggunakan `Auth::user()->agency_id` untuk rekod baharu — iaitu pegawai agensi Urus Setia, bukan agensi sasaran |
| Senarai draf Urus Setia | [FAKTA] `ApplicationList` untuk Urus Setia hanya memaparkan `status = 'SUBMITTED'`; draf yang dicipta oleh Urus Setia sendiri **tidak muncul dalam mana-mana senarai** (hanya boleh dibuka melalui URL) |
| Akses kemaskini | [FAKTA] Urus Setia hanya boleh mengemaskini permohonan yang beliau sendiri menjadi `applicant_id`; tiada skop "bagi pihak agensi" |
| Data semasa | [FAKTA] 0 permohonan dicipta oleh Urus Setia dalam DB pembangunan |

**Kesimpulan:** Keperluan 3.2 (Urus Setia memilih agensi sasaran) **belum dilaksanakan**. Pelaksanaan memerlukan:

[CADANGAN]
1. Medan `target_agency_id` dalam borang, dipaparkan **hanya** untuk `pentadbir_urus_setia`, wajib sebelum Fasa 1 / sebelum simpan pertama.
2. Pengesahan pelayan: `Rule::exists` ke `agencies` yang aktif dan tidak dipadam; pengguna biasa sentiasa diabaikan dan dipaksa kepada `$user->agency_id`.
3. Agensi ditetapkan sekali semasa rekod dicipta (dikunci selepas itu), atau dibenarkan diubah hanya semasa `DRAFT` dengan pegawai Fasa 5 dikosongkan semula.
4. `officerCandidates()` dan `syncOfficers()` menggunakan agensi permohonan (sudah begitu untuk rekod sedia ada; perlu dipadankan untuk rekod baharu).
5. Rekod siapa yang mencipta: `applicant_id` kekal = Urus Setia, atau tambah kolum `created_on_behalf_by` — **perlu keputusan** (siapa "pemohon" untuk e-mel dan kebenaran kemaskini Tidak Lengkap?).
6. Senarai Urus Setia perlu memaparkan draf milik sendiri.

---

## 6. Analisis Draf dan Syarat Mesyuarat JTICT Aktif

### 6.1 Definisi "Mesyuarat JTICT aktif" yang sebenarnya digunakan

[FAKTA] `Meeting::currentJtict()` = `jtict()->openForSubmission()->orderBy('submission_closes_at')->first()` di mana:

- `scopeJtict`: `meeting_names.accepts_applications = true`
- `scopeOpenForSubmission`: `is_active = true` **DAN** `submission_opens_at` dan `submission_closes_at` tidak null **DAN** `submission_opens_at ≤ hari ini ≤ submission_closes_at` (inklusif, tarikh sahaja)
- Soft-deleted dikecualikan secara automatik.
- `MeetingForm::ensureNoOverlappingOpenMeeting()` menghalang tempoh terbuka bertindih; `submission_closes_at` mesti sebelum `meeting_date`.

Definisi ini **jelas dan konsisten**; tiada perubahan dicadangkan.

### 6.2 Penguatkuasaan

| Semakan | Dapatan |
|---|---|
| Butang Hantar | [FAKTA] `@disabled(! $this->targetMeeting)` + tooltip; `partials/jtict-status` memaparkan status |
| Pelayan | [FAKTA] `ApplicationSubmissionService::submit()` menyemak semula dalam transaksi dengan `Meeting::lockForUpdate()`. Jika tiada mesyuarat → rekod disimpan sebagai draf dan `SubmissionClosedException` dilontar dengan mesej jelas termasuk No. Rujukan |
| Kaitan mesyuarat | [FAKTA] `applications.jtict_meeting_id = $meeting->id` ditetapkan semasa hantar |
| Draf tanpa mesyuarat | [FAKTA] Dibenarkan (`saveDraft()` tidak menyemak mesyuarat) |
| `NEEDS_AMENDMENT` | [FAKTA] `Application::targetJtictMeeting()` mengekalkan mesyuarat asal walaupun tempoh telah ditutup |
| Konsisten antara peranan | [FAKTA] Ya — laluan kod yang sama untuk Pengguna Biasa dan Urus Setia |
| Ujian | [FAKTA] `JtictSubmissionWindowTest` (8 ujian) lulus |

### 6.3 Isu berkaitan

- [FAKTA] Data: Mesyuarat id 1 (tidak aktif) mempunyai `submission_closes_at = 2026-01-09` **lebih awal** daripada `submission_opens_at = 2026-10-05`. Tidak menjejaskan logik (mesyuarat tidak aktif) tetapi menunjukkan rekod lama tidak melalui pengesahan semasa.
- [BELUM PASTI] Peraturan untuk permohonan **Tidak Lengkap** yang dikembalikan selepas mesyuarat asal telah berlangsung: kod semasa membenarkan penghantaran semula ke mesyuarat asal walaupun mesyuarat telah lepas. Perlu keputusan sama ada perlu dialihkan ke mesyuarat aktif semasa.

---

## 7. Analisis CSV dan Cadangan Templat Rasmi

### 7.1 Keadaan semasa

[FAKTA] Tiada fungsi CSV sama sekali: tiada route muat turun templat, tiada import, tiada `fputcsv`/`str_getcsv`/`SplFileObject`. Pakej `maatwebsite/excel` dipasang dalam `composer.json` tetapi tiada kelas Import/Export. Muat naik Fasa 4 **tidak** berkaitan dengan perincian.

### 7.2 Struktur data perincian sebenar (`application_details`)

| Kolum | Jenis | Wajib | Sumber |
|---|---|---|---|
| `id`, `uuid` | bigint, char(36) | ya | sistem |
| `application_id` | FK → applications (cascade) | ya | sistem |
| `project_category_id` | FK → project_categories, nullable | ya (logik) | pengguna |
| `item_category` | varchar(255) | ya | snapshot `project_categories.name` |
| `technical_specifications` | text | ya | pengguna (`strip_tags`) |
| `unit_quantity` | int | ya | pengguna (≥ 1) |
| `unit_cost` | decimal(15,2) | ya | pengguna (≥ 0) |
| `total_cost` | decimal(15,2) | ya | sistem (`qty × unit_cost`) |
| `created_at`, `updated_at`, `deleted_at` | | | sistem |

[FAKTA] Mekanisme simpan: `syncDetails()` **soft-delete semua perincian lama** dan mencipta semula daripada `form.details` setiap kali draf disimpan atau dihantar. (DB pembangunan: 102 daripada 130 baris perincian adalah soft-deleted — kesan sampingan reka bentuk ini.) Item dengan spesifikasi kosong dilangkau secara senyap.

### 7.3 Cadangan reka bentuk templat dan import

[CADANGAN]

**Lajur templat (tetap, mengikut susunan):**

| # | Tajuk lajur | Peraturan |
|---|---|---|
| 1 | `kod_kategori_projek` | Mesti padan `project_categories.code` yang aktif (kod lebih stabil daripada nama; nama boleh berubah) |
| 2 | `spesifikasi_teknikal` | Wajib, min 3, maks (cadang) 2000 aksara, `strip_tags` |
| 3 | `kuantiti` | Integer ≥ 1, had atas munasabah (contoh ≤ 100 000) |
| 4 | `kos_seunit_rm` | Numeric ≥ 0, maks 2 tempat perpuluhan, had atas < 10^13, tanpa simbol "RM" atau koma ribuan |

Jumlah dan SST **tidak** dimasukkan dalam CSV — dikira oleh sistem. Templat boleh disertakan baris kedua komen/contoh, atau helaian rujukan kod kategori (jika XLSX dipilih).

**Lokasi:**
- Butang "Muat Turun Templat CSV" dan "Import CSV" dalam `phase-3-item-details.blade.php`.
- Muat turun melalui route GET berasingan (contoh `application.details-template`) dengan middleware sama seperti `application.create`, menjana CSV secara dinamik (`response()->streamDownload`) supaya senarai kod kategori sentiasa terkini. UTF-8 dengan BOM untuk keserasian Excel.

**Aliran import (paling selamat):**
1. Fail dimuat naik sebagai `TemporaryUploadedFile` Livewire (tidak disimpan kekal).
2. Pengesahan fail: `extensions:csv,txt`, `mimetypes:text/csv,text/plain`, saiz maks kecil (cadang 1 MB; jauh di bawah had Livewire 25 MB / PHP 30 MB), had baris (cadang 500), pengekodan UTF-8 (buang BOM), pemisah `,` (atau kesan `;`).
3. Pengesahan tajuk lajur: mesti tepat sama dengan templat.
4. Pengesahan setiap baris; kumpul **semua** ralat dengan nombor baris; abaikan baris kosong sepenuhnya.
5. **Semua-atau-tiada:** jika ada mana-mana baris tidak sah, tiada data diimport dan perincian sedia ada kekal tanpa perubahan; ralat dipaparkan dengan nombor baris.
6. Jika semua sah: paparkan **pratonton** (bilangan item, jumlah kecil, SST, jumlah) dan minta pengesahan "Ganti perincian sedia ada" atau "Tambah kepada perincian sedia ada".
7. Data hasil import ditukar kepada struktur `form.details` yang sama (dikumpul mengikut `project_category_id`). **Tiada laluan simpan baharu** — perincian hanya disimpan apabila pengguna klik Simpan Draf / Hantar, menggunakan `syncDetails()` dan `validateStep3()` sedia ada. Ini menjamin keputusan manual dan CSV setara.
8. Fail sementara dibuang selepas diproses; **tidak disimpan kekal** (data telah ada dalam `application_details`; audit melalui `owen-it/auditing`).
9. Keselamatan: neutralkan formula injection jika data dieksport semula kepada CSV/Excel kelak (nilai bermula `=`, `+`, `-`, `@`); jangan sekali-kali `eval`/`include` kandungan; gunakan `fgetcsv` atau League CSV dan bukan pemprosesan regex buatan sendiri.

**Senario:**

| Senario | Cadangan |
|---|---|
| CSV sebelum ada perincian | Item kosong lalai (satu baris kosong) digantikan |
| Sudah ada perincian manual | Wajib pilih Ganti / Tambah selepas pratonton; lalai = tiada perubahan sehingga pengguna sahkan |
| Import kali kedua | Sama seperti di atas; "Ganti" mengelakkan pendua |
| Sebahagian baris tidak sah | Tolak keseluruhan fail (semua-atau-tiada) |
| Batal / gagal | `form.details` tidak diubah; tiada apa-apa disimpan ke DB |

[BELUM PASTI] Keputusan pengguna diperlukan: (a) Ganti vs Tambah sebagai pilihan lalai; (b) CSV sahaja atau juga XLSX (pakej `maatwebsite/excel` sedia ada); (c) had baris dan had nilai maksimum; (d) kod atau nama kategori dalam templat.

**Perubahan DB:** Tiada migration diperlukan untuk CSV (perubahan kod sahaja).

---

## 8. Analisis Pengiraan SST Semasa dan Reka Bentuk Checkbox 8%

### 8.1 Formula semasa

[FAKTA] `Forms\ApplicationForm::costSummary()` (satu-satunya lokasi formula dalam kod aplikasi):

```
subtotalSen = Σ (int)unit_quantity × round(unit_cost × 100)   // dalam sen
sstSen      = round(subtotalSen × sst_rate / 100)
totalSen    = subtotalSen + sstSen
```

- SST dikira **ke atas Jumlah Kecil keseluruhan perincian** (bukan setiap item).
- Kadar daripada `config('sppict.sst_rate')` = `env('SST_RATE', 8)`. Kadar **boleh diubah melalui `.env`**, bukan tetap dalam kod.
- **Tiada checkbox** — SST sentiasa dikenakan pada setiap permohonan.

### 8.2 Semua lokasi pengiraan

| Lokasi | Fungsi |
|---|---|
| `Forms\ApplicationForm::costSummary()`, `subtotalSen()`, `toSen()` | Pengiraan sumber (pelayan) |
| `Forms\ApplicationForm::validateStep3()` | Bandingkan jumlah termasuk SST dengan Kos Siling |
| `ApplicationSubmissionService::fillApplication()` | Simpan `subtotal_cost`, `sst_rate`, `sst_amount`, `total_cost` |
| `ApplicationSubmissionService::syncDetails()` | `total_cost` setiap item = `qty × unit_cost` (float, tidak melalui sen) |
| `phase-3-item-details.blade.php` | Paparan ringkasan (memanggil `costSummary()` di pelayan); "Jumlah (RM)" setiap baris dikira semula dalam Blade |
| `view-application.blade.php` | Memaparkan nilai **tersimpan** (`sst_rate`, `sst_amount`, `total_cost`) |
| Migration `2026_10_02_000003_recalculate_legacy_sst_on_applications` | Pengiraan semula sekali (sejarah) |
| Tiada JavaScript | [FAKTA] Tiada pengiraan dalam JS; semua melalui round-trip Livewire (`wire:model.live`) |

### 8.3 Penyimpanan dan pemulihan

- [FAKTA] Medan DB: `applications.subtotal_cost`, `sst_rate decimal(5,2)`, `sst_amount`, `total_cost` (semua nullable).
- [FAKTA] Apabila draf dibuka semula, SST **dikira semula** daripada perincian dan kadar konfigurasi semasa (bukan dibaca daripada `sst_rate` tersimpan). Jika `SST_RATE` berubah, draf lama akan menggunakan kadar baharu apabila disimpan semula; rekod yang telah dihantar kekal dengan nilai tersimpan.
- [FAKTA] Manipulasi: `sst_amount`/`total_cost` **bukan** sifat Livewire awam — tidak boleh dihantar oleh pelayar. Pelayar hanya boleh mengubah `form.details` (kuantiti/kos) yang disahkan semula semasa hantar. Selamat.
- [FAKTA] Konsistensi data semasa: 0 rekod dengan ringkasan kos tidak konsisten; 0 perincian dengan `total_cost ≠ qty × unit_cost`.
- [FAKTA] `ceiling_cost` merujuk jumlah **termasuk SST** ("Had maksimum termasuk SST"). Oleh itu **SST mempengaruhi kelayakan penghantaran** — checkbox SST akan mengubah sama ada permohonan melepasi semakan siling.
- [FAKTA] Tiada logik lain (kategori, aliran kelulusan, JPICT) bergantung pada `total_cost` dalam kod semasa.

### 8.4 Reka bentuk checkbox SST 8%

[CADANGAN]
1. **Migration tambahan:** `applications.sst_applicable boolean NOT NULL DEFAULT true`. Lalai `true` mengekalkan tingkah laku semua rekod sedia ada (semua telah dikenakan SST 8%) — tiada pengiraan semula diperlukan.
2. Form Object: `public bool $sst_applicable = true;` (atau lalai `false` untuk permohonan baharu — **perlu keputusan**). `costSummary()` → `sstSen = sst_applicable ? round(subtotalSen × 8 / 100) : 0`; `sst_rate` disimpan sebagai `8.00` atau `0.00`/`null` apabila tidak dikenakan.
3. Kadar: tukar kepada pemalar kod `SST_RATE = 8` (atau kekalkan config tetapi alih keluar sokongan `.env`) supaya selaras dengan keputusan "ditetapkan oleh sistem". Pengguna tidak boleh mengubah kadar — kadar bukan sifat Livewire awam.
4. `mount()` memulihkan `sst_applicable` daripada rekod; `fillApplication()` menyimpannya.
5. Paparan: checkbox dalam Fasa 3 di atas Ringkasan Kos; `view-application` paparkan "SST tidak dikenakan" apabila `false`.
6. Ujian: checkbox on/off, pemulihan semasa buka semula, manipulasi (`set('form.sst_applicable', 'x')` mesti gagal pengesahan `boolean`), semakan siling dengan/tanpa SST.
7. Amaran `hasManualSstItem()` dikekalkan.

[BELUM PASTI]
- Nilai lalai checkbox untuk permohonan baharu (ditanda atau tidak).
- Adakah `syncDetails()` patut menggunakan pengiraan sen yang sama untuk `total_cost` setiap item (kini float `$qty * $unitCost`; berisiko perbezaan 1 sen pada nilai ekstrem). Cadangan: satu kaedah `lineTotalSen()` dikongsi.

---

## 9. Analisis Muat Naik Dokumen dan Maklumat Pegawai

### 9.1 Fasa 4 — Dokumen

| Aspek | Dapatan [FAKTA] |
|---|---|
| Jenis | Kertas Cadangan: `pdf`, `docx`. Slaid: `pdf` sahaja. Disemak melalui `extensions`, `mimes` **dan** `DocumentSignature` (magic bytes `%PDF-`; DOCX = ZIP yang mengandungi `word/document.xml`) |
| Had saiz | 25 MB (`MAX_DOCUMENT_KB = 25600`); Livewire temp 25 MB; PHP `upload_max_filesize` 30M / `post_max_size` 35M; had pelayan web (nginx Herd `client_max_body_size`) **tidak dapat disahkan** daripada repo |
| Semakan segera | `ApplicationForm::updated()` mengesahkan dan membuang fail tidak sah sebaik dipilih |
| Penyimpanan | Disk `local` (private) — `storage/app/private/applications/{uuid}/documents/` |
| Kaitan | `application_documents.application_id`, `document_type` (`PROPOSAL_PAPER`, `PRESENTATION_SLIDE_JTICT`) |
| Ganti | Dokumen lama jenis sama di-soft delete; fail fizikal dikekalkan untuk audit |
| Padam | `deleteDocument()` dengan `authorize('update')` — hanya Draf / Tidak Lengkap |
| Muat turun | `ApplicationDocumentController` dengan `Gate::authorize('view')`; PDF `inline`, DOCX `attachment`; dokumen soft-deleted tidak boleh dibuka melalui route |
| Wajib sebelum hantar | Kertas Cadangan **wajib**; Slaid **pilihan** |

[BELUM PASTI] Prompt menyenaraikan Slaid Pembentangan sebagai dokumen Fasa 4 tanpa menyatakan wajib atau tidak. Kod semasa menjadikannya pilihan.

### 9.2 Fasa 5 — Pegawai Bertanggungjawab

| Aspek | Dapatan [FAKTA] |
|---|---|
| Sumber calon | `User::eligibleOfficersFor($agencyId)`: pengguna `active()` dalam agensi yang sama, bukan superadmin |
| Pengesahan | `Rule::in($eligibleOfficerIds)` di pelayan; Kedua `different:primary_officer_id` |
| Penyimpanan | `application_officers` (bukan kolum `primary_officer_*` lama dalam `applications` — kolum lama masih wujud, tidak digunakan) |
| Snapshot | `ApplicationOfficer::snapshotFrom()` menyimpan nama, jawatan, gred, e-mel, telefon, agensi |
| Draf | Rekod aktif dikemas kini terus tanpa sejarah; `started_at` ditetapkan semasa dihantar |
| Selepas dihantar | Pertukaran melalui `OfficerHandoverService` dengan sejarah (`ended_at`, `end_reason`) |
| E-mel | `ResponsibleOfficerChanged` dihantar kepada **Urus Setia** apabila pegawai ditukar selepas dihantar; tidak di-queue (`ShouldQueue` tiada) |

[FAKTA] Isu Urus Setia: calon pegawai bagi permohonan baharu oleh Urus Setia datang daripada agensi Urus Setia, bukan agensi sasaran (lihat 5.2).

[CADANGAN] Jika pegawai akan menjadi penerima e-mel keputusan (Seksyen 14), gunakan e-mel daripada akaun pengguna aktif (`application_officers.user_id → users.email`) dengan fallback kepada snapshot.

---

## 10. Analisis Semakan Urus Setia

[FAKTA] **Tidak dilaksanakan.**

| Perkara | Dapatan |
|---|---|
| Status dalam DB | `applications.status` enum: `DRAFT`, `SUBMITTED`, `UNDER_REVIEW`, `NEEDS_AMENDMENT`, `APPROVED_URUS_SETIA`, `REJECTED_URUS_SETIA` |
| Status yang benar-benar ditulis oleh kod | Hanya `DRAFT` dan `SUBMITTED` |
| Status yang dibaca oleh kod | `DRAFT`, `NEEDS_AMENDMENT` (`EDITABLE_STATUSES`, `targetJtictMeeting`), `SUBMITTED` (senarai Urus Setia) |
| Komponen / tindakan semakan | Tiada. Pautan "Semak" dalam senarai Urus Setia hanya membuka `ViewApplication` (paparan baca + tukar pegawai) |
| Polisi | Tiada kaedah `review` / `decide` dalam `ApplicationPolicy` |
| Ulasan wajib Tidak Lengkap | Tiada pengesahan; kolum `review_remarks` wujud tetapi **tidak pernah ditulis atau dipaparkan** |
| Pemohon melihat ulasan | Tidak — `view-application.blade.php` dan borang tidak memaparkan `review_remarks` |
| Penghantaran semula | [FAKTA] Separa sedia: `NEEDS_AMENDMENT` boleh disunting oleh pemohon dan dihantar semula ke mesyuarat asal (ada ujian). `review_remarks` lama tidak dikosongkan |
| Sejarah semakan | Tiada — satu kolum `review_remarks` akan ditimpa; hanya log `audits` generik |
| E-mel | Tiada |

**Ketidakselarasan status (penting):**
- [FAKTA] `application-list.blade.php` dan `dashboard/urus-setia.blade.php` menggunakan `IN_REVIEW`, `APPROVED`, `REJECTED` — **tiada dalam enum DB**. Penapis status "Lulus"/"Ditolak" sentiasa kosong; kad papan pemuka "Lulus"/"Ditolak" sentiasa 0.
- [FAKTA] Label `NEEDS_AMENDMENT` dipaparkan sebagai "Perlu Pembetulan"; keperluan menggunakan istilah "Tidak Lengkap".
- [FAKTA] Enum mengandungi `APPROVED_URUS_SETIA`/`REJECTED_URUS_SETIA` — maksud "Lulus/Tolak oleh Urus Setia" bercanggah dengan keperluan bahawa Urus Setia hanya menyemak kelengkapan.

[CADANGAN] Lihat model status di Seksyen 16.3. Ulasan Tidak Lengkap wajib (`required_if`, min 10), disimpan dalam jadual sejarah, dipaparkan kepada pemohon dalam borang edit dan paparan, e-mel kepada pemohon (dan pegawai — perlu keputusan).

---

## 11. Analisis Keputusan Pra JTICT

[FAKTA] **Tidak dilaksanakan.**

- Tiada status, kolum keputusan atau ulasan Pra JTICT.
- Kolum `pra_jtict_meeting_id` pernah wujud tetapi **telah dibuang** melalui migration `2026_10_05_000005_drop_pra_jtict_meeting_id_from_applications_table` (keputusan reka bentuk terdahulu: Pra JTICT tidak direkodkan sebagai mesyuarat dalam sistem).
- Tiada polisi atau komponen keputusan.

[CADANGAN]
- Status `PRE_JTICT_ACCEPTED` / `PRE_JTICT_REJECTED` (atau satu status + kolum keputusan) dan ulasan wajib bagi Ditolak.
- Keputusan hanya dibenarkan daripada status `COMPLETE` (Lengkap).
- Permohonan Diterima dikaitkan dengan `jtict_meeting_id` yang telah ditetapkan semasa dihantar (sudah wujud). [BELUM PASTI] Adakah Urus Setia boleh mengalihkan permohonan ke Mesyuarat JTICT lain pada peringkat ini?
- [BELUM PASTI] Adakah Pra JTICT perlu direkodkan sebagai mesyuarat (tarikh, bilangan) atau sekadar keputusan + tarikh keputusan? Keputusan terdahulu membuang kolum mesyuarat Pra JTICT.
- [BELUM PASTI] Adakah keputusan Ditolak Pra JTICT perlu e-mel kepada pemohon? (Tidak disenaraikan dalam Seksyen 11 prompt.)

---

## 12. Analisis Keputusan Mesyuarat JTICT

[FAKTA] **Tidak dilaksanakan** (struktur warisan sahaja).

| Perkara | Dapatan |
|---|---|
| Kolum | `jtict_decision` enum (`PENDING`, `APPROVED`, `APPROVED_CONDITIONAL`, `REVIEW`, `REJECTED`) default `PENDING`; `jtict_remarks` text; `jtict_meeting_id` FK |
| Penulisan | Tiada kod menulis `jtict_decision` atau `jtict_remarks` |
| Pemetaan keperluan | Lulus → `APPROVED`; Tidak Lulus → `REJECTED`; Kaji Semula → `REVIEW`; `APPROVED_CONDITIONAL` tidak diperlukan |
| Kolum JPICT | `jpict_meeting_id`, `jpict_decision`, `jpict_remarks` wujud, tidak digunakan |
| Sejarah | Tiada; satu kolum akan ditimpa |
| Paparan kepada pemohon | Tiada |
| Pengubahan keputusan | Tiada mekanisme; tiada audit khusus selain `owen-it/auditing` |

[CADANGAN] Gunakan semula `jtict_decision` + `jtict_remarks` sebagai **nilai semasa** (perubahan kod sahaja), dan tambah jadual sejarah keputusan (Seksyen 16.3) untuk jejak audit dan pembetulan keputusan. Ulasan wajib untuk ketiga-tiga keputusan. Keputusan dibenarkan hanya daripada status Diterima Pra JTICT dan selepas `meeting_date` mesyuarat berkaitan ([BELUM PASTI]).

---

## 13. Analisis Fungsi Salin Permohonan Kaji Semula

[FAKTA] **Tidak wujud.** Tiada `replicate()`, route, butang atau polisi `copy`.

### Reka bentuk yang dicadangkan [CADANGAN]

1. **Polisi** `ApplicationPolicy::copy(User, Application)`: pemohon asal (atau Urus Setia bagi pihak agensi — perlu keputusan), `jtict_decision === 'REVIEW'`, pengguna `create` dibenarkan, dan agensi asal masih aktif.
2. **Service** `ApplicationCopyService::copy()` dalam `DB::transaction`:
   - Rekod baharu: `uuid` baharu, `reference_number` draf baharu (`nextReferenceNumber(isDraft: true)` — kaedah perlu dijadikan boleh dikongsi), `status = DRAFT`, `applicant_id` = pengguna semasa, `agency_id` = agensi asal.
   - Salin Fasa 1–2: `title`, `application_category`, `objectives`, `project_scope`, `procurement_type(_id)`, `procurement_method(_id)`, `ceiling_cost`, `estimated_cost`, `expected_duration_months`, `outcome_code`.
   - Salin Fasa 3: setiap `application_details` aktif (bukan soft-deleted) dicipta semula dengan `uuid` baharu dan `application_id` baharu — **tiada rekod dikongsi**.
   - Salin `sst_applicable`; **kira semula** `subtotal_cost`/`sst_amount`/`total_cost` menggunakan mekanisme yang sama (`costSummary()`), bukan salin nombor.
   - **Tidak disalin:** dokumen, pegawai, `jtict_meeting_id`, `jpict_*`, `review_remarks`, `jtict_decision` (kekal `PENDING`), `jtict_remarks`.
   - Rekod asal hanya dibaca (`lockForUpdate` untuk mengelak salinan serentak); tiada `save()` pada rekod asal.
3. **Jejak audit:** migration tambahan `applications.copied_from_application_id` (FK nullable, `nullOnDelete`) — membolehkan paparan "Disalin daripada SPPICT/2026/XXXX".
4. **Elak salinan berulang:** [BELUM PASTI] Adakah satu permohonan Kaji Semula boleh disalin lebih daripada sekali? Jika tidak, semak `copied_from_application_id` sedia ada.
5. Master data tidak aktif: nilai yang disalin mungkin tidak aktif lagi; pengesahan sedia ada (`masterRule` dengan `savedMasterValues`) akan **membenarkannya** kerana ia kini nilai tersimpan pada rekod baharu. [BELUM PASTI] Patutkah master tidak aktif dikosongkan semasa salin supaya pemohon memilih semula?
6. Permohonan baharu tertakluk pada semua peraturan sedia ada (`submit()` + mesyuarat aktif) tanpa perubahan.

---

## 14. Analisis E-mel dan Notifikasi

### 14.1 Mekanisme sedia ada

[FAKTA] Hanya dua kelas notifikasi wujud, kedua-duanya saluran `mail` sahaja (tiada jadual `notifications`):

| Notifikasi | Penerima | Queue | Pengendalian kegagalan |
|---|---|---|---|
| `MeetingAnnouncement` | Ahli mesyuarat aktif | `ShouldQueue` (database) | Melalui `failed_jobs` (0 rekod) |
| `ResponsibleOfficerChanged` | Urus Setia | Segerak | Pengecualian SMTP akan menggagalkan permintaan pertukaran pegawai [FAKTA berdasarkan kod; tiada try/catch dalam `OfficerHandoverService::notify()`] — **perlu disahkan** |

### 14.2 Peristiwa yang diaudit

| Peristiwa | Dilaksanakan | Penerima | Kandungan | Ujian |
|---|---|---|---|---|
| Tidak Lengkap dikembalikan | **Tidak** | — | — | — |
| JTICT Lulus | **Tidak** | — | — | — |
| JTICT Tidak Lulus | **Tidak** | — | — | — |
| JTICT Kaji Semula | **Tidak** | — | — | — |

[CADANGAN] Satu notifikasi `ApplicationDecisionNotification` (atau tiga kelas) dengan `ShouldQueue`, `afterCommit()`, kandungan: No. Rujukan, Nama Projek, agensi, keputusan, ulasan (di-escape), mesyuarat (label), pautan ke `application.view`. Penerima: pemohon + Pegawai Utama aktif (cc Pegawai Kedua — perlu keputusan). Ujian dengan `Notification::fake()`.

### 14.3 Isu berasingan — Modul Pengurusan Pengguna

[FAKTA] Pengujian e-mel self-register dan notifikasi kepada Urus Setia **belum selesai** (seperti dinyatakan). Audit ini juga mendapati **tiada kelas Notification/Mailable** untuk self-register atau kelulusan pendaftaran dalam `app/` (hanya dua notifikasi di atas). Ini direkodkan sebagai isu berasingan untuk Modul Pengurusan Pengguna; tiada perubahan dibuat dan PP0 tidak menganggapnya telah berjaya.

---

## 15. Analisis Keselamatan dan Kawalan Akses

| Kawalan | Dapatan |
|---|---|
| Middleware | [FAKTA] `auth`, `verified`, `active`, `password.changed`, `role:*`, `not-role:superadmin`; middleware keselamatan didaftarkan sebagai Livewire persistent middleware (diuji) |
| Polisi | [FAKTA] `ApplicationPolicy` disemak di route, `mount()`, setiap tindakan simpan dan semula dalam Service (`resolveApplication`) |
| Pengesahan input | [FAKTA] Semua fasa disahkan semula di pelayan semasa hantar; master data disahkan aktif |
| Pemilikan | [FAKTA] `update` = pemohon sahaja + status boleh sunting; `$applicationId` `#[Locked]` |
| Fail | [FAKTA] Storan private, route dengan `Gate::authorize('view')`, semakan tandatangan kandungan |
| Audit log | [FAKTA] `Application`, `ApplicationDetail`, `ApplicationDocument`, `Meeting` melaksanakan `Auditable` (18 rekod audit untuk Application) |
| SST/jumlah | [FAKTA] Dikira di pelayan; tidak boleh dimanipulasi terus |
| Rate limiting | [FAKTA] Tiada pada tindakan simpan/hantar permohonan (trait `RateLimitsActions` wujud untuk modul lain) |

**Risiko yang dikenal pasti:**

1. [FAKTA] `ApplicationPolicy::view` membenarkan **semua pengguna seagensi melihat draf** rakan sekerja dan dokumennya (melalui URL UUID). [BELUM PASTI] sama ada ini dikehendaki.
2. [FAKTA] Tiada kawalan keputusan (Lengkap … Kaji Semula) kerana fungsi belum wujud — polisi baharu mesti dibina (`review`, `decidePreJtict`, `decideJtict`, `copy`) dan dikuatkuasakan dalam komponen + Service, bukan hanya pada butang.
3. [FAKTA] Mesej ralat generik mendedahkan `$e->getMessage()` kepada pengguna (`ApplicationForm::saveDraft()`, `submitApplication()`), termasuk mesej SQL jika berlaku limpahan nombor atau ralat DB.
4. [FAKTA] Draf tidak disahkan — nilai negatif/ekstrem boleh disimpan dalam draf (disekat semasa hantar).
5. [CADANGAN] Bagi Urus Setia bagi pihak agensi: agensi sasaran mesti disahkan di pelayan dan diabaikan untuk peranan lain; kebenaran kemaskini permohonan perlu ditakrif semula jika Urus Setia boleh mengemaskini permohonan agensi yang bukan miliknya.

---

## 16. Analisis Pangkalan Data dan Keserasian Rekod Sedia Ada

### 16.1 Jadual berkaitan (skema sebenar)

| Jadual | Kekangan / indeks utama |
|---|---|
| `applications` | PK; unique `uuid`, `reference_number`; FK `agency_id`, `applicant_id`, `jtict_meeting_id`, `jpict_meeting_id`, `procurement_type_id`, `procurement_method_id`. **Tiada indeks pada `status`** |
| `application_details` | FK `application_id` (cascade), `project_category_id` |
| `application_documents` | FK `application_id` (cascade); enum `document_type` |
| `application_officers` | FK `application_id`, `user_id`, `agency_id`; `role`, `started_at`, `ended_at` |
| `meetings` / `meeting_names` | `accepts_applications`, `is_active`, tempoh buka–tutup |
| Kolum warisan tidak digunakan | `applications.primary_officer_*`, `secondary_officer_*`, `jpict_*` |

### 16.2 Klasifikasi perubahan

| Penambahbaikan | Struktur sedia ada | Kod sahaja | Migration tambahan |
|---|---|---|---|
| Agensi sasaran Urus Setia | `agency_id` sedia ada | ✔ | Pilihan: `created_on_behalf_by` (perlu keputusan) |
| Templat & import CSV | ✔ | ✔ | — |
| Checkbox SST | — | — | ✔ `sst_applicable` |
| Pembetulan label/status paparan | ✔ | ✔ | — |
| Semakan Urus Setia | Separa (`review_remarks`) | — | ✔ status enum + sejarah |
| Keputusan Pra JTICT | — | — | ✔ |
| Keputusan JTICT | ✔ `jtict_decision`, `jtict_remarks` | Separa | ✔ jadual sejarah (disyorkan) |
| Salin Kaji Semula | Separa | ✔ | ✔ `copied_from_application_id` (disyorkan) |
| E-mel keputusan | — | ✔ | — |

### 16.3 Migration yang dicadangkan (tidak dicipta / dijalankan dalam PP0)

**(a) `add_sst_applicable_to_applications_table`**
- Sebab: menyimpan keadaan checkbox.
- `applications.sst_applicable boolean NOT NULL DEFAULT true` selepas `sst_rate`.
- Kesan: semua 5 rekod sedia ada menjadi `true`, sepadan dengan `sst_amount > 0` sedia ada. Tiada pengiraan semula.
- `down()`: buang kolum.

**(b) `extend_application_status_enum`** — Tambah (bukan buang) nilai enum.
- Cadangan nilai baharu: `COMPLETE` (Lengkap), `PRE_JTICT_ACCEPTED`, `PRE_JTICT_REJECTED`, `JTICT_APPROVED`, `JTICT_NOT_APPROVED`, `JTICT_REVIEW` — **atau** kekalkan status peringkat dan simpan keputusan dalam kolum keputusan. **Perlu keputusan** (lihat Seksyen 19).
- `NEEDS_AMENDMENT` dikekalkan sebagai "Tidak Lengkap" (nilai DB tidak ditukar; hanya label).
- `UNDER_REVIEW`, `APPROVED_URUS_SETIA`, `REJECTED_URUS_SETIA` dikekalkan dalam enum (tiada rekod menggunakannya) untuk keserasian, tetapi tidak digunakan.
- Pendekatan alternatif lebih fleksibel: tukar `status` kepada `varchar(40)` + PHP enum `ApplicationStatus` (seperti `item_category` dalam `2026_10_07_000007`). Kesan: tiada kehilangan data; `down()` perlu menyemak nilai di luar senarai lama.
- Nota: SQLite (ujian) tidak menguatkuasakan enum — ujian sedia ada tidak akan mengesan nilai status yang salah; disyorkan pemalar/enum PHP sebagai satu sumber kebenaran.

**(c) `create_application_reviews_table`** (sejarah semakan & keputusan)
- Kolum: `id`, `uuid`, `application_id` FK, `stage` (`URUS_SETIA`, `PRE_JTICT`, `JTICT`), `decision` (string), `remarks` text, `meeting_id` FK nullable, `decided_by` FK users, `decided_at`, timestamps.
- Sebab: keperluan "sejarah semakan terdahulu dikekalkan" tidak dapat dipenuhi oleh satu kolum `review_remarks`.
- Kesan: jadual baharu sahaja; rekod lama tiada sejarah (semua masih `SUBMITTED`/`DRAFT`). `applications.review_remarks`/`jtict_remarks` boleh dikekalkan sebagai cache nilai terkini.

**(d) `add_copied_from_application_id_to_applications_table`**
- `foreignId('copied_from_application_id')->nullable()->constrained('applications')->nullOnDelete()`.
- Kesan: semua rekod sedia ada `null`.

**(e) Pilihan:** indeks `applications(status)`; `created_on_behalf_by` FK users nullable.

Semua migration bersifat tambahan, tidak memusnahkan data, dan **tiada pengiraan semula rekod lama**. Disyorkan sandaran DB sebelum dijalankan (seperti amalan `db-backup-sebelum-*.sql` sedia ada).

---

## 17. Ujian yang Dijalankan

**Arahan:** `php artisan test --compact` (SQLite `:memory:`, mailer `array`; DB pembangunan tidak disentuh)

**Keputusan:** **385 lulus**, 0 gagal, 2 risky, 2672 assertion, ~173 saat.

Tiada kegagalan sedia ada untuk dilaporkan. Dua ujian "risky" (tiada assertion atau output tidak dijangka) tidak dikenal pasti secara individu dalam output ringkas — perlu disemak dalam fasa seterusnya.

### Liputan ujian mengikut keperluan

| Keperluan | Ujian sedia ada |
|---|---|
| Borang lima fasa | `ApplicationFormTest` (1 ujian sahaja — navigasi Fasa 1→2) |
| Simpan draf | `ApplicationDocumentTest`, `JtictSubmissionWindowTest`, `PolicyDecisionsTest::test_urus_setia_can_save_a_draft_application` |
| Penghantaran | `JtictSubmissionWindowTest`, `ApplicationDocumentTest::test_submitting_a_draft_updates_the_same_application` |
| Pemilihan agensi oleh Urus Setia | **Tiada** (fungsi belum wujud); `test_application_is_not_created_without_valid_active_agency` menguji agensi pengguna |
| Mesyuarat JTICT aktif | `JtictSubmissionWindowTest` (8) |
| Perincian manual | `ApplicationMasterDataIntegrationTest`, `ApplicationCostSummaryTest` (separa) |
| CSV | **Tiada** |
| SST & jumlah kos | `ApplicationCostSummaryTest` (6) |
| Dokumen | `ApplicationDocumentTest` (12) |
| Pegawai | `ApplicationOfficerTest` (14) |
| Semakan Lengkap/Tidak Lengkap | **Tiada** (hanya `test_needs_amendment_resubmits_to_original_meeting_even_when_closed` dengan status ditetapkan terus) |
| Pra JTICT | **Tiada** |
| JTICT | **Tiada** |
| E-mel keputusan | **Tiada** (hanya pertukaran pegawai dan makluman mesyuarat) |
| Salin Kaji Semula | **Tiada** |
| Kawalan akses & pemilikan | `ApplicationDocumentTest`, `PolicyDecisionsTest`, `SecurityHardeningTest`, `ApplicationMasterDataIntegrationTest::test_other_users_still_cannot_edit_the_application` |

---

## 18. Senarai Isu Mengikut Keutamaan

### Kritikal

| # | Isu | Lokasi |
|---|---|---|
| K1 | Semakan Urus Setia, keputusan Pra JTICT dan keputusan JTICT **tidak wujud** — aliran kerja terhenti pada `SUBMITTED` | `ApplicationList`, `ViewApplication`, `ApplicationPolicy` |
| K2 | Urus Setia **tidak boleh memilih agensi sasaran**; permohonan oleh Urus Setia direkod di bawah agensi Urus Setia dan calon pegawai daripada agensi Urus Setia | `ApplicationSubmissionService::resolveApplication()`, `applicantAgencyId()`, `ApplicationForm::officerCandidates()` |

### Tinggi

| # | Isu | Lokasi |
|---|---|---|
| T1 | Status paparan (`APPROVED`, `REJECTED`, `IN_REVIEW`) tidak wujud dalam enum DB; penapis/kad sentiasa kosong | `application-list.blade.php`, `dashboard/urus-setia.blade.php` |
| T2 | Tiada model status yang ditakrif secara berpusat (rentetan bertaburan; enum DB mengandungi `APPROVED_URUS_SETIA`/`REJECTED_URUS_SETIA` yang bercanggah dengan peranan Urus Setia) | `applications.status`, `Application::EDITABLE_STATUSES` |
| T3 | Tiada e-mel kepada pemohon untuk Tidak Lengkap / Lulus / Tidak Lulus / Kaji Semula | `app/Notifications` |
| T4 | Ulasan semakan (`review_remarks`) tidak dipaparkan kepada pemohon; tiada sejarah semakan | `view-application.blade.php`, borang |
| T5 | Templat & import CSV belum wujud | Fasa 3 |
| T6 | SST sentiasa dikenakan; tiada checkbox; kadar boleh diubah melalui `.env` | `Forms\ApplicationForm::costSummary()`, `config/sppict.php` |
| T7 | Fungsi salin Kaji Semula belum wujud | — |

### Sederhana

| # | Isu | Lokasi |
|---|---|---|
| S1 | Draf Urus Setia tidak dipaparkan dalam senarai Urus Setia (hanya `SUBMITTED`) | `ApplicationList::render()` |
| S2 | Pengguna seagensi boleh melihat draf dan dokumen draf rakan sekerja melalui URL | `ApplicationPolicy::view()` |
| S3 | Mesej pengecualian mentah dipaparkan kepada pengguna | `ApplicationForm::saveDraft()`, `submitApplication()` |
| S4 | Draf tidak disahkan — nilai negatif/limpahan boleh menyebabkan ralat SQL | `saveDraft()`, `syncDetails()` |
| S5 | `ResponsibleOfficerChanged` dihantar secara segerak; kegagalan SMTP boleh menggagalkan transaksi pertukaran pegawai (perlu disahkan) | `OfficerHandoverService::notify()` |
| S6 | E-mel self-register / notifikasi Urus Setia (Modul Pengguna) belum diuji dan tiada kelas notifikasi ditemui — **isu berasingan** | Modul Pengurusan Pengguna |
| S7 | `total_cost` item dikira dengan float (`$qty * $unitCost`) berbeza daripada pengiraan sen dalam `costSummary()` | `ApplicationSubmissionService::syncDetails()` |
| S8 | Liputan ujian borang lemah (`ApplicationFormTest` hanya 1 ujian) | `tests/Feature/ApplicationFormTest.php` |

### Rendah

| # | Isu | Lokasi |
|---|---|---|
| R1 | Medan "Rujukan Kelulusan Peruntukan" disimpan dalam kolum `outcome_code` (nama mengelirukan); label hanya "Rujukan Kelulusan" | Fasa 2 |
| R2 | Label "Perlu Pembetulan" vs istilah keperluan "Tidak Lengkap" | paparan |
| R3 | Kolum warisan tidak digunakan (`primary_officer_*`, `secondary_officer_*`, `jpict_*`) | `applications` |
| R4 | Data mesyuarat id 1: tarikh tutup sebelum tarikh buka | `meetings` |
| R5 | `syncDetails()` soft-delete dan cipta semula semua perincian setiap simpanan — bilangan rekod soft-deleted membesar (102/130) | `ApplicationSubmissionService::syncDetails()` |
| R6 | Tiada indeks pada `applications.status` | skema |
| R7 | `maatwebsite/excel` dipasang tetapi tidak digunakan | `composer.json` |
| R8 | 2 ujian risky belum dikenal pasti | suite ujian |

---

## 19. Keputusan yang Memerlukan Pengesahan

1. **Model status:** Status tunggal yang menggambarkan peringkat penuh (`COMPLETE`, `PRE_JTICT_ACCEPTED`, `JTICT_REVIEW`, …) **atau** status peringkat + kolum keputusan berasingan (`jtict_decision` sedia ada)? Dan adakah kolum `status` kekal ENUM atau ditukar kepada VARCHAR + PHP enum?
2. **Urus Setia bagi pihak agensi:** Siapakah "pemohon" (penerima e-mel, pihak yang membetulkan apabila Tidak Lengkap) — Urus Setia, Pegawai Utama, atau kedua-duanya? Bolehkah agensi diubah selepas draf disimpan?
3. **Penglihatan draf seagensi:** Adakah pengguna seagensi dibenarkan melihat draf rakan sekerja?
4. **CSV:** Ganti vs Tambah; CSV sahaja atau juga XLSX; had baris dan had nilai; kod atau nama kategori dalam templat.
5. **SST:** Nilai lalai checkbox untuk permohonan baharu; adakah kadar 8% ditetapkan dalam kod (tidak boleh diubah melalui `.env`); `sst_rate` disimpan sebagai 0 atau null apabila tidak dikenakan.
6. **Slaid Pembentangan:** wajib atau pilihan sebelum hantar?
7. **Tidak Lengkap:** Dihantar semula ke mesyuarat asal walaupun telah lepas (tingkah laku semasa) atau ke mesyuarat aktif semasa? Had bilangan kali dikembalikan?
8. **Pra JTICT:** Direkod sebagai mesyuarat (tarikh/bilangan) atau keputusan + tarikh sahaja? E-mel untuk Ditolak?
9. **Keputusan JTICT:** Bolehkah keputusan diubah selepas direkodkan? Siapa yang boleh mengubah? Adakah keputusan hanya boleh dibuat selepas tarikh mesyuarat?
10. **E-mel:** Penerima — pemohon sahaja, atau termasuk Pegawai Utama/Kedua dan Urus Setia?
11. **Salin:** Siapa boleh menyalin (pemohon asal / pegawai / Urus Setia)? Satu kali atau berkali-kali? Master data tidak aktif dikosongkan atau dikekalkan?
12. **Keputusan Ditolak Pra JTICT / Tidak Lulus JTICT:** Status akhir — boleh disalin atau tidak? (Prompt hanya membenarkan salin bagi Kaji Semula.)

---

## 20. Cadangan Urutan Pelaksanaan PP1 hingga PP8

| Fasa | Skop | Kebergantungan | Migration |
|---|---|---|---|
| **PP1** | Model status & aliran kerja: PHP enum `ApplicationStatus` sebagai satu sumber kebenaran, betulkan label & penapis paparan (T1, T2, R2), migration status + jadual `application_reviews`, polisi keputusan asas | Keputusan #1 | ✔ |
| **PP2** | Urus Setia memilih agensi sasaran (K2) + senarai draf Urus Setia (S1) + pengesahan pelayan | Keputusan #2, #3 | Pilihan |
| **PP3** | Checkbox SST 8% (T6) + penyatuan pengiraan sen untuk item (S7) | Keputusan #5 | ✔ `sst_applicable` |
| **PP4** | Templat & import CSV (T5) menggunakan `form.details` + `validateStep3()` sedia ada | PP3 (pengiraan muktamad) | — |
| **PP5** | Semakan Urus Setia Lengkap / Tidak Lengkap + paparan ulasan + e-mel Tidak Lengkap (K1, T3, T4) | PP1 | — |
| **PP6** | Keputusan Pra JTICT Diterima / Ditolak | PP1, PP5 | — (jika PP1 meliputi) |
| **PP7** | Keputusan Mesyuarat JTICT + e-mel Lulus / Tidak Lulus / Kaji Semula | PP1, PP6 | — |
| **PP8** | Salin permohonan Kaji Semula | PP3, PP7 | ✔ `copied_from_application_id` |

Isu sederhana/rendah (S2–S5, S8, R1–R8) boleh diselitkan dalam fasa yang menyentuh fail sama, atau satu fasa pengerasan selepas PP8.

---

## 21. Pengesahan Had PP0

Saya mengesahkan bahawa sepanjang audit PP0:

- **Tiada** kod aplikasi diubah, ditambah atau dipadam.
- **Tiada** migration dicipta atau dijalankan (`migrate:status` dibaca sahaja).
- **Tiada** rekod pangkalan data diubah atau dipadam; tiada seeder dijalankan. Semakan DB menggunakan `SHOW COLUMNS`, `SHOW INDEX` dan `SELECT` sahaja melalui skrip sementara di luar repositori (folder scratchpad sesi).
- **Tiada** `migrate:fresh`, `migrate:refresh`, `db:wipe` atau rollback dijalankan. Ujian automatik menggunakan SQLite `:memory:`.
- Fail `.env` **tidak** diubah (hanya beberapa kunci bukan rahsia dibaca: `APP_ENV`, `DB_CONNECTION`, `DB_DATABASE`, `MAIL_MAILER`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`).
- **Tiada** commit, push, merge, reset, stash atau pertukaran branch. Perubahan sedia ada yang belum di-commit dikekalkan (`git status` sama seperti sebelum audit).
- Satu-satunya fail baharu dalam repositori ialah laporan ini: `docs/Laporan Audit Fasa PP0 - Modul Permohonan Projek.md`.
- PP1 **tidak** dimulakan.
