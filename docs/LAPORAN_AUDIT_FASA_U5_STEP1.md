# LAPORAN AUDIT FASA U5 — STEP 1 (AUDIT SAHAJA)

**Projek:** SPPICT v2 — Integrasi Master Data Dengan Modul Permohonan Projek
**Tarikh:** 7 Oktober 2026
**Branch:** `feature/application-module`
**Status:** Audit selesai. Menunggu kelulusan sebelum Step 3 (implementasi).

Tiada fail diubah dan tiada data disentuh. Semua semakan pangkalan data hanya membaca data.

---

## 0. Keadaan Data Semasa

| Semakan | Keputusan |
|---|---|
| `applications` | 4 rekod (3 SUBMITTED, 1 DRAFT) |
| `application_details` | 92 rekod |
| `audits` | 122 |
| Rekod dengan snapshot tetapi FK NULL (dicipta selepas U4) | 0 / 0 / 0 |
| Snapshot berbeza daripada nama master semasa | 0 / 0 / 0 |
| `application_categories` | 5 aktif, 0 tidak aktif, 0 dipadam |
| `project_categories` | 6 aktif, 0 tidak aktif, 0 dipadam |
| `procurement_types` | 4 aktif, 0 tidak aktif, 0 dipadam |
| `procurement_methods` | 3 aktif, 0 tidak aktif, 0 dipadam |

---

## 1–2. Fail Terlibat dan Fungsinya

| Fail | Fungsi | Perlu diubah |
|---|---|---|
| `app/Livewire/Forms/ApplicationForm.php` | State dan validation borang (Fasa 1–5) | Ya |
| `app/Livewire/ApplicationForm.php` | `mount` (isi semula semasa edit), `addCategoryGroup`, `submitApplication`, `saveDraft` | Ya |
| `app/Services/ApplicationSubmissionService.php` | `fillApplication()` dan `syncDetails()` menyimpan data | Ya |
| `resources/views/livewire/application-form/phase-1-project-details.blade.php` | Dropdown Kategori Permohonan (enum) | Ya |
| `resources/views/livewire/application-form/phase-2-financial-details.blade.php` | Dropdown Jenis dan Kaedah Perolehan (hard code) | Ya |
| `resources/views/livewire/application-form/phase-3-item-details.blade.php` | Dropdown Kategori Projek (hard code A–F) | Ya |
| `resources/views/livewire/view-application.blade.php` | Paparan permohonan | Ya |
| `resources/views/livewire/application-list.blade.php` | Senarai permohonan (lajur Kategori) | Ya |
| `app/Livewire/ViewApplication.php` | Query paparan. Perlu eager load relationship | Ya (minimum) |
| `app/Livewire/ApplicationList.php` | Query senarai. Perlu eager load relationship | Ya (minimum) |
| `app/Models/Application.php` | Cast enum dan relationship | Ya |
| `app/Enums/ApplicationCategory.php` | Enum | Dipadam selepas semua penggunaan diganti |
| `phase-5-review-submit`, partials, JS, Notifications, Policies, Controllers | Tiada penggunaan | Tidak |

---

## 3. Hard Code Ditemui (kod produksi)

| Lokasi | Nilai |
|---|---|
| `app/Livewire/Forms/ApplicationForm.php:21-22` | Nilai lalai `'Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)'`, `'Sebut Harga'` |
| `app/Livewire/ApplicationForm.php:106` | `'item_category' => 'Projek Baharu'` (kumpulan baharu dalam `addCategoryGroup()`) |
| `app/Services/ApplicationSubmissionService.php:235` | Fallback `?? 'Projek Baharu'` |
| `phase-2-financial-details.blade.php:9-18` | 7 `<option>` (4 Jenis Perolehan, 3 Kaedah Perolehan) |
| `phase-3-item-details.blade.php:88-93` | 6 `<option>`. Masih menulis label lama `Peluasan Sistem/Projek` dan `Penyelenggaraan` |
| `app/Enums/ApplicationCategory.php` | 5 kes dan label |

---

## 4. Validation Yang Perlu Ditukar

| Medan | Semasa | Cadangan |
|---|---|---|
| `application_category` | `Rule::enum(ApplicationCategory::class)` | `Rule::exists('application_categories', 'code')`, aktif sahaja (kecuali nilai semasa rekod) |
| `procurement_type` | `required\|string` | `procurement_type_id`: `Rule::exists('procurement_types', 'id')`, aktif sahaja (kecuali nilai semasa rekod) |
| `procurement_method` | `required\|string` | `procurement_method_id`: `Rule::exists('procurement_methods', 'id')`, aktif sahaja (kecuali nilai semasa rekod) |
| `details.*.item_category` | `required\|string` | `details.*.project_category_id`: `Rule::exists('project_categories', 'id')`, aktif sahaja (kecuali nilai semasa rekod) |

Tiada `Rule::in` atau `in:` digunakan untuk master data ini. `Rule::in` yang ditemui berkaitan modul lain dan tidak disentuh:

- `primary_officer_id` / `secondary_officer_id` (Pegawai Bertanggungjawab)
- `MeetingForm` (status aktif mesyuarat)
- `AgencyManagement` (kategori agensi)

---

## 5. Dropdown Yang Perlu Ditukar

| Modul | Sumber baharu | Query | Nilai `<option>` |
|---|---|---|---|
| Kategori Permohonan | `application_categories` | `ApplicationCategory::active()->ordered()` | `code` |
| Kategori Projek | `project_categories` | `ProjectCategory::active()->ordered()` | `id`. Label `label()`, contoh "A. PROJEK BAHARU" |
| Jenis Perolehan | `procurement_types` | `ProcurementType::active()->ordered()` | `id` |
| Kaedah Perolehan | `procurement_methods` | `ProcurementMethod::active()->ordered()` | `id` |

---

## 6. Service Yang Perlu Ditukar

`ApplicationSubmissionService`:

- **`fillApplication()`**
  - Simpan `procurement_type_id` dan `procurement_method_id`.
  - Simpan snapshot `procurement_type` dan `procurement_method` daripada `name` master yang dipilih.
  - `application_category` kekal sebagai `code`.
- **`syncDetails()`**
  - Simpan `project_category_id`.
  - Simpan snapshot `item_category` daripada `name` master yang dipilih.
  - Buang fallback `'Projek Baharu'`.
- **Mencari master:** melalui ID dengan `withTrashed()`. Jika ID tidak wujud (hanya boleh berlaku jika input diubah secara manual), service gagal dengan mesej yang jelas, bukan menggunakan fallback.
- Tiada nama master data ditulis terus dalam service.

---

## 7. Model dan Relationship

### 7.1 Sudah tersedia (U2)

| Model | Relationship / Ciri |
|---|---|
| `Application` | `applicationCategoryMaster()` (melalui `code`), `procurementType()`, `procurementMethod()`, semua `withTrashed()` |
| `ApplicationDetail` | `projectCategory()` (`withTrashed()`) |
| `Application`, `ApplicationDetail` | `$fillable` untuk lajur FK |
| Empat model master | Scope `active()` dan `ordered()`. `ProjectCategory::label()` dan `prefix()` |

### 7.2 Masih perlu

- Buang cast enum `application_category` dalam `Application`.
- Tambah kaedah label paparan dengan fallback snapshot (contoh `procurementTypeLabel()`).
- Eager loading dalam `ViewApplication` dan `ApplicationList` untuk mengelakkan N+1 query.

---

## 8. Penggunaan Enum `ApplicationCategory`

| Lokasi | Jenis |
|---|---|
| `app/Models/Application.php:9, 114` | Cast (produksi) |
| `app/Livewire/Forms/ApplicationForm.php:5, 48` | Validation (produksi) |
| `phase-1-project-details.blade.php:11` | Dropdown (produksi) |
| `app/Livewire/ApplicationForm.php:52` | `?->value` (produksi) |
| `view-application.blade.php:25` | `?->label()` (produksi) |
| `application-list.blade.php:45` | `?->label()` (produksi) |
| `tests/Feature/ApplicationFormTest.php:5, 17` | Ujian |
| Komen dalam `app/Models/ApplicationCategory.php` dan `database/seeders/ApplicationMasterDataSeeder.php` | Dokumentasi |

Selepas semua penggunaan di atas diganti, tiada lagi penggunaan yang sah. **Cadangan: fail enum dipadam.**

---

## 9. Ujian Yang Terjejas

| Ujian | Kesan | Tindakan |
|---|---|---|
| `ApplicationFormTest` | Tiada `RefreshDatabase`. Selepas U5, borang membaca master data daripada DB. Menggunakan enum. | Tambah `RefreshDatabase` dan seeder. Tukar kepada `code` master |
| `ApplicationCostSummaryTest` | Tiada `RefreshDatabase`. Menggunakan `item_category` teks | Tambah `RefreshDatabase` dan seeder. Tukar kepada `project_category_id` |
| `ApplicationDocumentTest` | `item_category` teks | Seed master data. Tukar kepada ID master |
| `ApplicationOfficerTest` | `item_category` teks | Seed master data. Tukar kepada ID master |
| `JtictSubmissionWindowTest` | `procurement_type`, `procurement_method` dan `item_category` teks | Seed master data. Tukar kepada ID master |
| `ApplicationMasterDataTest` (U3) | Fixture teks yang sah | Kekal |
| `ApplicationMasterDataMigrationTest` (U4) | Fixture teks yang sah | Kekal |

---

## 10. Cadangan Perubahan Minimum Untuk U5

1. **Form object:**
   - `procurement_type` → `procurement_type_id`
   - `procurement_method` → `procurement_method_id`
   - `details.*.item_category` → `details.*.project_category_id`
   - `application_category` kekal sebagai `code`
2. **Pilihan dropdown** = master aktif + nilai semasa rekod yang sedang diedit (walaupun tidak aktif atau dipadam), dengan tanda "(Tidak Aktif)".
3. **Validation** menerima master aktif **atau** nilai asal rekod itu sahaja. Nilai tidak aktif tidak boleh dipilih untuk rekod lain atau untuk kumpulan baharu.
4. **Nilai lalai rekod baharu** = master aktif pertama mengikut `sort_order`. Ini mengekalkan tingkah laku semasa (One-Off, Sebut Harga, Projek Baharu) tanpa hard code.
5. **Edit:** item dikumpul semula mengikut `project_category_id`, bukan label teks.
6. **Paparan:**
   - Guna relationship jika master aktif.
   - Jika master tidak aktif, dipadam atau tiada, guna snapshot.
   - Untuk Kategori Permohonan, guna nama master, kemudian `code` jika master tiada (tiada snapshot nama).
7. Buang cast enum dan fail enum.
8. Kemaskini lima fail ujian dan tambah ujian U5.
9. **Tiada perubahan schema.** Struktur U2/U4 sudah mencukupi.
10. `isInUse()` (U3) tidak diubah. Semakan teks di dalamnya masih berguna sebagai lapisan keselamatan.

### 10.1 Pemetaan Sumber Baharu

| Modul | Sumber baharu | Disimpan dalam transaksi |
|---|---|---|
| Kategori Permohonan | `application_categories` | `applications.application_category` (code) |
| Kategori Projek | `project_categories` | `application_details.project_category_id` + snapshot `item_category` |
| Jenis Perolehan | `procurement_types` | `applications.procurement_type_id` + snapshot `procurement_type` |
| Kaedah Perolehan | `procurement_methods` | `applications.procurement_method_id` + snapshot `procurement_method` |

### 10.2 Kes Khas Penyelenggaraan

Selepas U5, nilai disimpan berdasarkan ID master, jadi dua konteks tidak boleh tertukar:

| Konteks | Master code | Snapshot |
|---|---|---|
| Kategori Projek (`application_details`) | `penyelenggaraan_sewaan_langganan` | `Penyelenggaraan / Sewaan / Langganan` |
| Jenis Perolehan (`applications`) | `penyelenggaraan` | `Penyelenggaraan` |

Kedua-dua konteks akan diuji secara automatik.

### 10.3 Dikekalkan dan Sebabnya

| Item | Sebab |
|---|---|
| `ApplicationMasterDataSeeder` | Sumber data awal yang sah |
| Migration U2/U4 | Sejarah. Tidak boleh dipadam |
| Fixture ujian U3/U4 | Menguji pemetaan teks lama |
| `docs/` | Dokumentasi |
| Snapshot dalam DB dan audit | Data sejarah |

---

## 11. Perkara Untuk Diputuskan

1. **Draf dengan nilai yang diubah secara manual.** Sebelum ini draf disimpan tanpa sebarang validation. Cadangan: service menolak ID master yang tidak wujud. Ini mengubah tingkah laku draf sedikit, kerana sebelum ini apa-apa teks diterima.
2. **Paparan rekod dengan master tidak aktif.** Cadangan mengikut arahan bahagian 6 prompt U5: tunjuk snapshot jika master tidak aktif atau dipadam. Akibatnya, jika Urus Setia menukar nama master yang **aktif**, rekod lama akan memaparkan nama baharu kerana relationship diutamakan.

---

*Audit Fasa U5 tamat. Menunggu kelulusan sebelum melaksanakan Step 3.*
