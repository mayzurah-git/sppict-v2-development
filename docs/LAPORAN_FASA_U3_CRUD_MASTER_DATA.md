# LAPORAN FASA U3 — CRUD MASTER DATA

**Projek:** SPPICT v2 — Master Data Urus Setia (Modul Permohonan Projek)
**Tarikh:** 7 Oktober 2026
**Branch:** `feature/application-module`
**Status:** BERJAYA DENGAN CATATAN

Urus Setia kini boleh mengurus keempat-empat master data melalui menu baharu **Master Data**. Borang Permohonan Projek belum disentuh. Fasa U4 dan U5 belum dimulakan.

---

## 1. Objektif

Bina CRUD untuk empat master data:

- Kategori Permohonan
- Kategori Projek
- Jenis Perolehan
- Kaedah Perolehan

Fungsi yang disediakan:

- lihat senarai, carian, penapis Aktif / Tidak Aktif dan pagination
- tambah dan kemaskini
- aktifkan dan nyahaktifkan
- soft delete secara terkawal (hanya jika belum digunakan)
- kawalan akses Urus Setia sahaja
- audit trail

---

## 2. Reka Bentuk

### 2.1 Struktur Komponen

- **Satu kelas asas** `App\Livewire\MasterData\MasterDataManagement` dengan empat subkelas nipis dan **satu view yang dikongsi**.
- Aliran kerja sama dengan `MeetingNameManagement` sedia ada:
  - modal tambah/kemaskini
  - `ensureUrusSetia()`
  - flash message kejayaan dan ralat
  - gaya Tailwind yang sama
- Kelas asas dipilih kerana logik CRUD keempat-empatnya serupa. Ini mengelakkan empat salinan kod yang hampir sama.
- Setiap subkelas hanya menentukan model, label, keterangan dan (jika perlu) peraturan `sort_order` tambahan.

### 2.2 Peraturan Medan

| Medan | Peraturan |
|---|---|
| Nama | Wajib, maksimum 255 aksara, unik (rekod soft-deleted tidak dikira) |
| Kod | Wajib, maksimum 100 aksara, format slug (`^[a-z0-9]+(_[a-z0-9]+)*$`), unik |
| Susunan | Wajib, integer. Kategori Projek: 1–26 dan tidak berulang |
| Aktif | Boolean |

### 2.3 Kod (`code`)

- Jika dibiarkan kosong semasa tambah, kod dijana automatik daripada nama. Contoh: "Contoh Baharu / Ujian" menjadi `contoh_baharu_ujian`.
- Kod **dikunci** apabila sudah digunakan oleh transaksi:
  - input dinyahaktifkan dalam UI
  - pelayan menyemak semula semasa simpan dan menolak perubahan kod

### 2.4 Kategori Projek

- `sort_order` mesti antara 1–26 dan tidak berulang, kerana ia menjana prefix A–Z.
- Senarai memaparkan nombor bersama prefix, contoh "1 (A)".
- Borang memaparkan petunjuk: *"Susunan menentukan prefix paparan: 1 = A, 2 = B, 3 = C, dan seterusnya."*

### 2.5 Padam

- Soft delete hanya dibenarkan jika master data belum digunakan.
- Jika sudah digunakan, mesej dipaparkan: *"… tidak boleh dipadam kerana telah digunakan oleh rekod permohonan. Sila nyahaktifkan sahaja."*

### 2.6 Semakan Penggunaan (`isInUse()`)

| Model | Dianggap digunakan jika |
|---|---|
| `ApplicationCategory` | Ada `applications.application_category` = `code` |
| `ProjectCategory` | Ada `application_details.project_category_id` = `id` **atau** `item_category` = `name` |
| `ProcurementType` | Ada `applications.procurement_type_id` = `id` **atau** `procurement_type` = `name` |
| `ProcurementMethod` | Ada `applications.procurement_method_id` = `id` **atau** `procurement_method` = `name` |

Semua semakan termasuk rekod transaksi yang telah di-soft delete.

Semakan teks diperlukan kerana FK belum diisi sehingga Fasa U4. Tanpanya, "Projek Baharu" (61 rekod) akan disangka belum digunakan.

---

## 3. Fail Dicipta

| Fail | Tujuan |
|---|---|
| `app/Livewire/MasterData/MasterDataManagement.php` | Kelas asas CRUD |
| `app/Livewire/MasterData/ApplicationCategoryManagement.php` | Komponen Kategori Permohonan |
| `app/Livewire/MasterData/ProjectCategoryManagement.php` | Komponen Kategori Projek (had `sort_order` 1–26, unik) |
| `app/Livewire/MasterData/ProcurementTypeManagement.php` | Komponen Jenis Perolehan |
| `app/Livewire/MasterData/ProcurementMethodManagement.php` | Komponen Kaedah Perolehan |
| `resources/views/livewire/master-data/management.blade.php` | View yang dikongsi |
| `tests/Feature/ApplicationMasterDataTest.php` | 22 ujian |

---

## 4. Fail Diubah

| Fail | Perubahan |
|---|---|
| `app/Models/ApplicationCategory.php` | Tambah `isInUse()` |
| `app/Models/ProjectCategory.php` | Tambah `isInUse()` |
| `app/Models/ProcurementType.php` | Tambah `isInUse()` |
| `app/Models/ProcurementMethod.php` | Tambah `isInUse()` |
| `routes/web.php` | Tambah empat route dalam kumpulan `role:pentadbir_urus_setia` |
| `resources/views/livewire/layout/navigation.blade.php` | Tambah dropdown **Master Data** di bawah `@hasrole('pentadbir_urus_setia')` |

### 4.1 Route

| Method | URL | Nama Route |
|---|---|---|
| GET | `/urus-setia/master-data/kategori-permohonan` | `master-data.application-categories` |
| GET | `/urus-setia/master-data/kategori-projek` | `master-data.project-categories` |
| GET | `/urus-setia/master-data/jenis-perolehan` | `master-data.procurement-types` |
| GET | `/urus-setia/master-data/kaedah-perolehan` | `master-data.procurement-methods` |

### 4.2 Navigation

```text
Master Data
├── Kategori Permohonan
├── Kategori Projek
├── Jenis Perolehan
└── Kaedah Perolehan
```

Navigation Superadmin tidak diubah.

---

## 5. Perubahan Pangkalan Data

Tiada.

---

## 6. Hard Code

Tiada hard code dibuang. Borang Permohonan Projek masih menggunakan dropdown lama sehingga Fasa U5.

---

## 7. Kesan Kepada Data Sedia Ada

Tiada data transaksi diubah.

---

## 8. Strategi Migrasi

Tiada migrasi data pada fasa ini. Pemetaan data dirancang untuk Fasa U4 (lihat bahagian 14).

---

## 9. Kawalan Akses

Akses dilindungi pada dua lapisan:

| Lapisan | Mekanisme | Kesan |
|---|---|---|
| Route | Middleware `role:pentadbir_urus_setia` | Superadmin dan pengguna biasa mendapat 403 |
| Komponen Livewire | `ensureUrusSetia()` dalam `mount`, `openModal`, `save`, `setStatus` dan `delete` | Menghalang panggilan action terus melalui `/livewire/update` |
| Navigation | `@hasrole('pentadbir_urus_setia')` | Menu hanya dipaparkan kepada Urus Setia |

Kawalan akses tidak bergantung pada menu yang disembunyikan sahaja.

---

## 10. Ujian

### 10.1 Keputusan

| Ujian | Keputusan |
|---|---|
| `ApplicationMasterDataTest` | **22/22 lulus** (142 assertion) |
| Suite penuh | **127/128 lulus** |

Satu kegagalan dalam suite penuh ialah `ProfileTest::test_user_can_delete_their_account`, sama seperti dalam U2. Ia berpunca daripada perubahan soft delete pada model `User` yang belum di-commit, dan tiada kaitan dengan fasa ini.

### 10.2 Liputan `ApplicationMasterDataTest`

- Akses mengikut role: 4 route × 3 role (Urus Setia, Superadmin, pengguna biasa)
- Menu navigation hanya dipaparkan kepada Urus Setia
- Komponen menolak pengguna bukan Urus Setia (×4)
- Tambah dengan kod auto, kemaskini, aktif dan nyahaktif (×4 master data)
- Kod dan nama mesti unik, format kod mesti betul (×4)
- Carian, penapis Aktif / Tidak Aktif dan pagination (10 rekod sehalaman)
- Kategori Projek: had `sort_order` 1–26, keunikan, dan label "G. KATEGORI UJIAN"
- Rekod yang sudah digunakan tidak boleh dipadam dan kodnya tidak boleh ditukar, termasuk:
  - rekod yang hanya dikesan melalui teks snapshot
  - rekod detail yang telah di-soft delete
- Nilai `Penyelenggaraan` sebagai Jenis Perolehan tidak menyebabkan Kategori Projek dianggap sudah digunakan
- Penggunaan dikesan melalui FK
- Audit trail: create, update dan nyahaktif direkod bersama `user_id`

---

## 11. Risiko

| Risiko | Tahap | Catatan |
|---|---|---|
| Sebelum Fasa U4, jika nama master data yang sudah digunakan ditukar, semakan penggunaan berdasarkan teks tidak lagi sepadan dengan rekod lama. Rekod itu boleh di-soft delete. | Rendah | Pemetaan U4 menggunakan `code` dan `withTrashed()`, jadi rekod lama masih boleh dipetakan. Risiko hilang selepas FK diisi pada U4. |
| Kategori Projek yang dinyahaktifkan mengekalkan hurufnya. Contoh: jika B dinyahaktifkan, dropdown U5 memaparkan A, C, D. | Rendah | Mengikut keputusan bahawa prefix dijana daripada `sort_order`. |
| Layout sedia ada tidak mempunyai menu navigation untuk mudah alih. Menu Master Data hanya dipaparkan pada skrin desktop. | Rendah | Sama seperti menu Mesyuarat sedia ada. |

---

## 12. Perubahan Di Luar Skop

Tiada.

---

## 13. Status Git

- Branch: `feature/application-module`
- Baharu (belum dijejak):
  - `app/Livewire/MasterData/`
  - `resources/views/livewire/master-data/`
  - `tests/Feature/ApplicationMasterDataTest.php`
- Diubah:
  - `routes/web.php`
  - `resources/views/livewire/layout/navigation.blade.php`
  - empat model master data
- Belum di-commit.

---

## 14. Cadangan Untuk Fasa U4 — Migration Existing Hard Code

Satu migration data yang tidak destruktif untuk:

1. **Isi FK** `procurement_type_id`, `procurement_method_id` dan `project_category_id` mengikut jadual pemetaan dalam laporan U2.
   - Gunakan `DB::table()`, termasuk rekod soft-deleted.
   - Tiada entri audit baharu dicipta dan entri audit lama tidak disentuh.
2. **Kemaskini teks snapshot `item_category`** bagi dua label yang bertukar:
   - `Peluasan Sistem/Projek` → `Peluasan Sistem / Projek`
   - `Penyelenggaraan` → `Penyelenggaraan / Sewaan / Langganan`
   - Hanya pada jadual `application_details`. Nilai `Penyelenggaraan` dalam `applications.procurement_type` tidak disentuh.
3. **Sahkan** bilangan rekod dan pastikan tiada nilai yang tidak sepadan. Migration akan berhenti dengan ralat jika ada nilai yang tidak dapat dipetakan.

---

*Fasa U3 tamat. Menunggu arahan untuk Fasa U4.*
