# LAPORAN FASA U4 — MIGRATION DATA SEDIA ADA → MASTER DATA

**Projek:** SPPICT v2 — Master Data Urus Setia (Modul Permohonan Projek)
**Tarikh:** 7 Oktober 2026
**Branch:** `feature/application-module`
**Status:** BERJAYA DENGAN CATATAN

Semua data transaksi lama, termasuk rekod soft-deleted, kini dipetakan kepada master data. Bilangan rekod dan audit tidak berubah. Fasa U5 belum dimulakan.

> **Catatan prompt:** fail prompt Fasa U4 (versi kemaskini) yang diterima terpotong di bahagian 25 (Rollback / Down Migration). Reka bentuk `down()` mengikut prinsip yang masih kelihatan dalam bahagian itu: tidak memadam data transaksi dan tidak menukar snapshot secara membuta.

---

## 1. Objektif

- Isi FK bagi semua rekod lama:
  - `applications.procurement_type_id`
  - `applications.procurement_method_id`
  - `application_details.project_category_id`
- Kemaskini dua label snapshot dalam `application_details.item_category`.
- Kekalkan lajur teks lama sebagai snapshot.
- Proses rekod soft-deleted.
- Tidak menyentuh audit, snapshot procurement, `application_category` atau sebarang logik Modul Permohonan.

---

## 2. Step 1 — Audit (sebelum migration)

| Jadual | Jumlah | Catatan |
|---|---|---|
| `applications` | 4 | 0 soft-deleted |
| `application_details` | 92 | 68 soft-deleted |
| `application_categories` | 5 | Semua aktif |
| `project_categories` | 6 | Semua aktif |
| `procurement_types` | 4 | Semua aktif |
| `procurement_methods` | 3 | Semua aktif |
| `audits` | 122 | ID maksimum 122 |

Semakan tambahan:

| Semakan | Keputusan |
|---|---|
| `code` berulang dalam master data | 0 |
| FK sedia ada yang sudah diisi | 0 |
| `applications.application_category` | 2 NULL, 1 `pembangunan_sistem`, 1 `perolehan_perkakasan`. Semua kod sah. Tidak disentuh. |

---

## 3. Step 2 — Pratonton Pemetaan

### 3.1 APPLICATIONS — `procurement_type` → `procurement_type_id`

| Nilai lama | Master code | Rekod | Soft-deleted | Akan dikemaskini |
|---|---|---|---|---|
| Pembekalan / Perkhidmatan Tidak Bermasa (One-Off) | `pembekalan_perkhidmatan_one_off` | 2 | 0 | 2 |
| Pembekalan / Perkhidmatan Bermasa | `pembekalan_perkhidmatan_bermasa` | 1 | 0 | 1 |
| Pembangunan Aplikasi | `pembangunan_aplikasi` | 1 | 0 | 1 |

### 3.2 APPLICATIONS — `procurement_method` → `procurement_method_id`

| Nilai lama | Master code | Rekod | Soft-deleted | Akan dikemaskini |
|---|---|---|---|---|
| Sebut Harga | `sebut_harga` | 4 | 0 | 4 |

### 3.3 APPLICATION_DETAILS — `item_category` → `project_category_id`

| Nilai lama | Master code | Rekod | Soft-deleted | Akan dikemaskini |
|---|---|---|---|---|
| Projek Baharu | `projek_baharu` | 61 | 43 | 61 |
| Penambahbaikan Peralatan | `penambahbaikan_peralatan` | 30 | 24 | 30 |
| Khidmat Perunding ICT | `khidmat_perunding_ict` | 1 | 1 | 1 |

### 3.4 Ringkasan Pratonton

| Perkara | Jumlah |
|---|---|
| Nilai tidak sepadan | **0** |
| NULL dalam lajur yang dipetakan | 0 |
| Rekod akan dikemaskini | 4 (procurement_type) + 4 (procurement_method) + 92 (item_category) |
| Snapshot `Peluasan Sistem/Projek` → `Peluasan Sistem / Projek` | 0 rekod |
| Snapshot `Penyelenggaraan` → `Penyelenggaraan / Sewaan / Langganan` | 0 rekod |
| `applications.procurement_type = 'Penyelenggaraan'` (tidak disentuh) | 0 rekod |

Dua label snapshot tidak wujud dalam DB pembangunan, tetapi kemaskini tetap disertakan dalam migration untuk persekitaran lain, dan disahkan melalui ujian automatik.

**Keputusan pratonton: LENGKAP — tiada nilai tidak sepadan.** Migration diteruskan.

---

## 4. Fail Dicipta

| Fail | Tujuan |
|---|---|
| `database/migrations/2026_10_07_000009_map_existing_application_data_to_master_data.php` | Migration pemetaan data |
| `tests/Feature/ApplicationMasterDataMigrationTest.php` | 4 ujian migration |

## 5. Fail Diubah

Tiada.

---

## 6. Reka Bentuk Migration

### 6.1 Jadual Pemetaan Dalam Migration

**`applications.procurement_type` → `procurement_types`**

| Nilai lama | Master code |
|---|---|
| Pembekalan / Perkhidmatan Tidak Bermasa (One-Off) | `pembekalan_perkhidmatan_one_off` |
| Pembekalan / Perkhidmatan Bermasa | `pembekalan_perkhidmatan_bermasa` |
| Penyelenggaraan | `penyelenggaraan` |
| Pembangunan Aplikasi | `pembangunan_aplikasi` |

**`applications.procurement_method` → `procurement_methods`**

| Nilai lama | Master code |
|---|---|
| Sebut Harga | `sebut_harga` |
| Tender Terbuka | `tender_terbuka` |
| Rundingan Terus | `rundingan_terus` |

**`application_details.item_category` → `project_categories`**

| Nilai lama | Master code |
|---|---|
| Projek Baharu | `projek_baharu` |
| Peningkatan Sistem | `peningkatan_sistem` |
| Peluasan Sistem/Projek | `peluasan_sistem_projek` |
| Peluasan Sistem / Projek | `peluasan_sistem_projek` |
| Penambahbaikan Peralatan | `penambahbaikan_peralatan` |
| Penyelenggaraan | `penyelenggaraan_sewaan_langganan` |
| Penyelenggaraan / Sewaan / Langganan | `penyelenggaraan_sewaan_langganan` |
| Khidmat Perunding ICT | `khidmat_perunding_ict` |

### 6.2 Peraturan Khas `Penyelenggaraan`

Pemetaan sentiasa ditentukan oleh **jadual + lajur**, bukan nilai sahaja:

| Jadual.lajur | Maksud | Master | Snapshot |
|---|---|---|---|
| `application_details.item_category` | Kategori Projek | `penyelenggaraan_sewaan_langganan` | Dilabel semula kepada `Penyelenggaraan / Sewaan / Langganan` |
| `applications.procurement_type` | Jenis Perolehan | `penyelenggaraan` | **Kekal** `Penyelenggaraan` |

Tiada carian dan ganti global.

### 6.3 Langkah Dalam Migration

Semua langkah dijalankan dalam `DB::transaction()`:

1. Ambil bilangan `applications`, `application_details` dan `audits`.
2. Semak nilai tidak sepadan bagi setiap jadual + lajur. Jika ada, migration berhenti **sebelum sebarang kemaskini**.
3. Cari ID master melalui `code` (tiada ID nombor ditulis terus). Setiap `code` yang diperlukan mesti wujud tepat satu rekod.
4. Isi FK hanya jika masih `NULL`.
5. Label semula dua nilai snapshot dalam `application_details.item_category` sahaja.
6. Sahkan tiada FK NULL yang tidak dijangka.
7. Sahkan setiap FK sepadan dengan nilai snapshot.
8. Sahkan bilangan `applications`, `application_details` dan `audits` sama seperti langkah 1.

### 6.4 Ciri Keselamatan

| Ciri | Pelaksanaan |
|---|---|
| Transaksi | `DB::transaction()`. Sebarang ralat rollback semua perubahan. Tiada `try/catch` yang menyembunyikan ralat. |
| Nilai tidak sepadan | Migration berhenti dengan mesej yang menyenaraikan nilai dan bilangan rekod. Master data tidak dicipta secara automatik. |
| Master tiada atau berulang | Migration berhenti dengan mesej *"… mesti wujud tepat satu rekod … Jalankan ApplicationMasterDataSeeder dahulu."* |
| Rekod soft-deleted | Diproses melalui `DB::table()`, yang tidak menapis `deleted_at` |
| Master soft-deleted | Masih dipetakan, kerana ia data sejarah |
| Audit trail | `DB::table()->update()` tidak mencetuskan event model. Tiada audit baharu. |
| `updated_at` | Tidak diubah oleh `DB::table()->update()` |
| Idempotent | FK hanya diisi jika `NULL`. Migration selamat dijalankan semula. |
| NULL / kosong | `procurement_*` yang NULL atau kosong (contoh draf) dibiarkan tanpa FK. `item_category` yang NULL atau kosong dianggap tidak sepadan. |
| Hanya master yang diperlukan | Migration hanya menuntut master bagi nilai yang wujud dalam data transaksi |

### 6.5 `down()`

`down()` sengaja tidak melakukan apa-apa (no-op):

- Tidak memadam data transaksi.
- Tidak menukar semula snapshot `item_category`. Selepas U5, label baharu juga digunakan oleh rekod baharu, jadi rekod lama dan baharu tidak boleh dibezakan dengan selamat.
- Lajur FK hanya dibuang jika migration `2026_10_07_000008` di-rollback.

### 6.6 Penemuan Semasa Ujian

Pada pangkalan data baharu (ujian atau pemasangan baru), migration berjalan **sebelum** seeder. Versi awal migration gagal kerana menuntut semua master wujud walaupun tiada data transaksi.

Pembetulan: migration kini hanya menuntut master bagi `code` yang benar-benar diperlukan oleh data sedia ada. Master data tetap tidak dicipta secara automatik.

---

## 7. Kesan Kepada Data Sedia Ada (disahkan pada DB pembangunan)

### 7.1 Bilangan Rekod (sebelum → selepas)

| Jadual | Sebelum | Selepas | Keputusan |
|---|---|---|---|
| `applications` | 4 | 4 | SAMA |
| `application_details` | 92 | 92 | SAMA |
| `audits` | 122 | 122 | SAMA |
| `application_categories` | 5 | 5 | SAMA |
| `project_categories` | 6 | 6 | SAMA |
| `procurement_types` | 4 | 4 | SAMA |
| `procurement_methods` | 3 | 3 | SAMA |

### 7.2 Kandungan

| Semakan | Keputusan |
|---|---|
| ID audit maksimum | 122 → 122 |
| Kandungan `audits` (hash) | **SAMA** |
| Kandungan master data (hash) | **SAMA** |
| Baris `applications` berubah selain lajur FK (hash setiap baris) | **0** |
| Baris `application_details` berubah selain lajur FK (hash setiap baris) | **0** |

### 7.3 Pemetaan Selepas Migration

| Jadual.lajur | Nilai | FK → master code | Rekod (soft-deleted) |
|---|---|---|---|
| applications.procurement_type | Pembangunan Aplikasi | `pembangunan_aplikasi` | 1 (0) |
| | Pembekalan / Perkhidmatan Bermasa | `pembekalan_perkhidmatan_bermasa` | 1 (0) |
| | Pembekalan / Perkhidmatan Tidak Bermasa (One-Off) | `pembekalan_perkhidmatan_one_off` | 2 (0) |
| applications.procurement_method | Sebut Harga | `sebut_harga` | 4 (0) |
| application_details.item_category | Projek Baharu | `projek_baharu` | 61 (43) |
| | Khidmat Perunding ICT | `khidmat_perunding_ict` | 1 (1) |
| | Penambahbaikan Peralatan | `penambahbaikan_peralatan` | 30 (24) |

### 7.4 FK NULL Yang Tidak Dijangka

| Semakan | Jumlah |
|---|---|
| `procurement_type` ada nilai tetapi `procurement_type_id` NULL | **0** |
| `procurement_method` ada nilai tetapi `procurement_method_id` NULL | **0** |
| `item_category` ada nilai tetapi `project_category_id` NULL | **0** |

### 7.5 Semakan Khas Penyelenggaraan

| Semakan | Keputusan |
|---|---|
| `application_details.item_category = 'Penyelenggaraan'` | 0 (dijangka 0) |
| `application_details.item_category = 'Peluasan Sistem/Projek'` | 0 (dijangka 0) |
| `item_category = 'Penyelenggaraan / Sewaan / Langganan'` dengan FK salah | 0 |
| `applications.procurement_type = 'Penyelenggaraan'` dengan FK salah atau NULL | 0 |

DB pembangunan tidak mempunyai rekod dengan nilai `Penyelenggaraan` dalam mana-mana konteks. Kedua-dua konteks disahkan melalui ujian automatik (bahagian 10).

### 7.6 Lain-lain

- Rekod soft-deleted dipetakan: **68/68**, dan kekal soft-deleted.
- `application_category` tidak berubah: 2 NULL, 1 `pembangunan_sistem`, 1 `perolehan_perkakasan`.

---

## 8. Strategi Migrasi Untuk Persekitaran Lain (staging dan produksi)

Ikut langkah ini:

1. Jalankan `php artisan migrate`.
   - Jika data transaksi sudah ada tetapi master belum di-seed, migration `000009` berhenti dengan mesej *"Jalankan ApplicationMasterDataSeeder dahulu"*.
   - Migration `000003`–`000008` sudah selesai pada ketika itu, dan tiada data diubah.
2. Jalankan `php artisan db:seed --class=ApplicationMasterDataSeeder`.
3. Jalankan `php artisan migrate` sekali lagi.

Sebelum langkah 1, ambil backup pangkalan data seperti amalan biasa.

---

## 9. Kawalan Akses

Tiada perubahan.

---

## 10. Ujian

### 10.1 Keputusan

| Ujian | Keputusan |
|---|---|
| `ApplicationMasterDataMigrationTest` | **4/4 lulus** (26 assertion) |
| Suite penuh | **132/133 lulus** |

Satu kegagalan dalam suite penuh ialah `ProfileTest::test_user_can_delete_their_account`, sama seperti dalam U2 dan U3. Ia berpunca daripada perubahan soft delete pada model `User` yang belum di-commit, dan tiada kaitan dengan fasa ini.

### 10.2 Liputan `ApplicationMasterDataMigrationTest`

| Ujian | Apa yang disahkan |
|---|---|
| Pemetaan ikut jadual + lajur | `Penyelenggaraan` dalam dua konteks; label lama dilabel semula; rekod soft-deleted dipetakan dan kekal soft-deleted; draf dengan NULL dibiarkan; `updated_at` tidak berubah; bilangan rekod dan audit tidak berubah |
| Nilai tidak sepadan | Migration berhenti dengan mesej jelas; tiada perubahan separuh siap |
| Master tiada | Migration berhenti; tiada master dicipta; tiada FK diisi |
| Idempotent | Migration dijalankan dua kali tanpa masalah; master yang telah di-soft delete masih dipetakan |

---

## 11. Risiko

| Risiko | Tahap | Tindakan |
|---|---|---|
| Sehingga U5, borang permohonan masih menyimpan teks lama tanpa FK. Rekod baharu yang dicipta antara U4 dan U5 tidak mempunyai FK. | Sederhana | U5 perlu mengisi FK semasa simpan, dan jika perlu menyediakan pemetaan susulan. Migration `000009` idempotent, tetapi sudah ditanda "Ran". |
| Sehingga U5, borang masih menulis `Penyelenggaraan` sebagai Kategori Projek. Label lama akan wujud semula dalam rekod baharu. | Sederhana | Diselesaikan apabila dropdown menggunakan master data pada U5. |

---

## 12. Perubahan Di Luar Skop

Tiada.

- Borang, service, enum, validation, UI, route, navigation, audit, pengguna dan role tidak disentuh.
- Tiada operasi destruktif dijalankan. Tiada `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `DROP`, `TRUNCATE` atau `DELETE` pada jadual transaksi.

---

## 13. Status Git

- Branch: `feature/application-module`
- Baharu (belum dijejak):
  - `database/migrations/2026_10_07_000009_map_existing_application_data_to_master_data.php`
  - `tests/Feature/ApplicationMasterDataMigrationTest.php`
- Belum di-commit.

---

## 14. Cadangan Untuk Fasa U5 — Integrasi Permohonan Projek

1. Dropdown Fasa 1–3 diambil daripada master data `active()->ordered()`. Nilai semasa rekod lama yang telah dinyahaktifkan masih perlu dipaparkan semasa edit.
2. Validation ditukar kepada `Rule::exists(...)->where('is_active', true)`.
3. `ApplicationSubmissionService` menyimpan FK dan snapshot nama.
4. Paparan menggunakan relationship, dengan teks snapshot sebagai fallback.
5. Buang enum `ApplicationCategory` dan cast enumnya.
6. Kemaskini lima fail ujian yang menggunakan nilai hard code.
7. Cari semula seluruh repository untuk memastikan tiada hard code yang tertinggal.

---

*Fasa U4 tamat. Menunggu arahan untuk Fasa U5.*
