# LAPORAN FASA U1 — AUDIT HARD CODE

**Projek:** SPPICT v2 — Master Data Urus Setia (Modul Permohonan Projek)
**Tarikh:** 7 Oktober 2026
**Branch:** `feature/application-module`
**Status:** BERJAYA DENGAN CATATAN

Audit ini hanya membaca kod dan data. Tiada fail kod, migration atau data diubah.

---

## 1. Objektif

Mengenal pasti semua nilai hard code bagi empat kategori master data, iaitu:

- Kategori Permohonan
- Kategori Projek
- Jenis Perolehan
- Kaedah Perolehan

Bagi setiap kategori, audit ini menentukan cara data disimpan, data sedia ada yang terlibat dan risiko migrasi ke master data berasaskan pangkalan data.

---

## 2. Penemuan Utama

Setiap kategori disimpan dengan cara yang **berbeza**:

| Kategori | Medan DB | Jenis Lajur | Nilai Disimpan | Sumber Pilihan |
|---|---|---|---|---|
| Kategori Permohonan | `applications.application_category` | `varchar(255)` nullable | **Kod** (`pembangunan_sistem`, …) | PHP Enum `App\Enums\ApplicationCategory` |
| Kategori Projek | `application_details.item_category` | **MySQL `ENUM(...)`** NOT NULL | **Label** (`Projek Baharu`, …) | `<option>` dalam Blade + ENUM dalam migration |
| Jenis Perolehan | `applications.procurement_type` | `varchar(255)` nullable | **Label** | `<option>` dalam Blade |
| Kaedah Perolehan | `applications.procurement_method` | `varchar(255)` nullable | **Label** | `<option>` dalam Blade |

### Catatan Penting

1. **Dua label Kategori Projek dalam kod berbeza daripada spesifikasi:**

   | Kod / DB semasa | Spesifikasi |
   |---|---|
   | `Peluasan Sistem/Projek` | `Peluasan Sistem / Projek` |
   | `Penyelenggaraan` | `Penyelenggaraan / Sewaan / Langganan` |

   Seeder mesti mengikut salah satu. Jika label diubah, ia dikira sebagai penamaan semula nilai sedia ada, bukan nilai baharu.

2. **`item_category` ialah lajur MySQL ENUM.** Selagi lajur ini tidak ditukar, sebarang Kategori Projek baharu yang ditambah oleh Urus Setia akan **gagal semasa disimpan**. Kegagalan berlaku di peringkat DB kerana pengesahan aplikasi hanya `required|string`.

3. **Nilai `'Penyelenggaraan'` digunakan oleh dua kategori**, iaitu Jenis Perolehan dan Kategori Projek. Carian dan ganti secara global tidak selamat.

---

## 3. Fail Dicipta

Tiada.

## 4. Fail Diubah

Tiada.

## 5. Perubahan Pangkalan Data

Tiada.

---

## 6. Senarai Hard Code Yang Ditemui

### 6.1 Kategori Permohonan

| Fail | Lokasi | Tujuan | Risiko |
|---|---|---|---|
| `app/Enums/ApplicationCategory.php` | Baris 12–26: 5 `case` + `label()` | Sumber kod dan label | Sederhana |
| `app/Models/Application.php` | Baris 112: `$casts` → enum | Cast ke enum. Jika ada nilai yang tidak dikenali, ralat `ValueError` berlaku semasa model dibaca | **Tinggi** |
| `app/Livewire/Forms/ApplicationForm.php` | Baris 48: `Rule::enum(...)` | Pengesahan | Sederhana |
| `resources/views/livewire/application-form/phase-1-project-details.blade.php` | Baris 11: `ApplicationCategory::options()` | Dropdown | Rendah |
| `app/Livewire/ApplicationForm.php` | Baris 52: `?->value` | Isi semula semasa edit | Rendah |
| `resources/views/livewire/view-application.blade.php` | Baris 25: `?->label()` | Paparan | Rendah |
| `resources/views/livewire/application-list.blade.php` | Baris 45: `?->label()` | Paparan | Rendah |
| `database/migrations/2026_10_02_000001_rename_project_category_to_application_category_on_applications_table.php` | Rename `project_category` → `application_category` dan set kepada null | Sejarah. Sudah dijalankan | Tiada |

**Nilai:**

| Kod | Label |
|---|---|
| `pembangunan_sistem` | Pembangunan Sistem / Aplikasi |
| `perolehan_perkakasan` | Perolehan Perkakasan / Komputer |
| `rangkaian_keselamatan_siber` | Rangkaian & Keselamatan Siber |
| `penyelenggaraan_sewaan_langganan` | Penyelenggaraan, Sewaan dan Langganan |
| `kajian_perunding` | Kajian/Khidmat Perunding ICT |

### 6.2 Kategori Projek

| Fail | Lokasi | Tujuan | Risiko |
|---|---|---|---|
| `database/migrations/2026_08_04_000001_create_sppict_core_tables.php` | Baris 131–138: `enum('item_category', [6 nilai])` | Kekangan DB | **Tinggi** |
| `resources/views/livewire/application-form/phase-3-item-details.blade.php` | Baris 88–93: 6 `<option>`, label paparan berprefix A–F dan huruf besar | Dropdown, susunan tetap | Sederhana |
| `app/Livewire/ApplicationForm.php` | Baris 106: default `'Projek Baharu'` dalam `addCategoryGroup()` | Nilai lalai | Sederhana |
| `app/Services/ApplicationSubmissionService.php` | Baris 235: fallback `?? 'Projek Baharu'` | Simpan detail | Sederhana |
| `app/Livewire/ApplicationForm.php` | Baris 73–83: `groupBy('item_category')` | Bina semula kumpulan item semasa edit, dikumpul ikut label | Sederhana |
| `app/Livewire/Forms/ApplicationForm.php` | Baris 84: `required\|string` sahaja | Pengesahan tidak menyemak senarai nilai yang sah | Sederhana |
| `resources/views/livewire/view-application.blade.php` | Baris 85: `$detail->item_category` | Paparan | Rendah |

**Nilai (ikut susunan dropdown):**

| Prefix | Nilai disimpan | Label paparan |
|---|---|---|
| A | Projek Baharu | A. PROJEK BAHARU |
| B | Peningkatan Sistem | B. PENINGKATAN SISTEM |
| C | Peluasan Sistem/Projek | C. PELUASAN SISTEM / PROJEK |
| D | Penambahbaikan Peralatan | D. PENAMBAHBAIKAN PERALATAN |
| E | Penyelenggaraan | E. PENYELENGGARAAN |
| F | Khidmat Perunding ICT | F. KHIDMAT PERUNDING ICT |

### 6.3 Jenis Perolehan dan Kaedah Perolehan

| Fail | Lokasi | Tujuan | Risiko |
|---|---|---|---|
| `resources/views/livewire/application-form/phase-2-financial-details.blade.php` | Baris 9–12: 4 `<option>` Jenis Perolehan | Dropdown | Rendah |
| `resources/views/livewire/application-form/phase-2-financial-details.blade.php` | Baris 16–18: 3 `<option>` Kaedah Perolehan | Dropdown | Rendah |
| `app/Livewire/Forms/ApplicationForm.php` | Baris 21–22: default `'Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)'` dan `'Sebut Harga'` | Nilai lalai | Sederhana |
| `app/Livewire/Forms/ApplicationForm.php` | Baris 65–66: `required\|string` | Pengesahan tidak menyemak senarai nilai yang sah | Sederhana |
| `app/Services/ApplicationSubmissionService.php` | Baris 165–166 | Simpan label | Rendah |
| `app/Livewire/ApplicationForm.php` | Baris 56–57 | Isi semula semasa edit | Rendah |
| `resources/views/livewire/view-application.blade.php` | Baris 42 dan 46 | Paparan | Rendah |

**Nilai Jenis Perolehan:**

- Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)
- Pembekalan / Perkhidmatan Bermasa
- Penyelenggaraan
- Pembangunan Aplikasi

**Nilai Kaedah Perolehan:**

- Sebut Harga
- Tender Terbuka
- Rundingan Terus

### 6.4 Ujian Yang Menggunakan Nilai Hard Code

- `tests/Feature/ApplicationFormTest.php`
- `tests/Feature/ApplicationCostSummaryTest.php`
- `tests/Feature/ApplicationDocumentTest.php`
- `tests/Feature/ApplicationOfficerTest.php`
- `tests/Feature/JtictSubmissionWindowTest.php`

Ujian berjalan atas SQLite (`:memory:`). Dalam SQLite, ENUM menjadi CHECK constraint.

### 6.5 Tiada Penggunaan Dalam

- workflow atau syarat `if`
- query `where`
- penapis senarai
- dashboard
- laporan
- notifikasi
- controller
- policy
- JavaScript / Alpine.js
- route
- seeder

Laporan "mengikut Kategori Projek" hanya disebut dalam `docs/PRD_SPPICT_v2.md` (baris 178) dan belum dibangunkan.

---

## 7. Kesan Kepada Data Sedia Ada

Pangkalan data: `sppictv2_db` (MySQL)

### 7.1 Jadual `applications` — 4 rekod

Status: 3 SUBMITTED, 1 DRAFT. Tiada rekod soft-deleted.

| Medan | Nilai | Bilangan |
|---|---|---|
| `application_category` | NULL | 2 |
| | `pembangunan_sistem` | 1 |
| | `perolehan_perkakasan` | 1 |
| `procurement_type` | Pembekalan / Perkhidmatan Tidak Bermasa (One-Off) | 2 |
| | Pembekalan / Perkhidmatan Bermasa | 1 |
| | Pembangunan Aplikasi | 1 |
| `procurement_method` | Sebut Harga | 4 |

### 7.2 Jadual `application_details` — 92 rekod

Daripada jumlah ini, **68 rekod soft-deleted**. Ini kerana `syncDetails()` soft-delete dan cipta semula detail setiap kali permohonan disimpan.

| `item_category` | Bilangan |
|---|---|
| Projek Baharu | 61 |
| Penambahbaikan Peralatan | 30 |
| Khidmat Perunding ICT | 1 |

### 7.3 Jadual `audits`

| Medan | Bilangan entri |
|---|---|
| `item_category` | ~92 |
| `procurement_type` | 6 |
| `procurement_method` | 5 |
| `application_category` | 3 |

Entri audit menyimpan nilai teks dan **tidak patut diubah**.

### 7.4 Padanan

Semua nilai sedia ada **padan tepat** dengan senarai dalam kod. Tiada nilai yang tidak sepadan.

---

## 8. Strategi Migrasi Dicadangkan

Pendekatan ini hanya menambah struktur baharu dan tidak destruktif.

1. **Pola jadual master data:** ikut `MeetingName`, iaitu:
   - `id`, `uuid`, `code` (unique), `name`, `sort_order`, `is_active`, `timestamps`, `softDeletes`
   - model `Auditable` (OwenIt\Auditing)
   - scope `active()` dan `ordered()`
2. **Kategori Permohonan:**
   - `code` = nilai enum sedia ada (`pembangunan_sistem`, …)
   - lajur `applications.application_category` kekal menyimpan kod, jadi tiada perubahan struktur
   - cast enum diganti dengan relationship `belongsTo(..., 'application_category', 'code')`
3. **Jenis Perolehan, Kaedah Perolehan dan Kategori Projek:**
   - tambah lajur nullable FK `*_id`
   - isi lajur ini dengan padanan nama yang tepat, **termasuk rekod soft-deleted**
   - sahkan bilangan rekod dan nilai yang tidak sepadan
   - lajur teks lama dikekalkan sebagai snapshot untuk paparan rekod lama
4. **`item_category`:** tukar ENUM kepada `varchar(255)`. Ini tidak destruktif dan nilai sedia ada dikekalkan.
5. **Pengesahan:** tukar kepada `Rule::exists(...)->where('is_active', true)` untuk transaksi baharu.
6. **Paparan:** guna relationship dahulu, dan jatuh balik kepada teks lama jika tiada.

---

## 9. Kawalan Akses

Role sebenar dalam sistem ialah **`pentadbir_urus_setia`**.

Pola sedia ada (modul Mesyuarat) yang sesuai digunakan semula:

- route group `middleware('role:pentadbir_urus_setia')`
- kaedah `ensureUrusSetia()` dengan `abort_unless(...)` dalam setiap action Livewire
- dropdown navigation di bawah `@hasrole('pentadbir_urus_setia')`

---

## 10. Ujian

Fasa audit tidak melibatkan ujian. Lima fail ujian dalam bahagian 6.4 perlu dikemas kini pada Fasa U5.

---

## 11. Risiko

| Risiko | Tahap |
|---|---|
| ENUM `item_category` akan menghalang Kategori Projek baharu disimpan | Tinggi |
| Cast enum `application_category` akan menyebabkan ralat jika kod baharu ditambah melalui CRUD tanpa menukar cast | Tinggi |
| Mengedit permohonan lama dengan kategori yang telah dinyahaktifkan: nilai itu perlu kekal dipaparkan dalam dropdown | Sederhana |
| `groupBy('item_category')` bergantung pada label. Jika nama ditukar, rekod lama dan baharu akan berada dalam kumpulan berbeza | Sederhana |
| Nilai `'Penyelenggaraan'` dikongsi oleh dua kategori. Carian dan ganti global tidak selamat | Sederhana |

---

## 12. Perubahan Di Luar Skop

Tiada.

---

## 13. Status Git

- Branch: `feature/application-module`
- Status git sama seperti sebelum audit: 104 entri belum di-commit, kesemuanya sedia ada sebelum audit.
- Tiada fail diubah oleh audit ini.

---

## 14. Keputusan Diperlukan Sebelum Fasa U2

1. **Label Kategori Projek:** kekalkan nilai DB (`Peluasan Sistem/Projek`, `Penyelenggaraan`) atau tukar kepada label spesifikasi? Jika tukar, rekod lama perlu dipetakan.
2. **Kod (`code`)** untuk Jenis Perolehan, Kaedah Perolehan dan Kategori Projek: guna slug seperti Kategori Permohonan (contoh `projek_baharu`), atau tanpa `code`?
3. **Storan:** setuju dengan pendekatan FK `*_id` tambahan berserta lajur teks dikekalkan sebagai snapshot?
4. **Prefix A–F** pada Kategori Projek: jana daripada `sort_order` (dicadangkan)?

---

*Fasa U1 tamat. Menunggu arahan untuk Fasa U2.*
