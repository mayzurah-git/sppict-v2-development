# LAPORAN FASA U2 — DATABASE DESIGN & MODELS

**Projek:** SPPICT v2 — Master Data Urus Setia (Modul Permohonan Projek)
**Tarikh:** 7 Oktober 2026
**Branch:** `feature/application-module`
**Status:** BERJAYA DENGAN CATATAN

Struktur pangkalan data, model dan seeder untuk empat master data telah siap dan dijalankan pada DB pembangunan `sppictv2_db`. Data transaksi lama tidak berubah. Fasa U3, U4 dan U5 belum dimulakan.

---

## 1. Objektif

- Sediakan jadual master data, model, relationship, seeder dan audit trail bagi:
  - Kategori Permohonan
  - Kategori Projek
  - Jenis Perolehan
  - Kaedah Perolehan
- Tukar lajur `application_details.item_category` daripada ENUM kepada VARCHAR supaya Kategori Projek boleh diselenggara secara dinamik.

### Keputusan Fasa U1 yang dilaksanakan

| Perkara | Keputusan |
|---|---|
| Label Kategori Projek | Ikut spesifikasi (`Peluasan Sistem / Projek`, `Penyelenggaraan / Sewaan / Langganan`) |
| `code` | Digunakan untuk keempat-empat master data. Slug stabil dan unik. |
| Storan | FK `*_id` tambahan. Lajur teks lama dikekalkan sebagai snapshot. |
| Kategori Permohonan | `applications.application_category` kekal menyimpan `code` sedia ada |
| Prefix A–F | Dijana daripada `sort_order`, tidak disimpan dalam `name` |

---

## 2. Semakan Semula Sebelum Migration

- Struktur sebenar jadual `applications` dan `application_details` sama dengan dapatan U1.
- Semua migration lama sudah dijalankan, jadi hanya migration U2 yang berjalan.
- SQL disemak dahulu dengan `php artisan migrate --pretend`. Ia hanya mengandungi:
  - `CREATE TABLE`
  - `ADD COLUMN` / `ADD CONSTRAINT`
  - `MODIFY item_category varchar(255) NOT NULL`
- Tiada `DROP`, `DELETE` atau `UPDATE` data.
- MySQL `sql_mode` termasuk `STRICT_TRANS_TABLES`.

---

## 3. Fail Dicipta

| Fail | Tujuan |
|---|---|
| `database/migrations/2026_10_07_000003_create_application_categories_table.php` | Jadual Kategori Permohonan |
| `database/migrations/2026_10_07_000004_create_project_categories_table.php` | Jadual Kategori Projek |
| `database/migrations/2026_10_07_000005_create_procurement_types_table.php` | Jadual Jenis Perolehan |
| `database/migrations/2026_10_07_000006_create_procurement_methods_table.php` | Jadual Kaedah Perolehan |
| `database/migrations/2026_10_07_000007_change_item_category_to_string_on_application_details_table.php` | Tukar ENUM kepada VARCHAR |
| `database/migrations/2026_10_07_000008_add_master_data_foreign_keys_to_application_tables.php` | Tambah FK `*_id` (nullable) |
| `app/Models/ApplicationCategory.php` | Model Kategori Permohonan |
| `app/Models/ProjectCategory.php` | Model Kategori Projek, termasuk `prefix()` dan `label()` |
| `app/Models/ProcurementType.php` | Model Jenis Perolehan |
| `app/Models/ProcurementMethod.php` | Model Kaedah Perolehan |
| `database/seeders/ApplicationMasterDataSeeder.php` | Seeder idempotent |

Semua model mengikut pola `MeetingName` sedia ada:

- `HasUuids`, `SoftDeletes`, `HasFactory`
- `OwenIt\Auditing` (`Auditable`) untuk audit trail create, update dan activate/deactivate
- scope `active()` dan `ordered()` (ikut `sort_order`, kemudian `name`)

---

## 4. Fail Diubah

| Fail | Perubahan |
|---|---|
| `app/Models/Application.php` | Tambah `procurement_type_id` dan `procurement_method_id` ke `$fillable`. Tambah relationship `applicationCategoryMaster()` (padanan melalui `code`), `procurementType()` dan `procurementMethod()`. **Cast enum `application_category` masih dikekalkan** sehingga Fasa U5. |
| `app/Models/ApplicationDetail.php` | Tambah `project_category_id` ke `$fillable` dan relationship `projectCategory()` |
| `database/seeders/DatabaseSeeder.php` | Panggil `ApplicationMasterDataSeeder` |

Semua relationship menggunakan `withTrashed()` supaya rekod lama masih boleh dipaparkan walaupun master datanya telah dipadam (soft delete).

---

## 5. Perubahan Pangkalan Data

### 5.1 Jadual Baharu

Keempat-empat jadual menggunakan struktur yang sama:

| Lajur | Jenis | Catatan |
|---|---|---|
| `id` | bigint unsigned | Primary key |
| `uuid` | char(36) | Unique |
| `code` | varchar(255) | Unique. Identifier stabil |
| `name` | varchar(255) | Label paparan |
| `sort_order` | int unsigned | Default 0 |
| `is_active` | tinyint(1) | Default 1, index |
| `created_at`, `updated_at` | timestamp | |
| `deleted_at` | timestamp | Soft delete |

Jadual:

- `application_categories`
- `project_categories`
- `procurement_types`
- `procurement_methods`

### 5.2 Lajur Yang Berubah atau Ditambah

| Jadual | Lajur | Perubahan |
|---|---|---|
| `application_details` | `item_category` | `ENUM(...)` ditukar kepada `VARCHAR(255) NOT NULL`. Nilai dikekalkan. |
| `application_details` | `project_category_id` | Baharu. Nullable, FK ke `project_categories`, `restrictOnDelete` |
| `applications` | `procurement_type_id` | Baharu. Nullable, FK ke `procurement_types`, `restrictOnDelete` |
| `applications` | `procurement_method_id` | Baharu. Nullable, FK ke `procurement_methods`, `restrictOnDelete` |
| `applications` | `application_category` | Tiada perubahan. Terus menyimpan `code`. |

Lajur teks lama (`item_category`, `procurement_type`, `procurement_method`) **tidak dibuang**.

### 5.3 Keselamatan Rollback

`down()` bagi migration 000007 akan berhenti dengan mesej ralat, bukannya memotong data, jika terdapat nilai `item_category` di luar senarai ENUM lama.

---

## 6. Master Data Yang Di-seed

### 6.1 Kategori Permohonan (`application_categories`)

| sort_order | code | name |
|---|---|---|
| 1 | `pembangunan_sistem` | Pembangunan Sistem / Aplikasi |
| 2 | `perolehan_perkakasan` | Perolehan Perkakasan / Komputer |
| 3 | `rangkaian_keselamatan_siber` | Rangkaian & Keselamatan Siber |
| 4 | `penyelenggaraan_sewaan_langganan` | Penyelenggaraan, Sewaan dan Langganan |
| 5 | `kajian_perunding` | Kajian/Khidmat Perunding ICT |

### 6.2 Kategori Projek (`project_categories`)

| sort_order | Prefix | code | name | Label UI |
|---|---|---|---|---|
| 1 | A | `projek_baharu` | Projek Baharu | A. PROJEK BAHARU |
| 2 | B | `peningkatan_sistem` | Peningkatan Sistem | B. PENINGKATAN SISTEM |
| 3 | C | `peluasan_sistem_projek` | Peluasan Sistem / Projek | C. PELUASAN SISTEM / PROJEK |
| 4 | D | `penambahbaikan_peralatan` | Penambahbaikan Peralatan | D. PENAMBAHBAIKAN PERALATAN |
| 5 | E | `penyelenggaraan_sewaan_langganan` | Penyelenggaraan / Sewaan / Langganan | E. PENYELENGGARAAN / SEWAAN / LANGGANAN |
| 6 | F | `khidmat_perunding_ict` | Khidmat Perunding ICT | F. KHIDMAT PERUNDING ICT |

### 6.3 Jenis Perolehan (`procurement_types`)

| sort_order | code | name |
|---|---|---|
| 1 | `pembekalan_perkhidmatan_one_off` | Pembekalan / Perkhidmatan Tidak Bermasa (One-Off) |
| 2 | `pembekalan_perkhidmatan_bermasa` | Pembekalan / Perkhidmatan Bermasa |
| 3 | `penyelenggaraan` | Penyelenggaraan |
| 4 | `pembangunan_aplikasi` | Pembangunan Aplikasi |

### 6.4 Kaedah Perolehan (`procurement_methods`)

| sort_order | code | name |
|---|---|---|
| 1 | `sebut_harga` | Sebut Harga |
| 2 | `tender_terbuka` | Tender Terbuka |
| 3 | `rundingan_terus` | Rundingan Terus |

### 6.5 Ciri Seeder

- Menggunakan `withTrashed()->firstOrCreate()` mengikut `code`.
- Tidak mencipta duplikasi, termasuk jika rekod telah di-soft delete.
- Tidak menimpa rekod sedia ada, jadi kemaskini oleh Urus Setia kekal apabila seeder dijalankan semula.
- Tidak memadam data dan tidak mengubah data transaksi.

---

## 7. Hard Code

Tiada hard code dibuang pada fasa ini. Dropdown, pengesahan dan nilai lalai masih seperti dalam laporan U1, dan akan diganti pada Fasa U5.

---

## 8. Kesan Kepada Data Sedia Ada (disahkan selepas migration)

| Semakan | Keputusan |
|---|---|
| `applications` | 4 rekod (tidak berubah) |
| `application_details` | 92 rekod, termasuk 68 soft-deleted (tidak berubah) |
| Taburan `item_category` | Projek Baharu 61, Penambahbaikan Peralatan 30, Khidmat Perunding ICT 1 (sama seperti U1) |
| `audits` | Tiada migration atau seeder yang menulis ke jadual ini. Jumlah semasa: 122 entri |
| Lajur FK baharu terisi | 0 (dijangka, kerana pengisian dibuat pada Fasa U4) |
| Seeder dijalankan dua kali | Jumlah kekal 5 / 6 / 4 / 3. Tiada duplikasi |

---

## 9. Pelan Pemetaan Untuk Fasa U4 (belum dilaksanakan)

### 9.1 Jadual Pemetaan

| Lajur lama | Nilai lama | Master data (`code`) |
|---|---|---|
| `application_details.item_category` | Projek Baharu | `projek_baharu` |
| | Peningkatan Sistem | `peningkatan_sistem` |
| | **Peluasan Sistem/Projek** | `peluasan_sistem_projek` (label menjadi "Peluasan Sistem / Projek") |
| | Penambahbaikan Peralatan | `penambahbaikan_peralatan` |
| | **Penyelenggaraan** | `penyelenggaraan_sewaan_langganan` (label menjadi "Penyelenggaraan / Sewaan / Langganan") |
| | Khidmat Perunding ICT | `khidmat_perunding_ict` |
| `applications.procurement_type` | Pembekalan / Perkhidmatan Tidak Bermasa (One-Off) | `pembekalan_perkhidmatan_one_off` |
| | Pembekalan / Perkhidmatan Bermasa | `pembekalan_perkhidmatan_bermasa` |
| | Penyelenggaraan | `penyelenggaraan` (jenis perolehan) |
| | Pembangunan Aplikasi | `pembangunan_aplikasi` |
| `applications.procurement_method` | Sebut Harga | `sebut_harga` |
| | Tender Terbuka | `tender_terbuka` |
| | Rundingan Terus | `rundingan_terus` |
| `applications.application_category` | Kod sedia ada | Padanan terus melalui `code`. Tiada pengisian data diperlukan. |

### 9.2 Cara Pemetaan

- **Dipeta mengikut jadual dan lajur secara berasingan.** Nilai `Penyelenggaraan` dalam `item_category` dan dalam `procurement_type` dipetakan ke master data yang berbeza. Tiada carian dan ganti global.
- **Rekod soft-deleted:** diproses melalui query builder `DB::table()`, yang tidak menapis `deleted_at`. Kesemua 92 rekod detail akan dipetakan.
- **Audit:** pengisian dibuat dengan `DB::table()->update()`, yang tidak mencetuskan event model. Tiada entri audit baharu dicipta dan entri audit lama tidak disentuh.
- **Pengesahan selepas pemetaan:** semak bilangan rekod, dan pastikan tiada nilai yang tidak sepadan.

---

## 10. Kawalan Akses

Belum berkaitan pada fasa ini kerana tiada route atau UI baharu. Akan dilaksanakan pada Fasa U3 menggunakan:

- route middleware `role:pentadbir_urus_setia`
- kaedah `ensureUrusSetia()` dalam setiap action Livewire

---

## 11. Ujian

| Ujian | Keputusan |
|---|---|
| `php artisan test` | **106/107 lulus** (SQLite in-memory) |
| Ujian modul permohonan | Semua lulus selepas ENUM ditukar |
| `ProfileTest::test_user_can_delete_their_account` | Gagal. **Tidak berkaitan dengan U2** (lihat di bawah) |
| Label `A. PROJEK BAHARU` hingga `F. KHIDMAT PERUNDING ICT` | Dijana dengan betul daripada `sort_order` |
| Relationship `applicationCategoryMaster` | Berfungsi melalui eager loading dan lazy loading walaupun lajur masih di-cast ke enum |

**Catatan kegagalan ujian:** `ProfileTest` menjangka pengguna dipadam terus, tetapi model `User` sudah menggunakan `SoftDeletes` dalam perubahan sedia ada yang belum di-commit. Kegagalan ini tidak melibatkan jadual atau model Fasa U2.

---

## 12. Risiko

| Risiko | Tahap | Tindakan |
|---|---|---|
| Sehingga Fasa U5, borang permohonan masih menulis nilai lama (contoh `Penyelenggaraan`) dan tidak mengisi FK | Sederhana | Pengisian Fasa U4 perlu dijalankan semula atau menjadi sebahagian proses U5 |
| Nama relationship `procurementType` dan `procurementMethod` akan bertembung dengan nama lajur teks jika model ditukar ke array (`toArray()`) selepas relationship dimuatkan | Rendah | Pada masa ini tiada kod yang berbuat demikian. Risiko hilang apabila lajur teks dibuang kelak |
| Nama `ApplicationCategory` digunakan oleh model dan enum dalam namespace berbeza | Rendah | Dalam `Application.php`, model dirujuk dengan nama penuh (FQCN). Enum akan dibuang pada Fasa U5 |

---

## 13. Perubahan Di Luar Skop

Tiada. UI Permohonan Projek tidak diubah.

---

## 14. Status Git

- Branch: `feature/application-module`
- Sebelas fail baharu belum dijejak oleh git: 6 migration, 4 model, 1 seeder.
- Tiga fail diubah: `app/Models/Application.php`, `app/Models/ApplicationDetail.php` dan `database/seeders/DatabaseSeeder.php`. Ketiga-tiganya sudah mempunyai perubahan lain yang belum di-commit sebelum fasa ini.
- Belum di-commit.

---

## 15. Cadangan Untuk Fasa Seterusnya (U3 — CRUD Master Data)

- Ikut pola `MeetingNameManagement` untuk komponen Livewire dan kawalan akses.
- Sediakan search, pagination, dan penapis Aktif / Tidak Aktif.
- Semak `name` unik dalam validation.
- Padam (soft delete) hanya dibenarkan jika master data belum digunakan oleh mana-mana transaksi. Jika sudah digunakan, gunakan Tidak Aktif.
- Tambah menu "Master Data" dalam navigation Urus Setia:

```text
Master Data
├── Kategori Permohonan
├── Kategori Projek
├── Jenis Perolehan
└── Kaedah Perolehan
```

---

*Fasa U2 tamat. Menunggu arahan untuk Fasa U3.*
