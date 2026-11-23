# SPPICT v2 — Spesifikasi Modul Pengurusan Skim Perkhidmatan, Gred dan Jawatan

> **Dokumen rujukan untuk AI Agent / Codex di VS Code**
>
> Tujuan dokumen ini adalah untuk menjadi spesifikasi pelaksanaan bagi modul master data yang digunakan oleh **Superadmin** untuk mengurus maklumat Skim Perkhidmatan, Gred Jawatan dan Jawatan, serta menggunakannya dalam modul Pengurusan Pengguna.

---

## 1. Objektif

Membangunkan modul pengurusan master data yang membolehkan Superadmin:

1. Mengurus **Skim Perkhidmatan**.
2. Mengurus **Gred Jawatan**.
3. Mengurus **Jawatan**.
4. Menghubungkan Jawatan dengan Skim Perkhidmatan dan Gred Jawatan.
5. Menggunakan Jawatan dalam modul **Pengurusan Pengguna**.
6. Memastikan maklumat jawatan, skim dan gred konsisten melalui relationship database.
7. Mengekalkan reka bentuk yang boleh dikembangkan pada masa hadapan.

---

## 2. Sumber Data

Senarai Skim Perkhidmatan dan Gred Jawatan dalam spesifikasi ini hendaklah berpandukan fail sumber yang diberikan.

### 2.1 Skim Perkhidmatan

| Kod | Skim Perkhidmatan |
|---|---|
| A | Pengangkutan |
| B | Bakat dan Seni |
| C | Sains |
| D | Pendidikan |
| E | Ekonomi |
| F | Teknologi Maklumat |
| G | Pertanian |
| J | Kejuruteraan |
| K | Keselamatan |
| L | Perundangan |
| M | Tadbir dan Diplomatik |
| N | Tadbir dan Sokongan |
| P | Pencegahan |
| Q | Penyelidik |
| S | Sosial |
| U | Perubatan dan Kesihatan |
| W | Kewangan |
| X | Maritim |

**Nota:** Jangan tambah, buang atau ubah senarai di atas tanpa arahan pengguna.

### 2.2 Gred Jawatan

Fail sumber menyenaraikan Gred Jawatan:

```text
1
2
3
4
5
6
7
8
9
10
11
12
13
14
15
```

**Nota:** Nama/label tambahan bagi gred tidak dinyatakan dalam fail sumber. Jangan mereka-reka maklumat tersebut tanpa arahan.

---

# 3. Prinsip Reka Bentuk

Gunakan konsep berikut:

```text
Skim Perkhidmatan
        │
        ▼
Gred Jawatan
        │
        ▼
Jawatan
        │
        ▼
Pengguna
```

Walau bagaimanapun, dari perspektif database, **Jawatan** hendaklah menjadi entiti yang menyimpan relationship kepada:

- Skim Perkhidmatan
- Gred Jawatan

Kemudian `users` merujuk kepada `position_id`.

Cadangan relationship:

```text
service_schemes
       │
       └──────────────┐
                      ▼
                  positions
                      ▲
       ┌──────────────┘
       │
     grades

users ───────────────► positions
```

---

# 4. Struktur Menu Superadmin

Cadangan menu:

```text
PENGURUSAN SISTEM
│
├── Pengguna
│
├── Agensi
│
├── Jawatan
│
├── Skim Perkhidmatan
│
├── Gred Jawatan
│
└── Peranan & Akses
```

Skim Perkhidmatan dan Gred Jawatan dianggap sebagai **Master Data**.

Jawatan menggunakan kedua-dua master data tersebut.

---

# 5. Database Design

## 5.1 Table: service_schemes

Cadangan migration:

```text
service_schemes
-----------------------------
id
code
name
description
is_active
created_at
updated_at
```

### Field specification

| Field | Type | Required | Description |
|---|---|---:|---|
| id | bigint | Yes | Primary key |
| code | string | Yes | Kod skim seperti A, B, C, F |
| name | string | Yes | Nama skim |
| description | text | No | Penerangan tambahan |
| is_active | boolean | Yes | Status aktif/tidak aktif |
| created_at | timestamp | Yes | Laravel timestamp |
| updated_at | timestamp | Yes | Laravel timestamp |

### Constraints

- `code` mesti unik.
- `name` hendaklah unik jika sesuai dengan reka bentuk sedia ada.
- `is_active` default kepada `true`.
- Jangan hard delete rekod yang telah digunakan oleh `positions`.
- Gunakan deactivation/soft business rule jika rekod telah digunakan.

---

## 5.2 Table: grades

Cadangan migration:

```text
grades
-----------------------------
id
code
name
description
is_active
created_at
updated_at
```

### Field specification

| Field | Type | Required | Description |
|---|---|---:|---|
| id | bigint | Yes | Primary key |
| code | string | Yes | Kod gred, berdasarkan data sumber 1–15 |
| name | string | Yes | Label/nama gred |
| description | text | No | Penerangan tambahan |
| is_active | boolean | Yes | Status aktif/tidak aktif |
| created_at | timestamp | Yes | Laravel timestamp |
| updated_at | timestamp | Yes | Laravel timestamp |

### Important

Fail sumber hanya memberikan nilai:

```text
1–15
```

Oleh itu AI Agent **tidak boleh mencipta nama rasmi gred** yang tidak diberikan oleh sumber.

Jika sistem sedia ada menggunakan istilah lain untuk gred, Agent hendaklah mengekalkan istilah yang telah wujud dan melaporkan perbezaan tersebut sebelum mengubah database.

---

## 5.3 Table: positions

Cadangan migration:

```text
positions
-----------------------------
id
name
service_scheme_id
grade_id
description
is_active
created_at
updated_at
```

### Field specification

| Field | Type | Required | Description |
|---|---|---:|---|
| id | bigint | Yes | Primary key |
| name | string | Yes | Nama jawatan |
| service_scheme_id | foreign key | Yes | FK kepada service_schemes |
| grade_id | foreign key | Yes | FK kepada grades |
| description | text | No | Penerangan |
| is_active | boolean | Yes | Status aktif/tidak aktif |
| created_at | timestamp | Yes | Laravel timestamp |
| updated_at | timestamp | Yes | Laravel timestamp |

### Relationship

```text
Position belongsTo ServiceScheme
Position belongsTo Grade

ServiceScheme hasMany Positions
Grade hasMany Positions
```

---

# 6. Integrasi Dengan users

Jika struktur `users` sedia ada belum mempunyai `position_id`, tambahkan:

```text
users
-----------------------------
id
...
position_id
...
```

Relationship:

```text
User belongsTo Position
Position hasMany Users
```

### Important

Sebelum membuat migration baru:

1. Inspect migration `users` sedia ada.
2. Inspect model `User`.
3. Inspect migration/model berkaitan `roles`.
4. Inspect migration/model berkaitan `agencies`.
5. Jangan duplicate column atau relationship yang sudah wujud.
6. Jangan ubah struktur sedia ada tanpa keperluan.

---

# 7. Form Pengurusan Skim Perkhidmatan

## 7.1 Senarai

Paparkan:

| Kolum | Kandungan |
|---|---|
| Kod | Kod skim |
| Nama | Nama skim |
| Status | Aktif / Tidak Aktif |
| Bil. Jawatan | Bilangan jawatan yang menggunakan skim |
| Tindakan | Edit / Aktifkan / Nyahaktif |

Sediakan:

```text
+ Tambah Skim Perkhidmatan
```

Sediakan carian dan filter status jika sesuai dengan UI sedia ada.

---

## 7.2 Form Tambah/Edit

Field:

```text
Kod Skim *
Nama Skim Perkhidmatan *
Penerangan
Status
```

Contoh:

```text
Kod Skim
[F]

Nama Skim Perkhidmatan
[Teknologi Maklumat]

Penerangan
[........................]

Status
(●) Aktif
( ) Tidak Aktif
```

### Validation

- `code`: required, string, unique.
- `name`: required, string.
- `description`: nullable.
- `is_active`: boolean.

---

# 8. Form Pengurusan Gred Jawatan

## 8.1 Senarai

Paparkan:

| Kolum | Kandungan |
|---|---|
| Kod Gred | 1–15 |
| Nama | Nama/label yang disimpan |
| Status | Aktif / Tidak Aktif |
| Bil. Jawatan | Bilangan jawatan menggunakan gred |
| Tindakan | Edit / Aktifkan / Nyahaktif |

Sediakan:

```text
+ Tambah Gred Jawatan
```

---

## 8.2 Form Tambah/Edit

Field:

```text
Kod Gred *
Nama Gred *
Penerangan
Status
```

### Validation

- `code`: required, string, unique.
- `name`: required, string.
- `description`: nullable.
- `is_active`: boolean.

---

# 9. Form Pengurusan Jawatan

Ini adalah form utama yang menghubungkan kedua-dua master data.

## 9.1 Form

```text
Tambah Jawatan

Nama Jawatan *
[................................................]

Skim Perkhidmatan *
[ F - Teknologi Maklumat                       ▼ ]

Gred Jawatan *
[ 9                                             ▼ ]

Penerangan
[................................................]

Status
(●) Aktif
( ) Tidak Aktif

[ Batal ]                         [ Simpan ]
```

---

## 9.2 Senarai Jawatan

Cadangan kolum:

| Jawatan | Skim | Gred | Status | Pengguna | Tindakan |
|---|---|---|---|---:|---|
| Nama jawatan | F - Teknologi Maklumat | 9 | Aktif | 5 | Edit |

Sediakan:

- Search
- Filter Skim Perkhidmatan
- Filter Gred
- Filter Status
- Pagination

---

# 10. Pengurusan Pengguna

Dalam form pengguna, gunakan:

```text
Nama Penuh *
Emel *
Agensi *
Jawatan *
Peranan Sistem *
Status
```

Selepas Superadmin memilih `Jawatan`, sistem boleh memaparkan:

```text
Jawatan
Pegawai Teknologi Maklumat

Skim Perkhidmatan
F - Teknologi Maklumat

Gred
9
```

Maklumat Skim dan Gred sebaiknya **read-only** jika ia datang daripada `positions`.

Ini mengelakkan pengguna mempunyai kombinasi data yang tidak konsisten.

---

# 11. Cadangan UX

Gunakan cascading/relationship-driven selection jika terdapat keperluan untuk memilih secara berperingkat.

Contoh alternatif:

```text
Skim Perkhidmatan
[F - Teknologi Maklumat ▼]

Gred
[9 ▼]

Jawatan
[Pegawai Teknologi Maklumat ▼]
```

Namun, untuk jangka panjang, pendekatan yang lebih baik ialah:

```text
Jawatan
[Pegawai Teknologi Maklumat ▼]

Skim
F - Teknologi Maklumat     [readonly]

Gred
9                          [readonly]
```

Sebab satu rekod `position` telah menentukan kombinasi tersebut.

---

# 12. Business Rules

## 12.1 Skim Perkhidmatan

1. Kod skim mesti unik.
2. Skim boleh dinyahaktifkan.
3. Skim yang telah digunakan oleh jawatan tidak boleh dihapuskan secara hard delete.
4. Skim tidak aktif tidak boleh digunakan untuk mencipta jawatan baharu.

## 12.2 Gred

1. Kod gred mesti unik.
2. Gred boleh dinyahaktifkan.
3. Gred yang telah digunakan oleh jawatan tidak boleh dihapuskan secara hard delete.
4. Gred tidak aktif tidak boleh digunakan untuk mencipta jawatan baharu.

## 12.3 Jawatan

1. Nama jawatan wajib diisi.
2. Skim Perkhidmatan wajib dipilih.
3. Gred wajib dipilih.
4. Jawatan tidak aktif tidak boleh dipilih untuk pengguna baharu.
5. Jawatan yang telah digunakan oleh pengguna tidak boleh dihapuskan secara hard delete.
6. Sebaiknya gunakan status aktif/tidak aktif berbanding delete.

## 12.4 Pengguna

1. Pengguna boleh mempunyai satu jawatan.
2. Jawatan hendaklah berasal daripada `positions`.
3. Skim dan Gred tidak perlu disimpan semula dalam `users` jika kedua-duanya boleh diperoleh melalui `position`.
4. Role sistem kekal berasingan daripada jawatan.

---

# 13. Laravel Model Relationships

## ServiceScheme

```php
public function positions()
{
    return $this->hasMany(Position::class);
}
```

## Grade

```php
public function positions()
{
    return $this->hasMany(Position::class);
}
```

## Position

```php
public function serviceScheme()
{
    return $this->belongsTo(ServiceScheme::class);
}

public function grade()
{
    return $this->belongsTo(Grade::class);
}

public function users()
{
    return $this->hasMany(User::class);
}
```

## User

```php
public function position()
{
    return $this->belongsTo(Position::class);
}
```

**Nota:** Gunakan namespace, model naming dan coding conventions yang telah digunakan oleh projek sedia ada. Jangan terus copy code di atas tanpa terlebih dahulu memeriksa struktur projek.

---

# 14. Seed Data

Seed data untuk Skim Perkhidmatan hendaklah menggunakan senarai daripada sumber:

```text
A - Pengangkutan
B - Bakat dan Seni
C - Sains
D - Pendidikan
E - Ekonomi
F - Teknologi Maklumat
G - Pertanian
J - Kejuruteraan
K - Keselamatan
L - Perundangan
M - Tadbir dan Diplomatik
N - Tadbir dan Sokongan
P - Pencegahan
Q - Penyelidik
S - Sosial
U - Perubatan dan Kesihatan
W - Kewangan
X - Maritim
```

Seed Gred:

```text
1
2
3
4
5
6
7
8
9
10
11
12
13
14
15
```

Gunakan `updateOrCreate()` atau pendekatan idempotent yang sesuai supaya seeder boleh dijalankan semula tanpa duplicate data.

---

# 15. Arahan Untuk AI Agent

## WAJIB: Analisis Projek Terlebih Dahulu

Sebelum menulis atau mengubah sebarang kod:

1. Inspect struktur folder projek.
2. Kenal pasti versi Laravel.
3. Inspect `composer.json`.
4. Inspect `package.json`.
5. Inspect migration sedia ada.
6. Inspect model sedia ada.
7. Inspect routes.
8. Inspect layout/UI components.
9. Inspect modul Pengurusan Pengguna yang sedia ada.
10. Inspect RBAC/role implementation.
11. Kenal pasti sama ada Livewire digunakan.
12. Kenal pasti sama ada Tailwind CSS/Bootstrap digunakan.
13. Kenal pasti naming convention projek.

Jangan terus membuat migration atau component sebelum analisis ini selesai.

---

# 16. Prinsip Jangan Rosakkan Fungsi Sedia Ada

AI Agent:

- Jangan overwrite functionality sedia ada tanpa sebab.
- Jangan ubah migration lama jika migration baharu lebih selamat.
- Jangan duplicate model.
- Jangan duplicate relationship.
- Jangan duplicate route.
- Jangan duplicate menu.
- Jangan memasang package baharu jika fungsi boleh dilaksanakan menggunakan package sedia ada.
- Kekalkan UI/UX style projek.
- Kekalkan authorization/RBAC sedia ada.
- Pastikan hanya Superadmin boleh mengurus master data jika itu selaras dengan authorization sedia ada.

---

# 17. Authorization

Semua operasi berikut hendaklah disekat kepada Superadmin:

```text
Create Skim
Edit Skim
Activate/Deactivate Skim

Create Grade
Edit Grade
Activate/Deactivate Grade

Create Position
Edit Position
Activate/Deactivate Position
```

Jangan hanya menyembunyikan button di UI.

Authorization mesti turut dilaksanakan di:

- Route
- Controller/Livewire action
- Server-side validation/policy

Gunakan mekanisme authorization yang telah digunakan oleh projek.

---

# 18. Audit & Data Integrity

Jika projek telah mempunyai audit log/activity log:

Rekodkan sekurang-kurangnya:

```text
Create
Update
Activate
Deactivate
```

Jika audit log belum wujud, jangan memasang sistem audit kompleks secara automatik tanpa keperluan. Laporkan sebagai cadangan enhancement.

---

# 19. Testing

AI Agent hendaklah menyediakan/kemas kini test yang relevan.

Minimum:

### Service Scheme

- Boleh create.
- Tidak boleh create duplicate code.
- Boleh update.
- Boleh deactivate.
- Tidak boleh digunakan untuk jawatan baharu apabila inactive.

### Grade

- Boleh create.
- Tidak boleh create duplicate code.
- Boleh update.
- Boleh deactivate.
- Tidak boleh digunakan untuk jawatan baharu apabila inactive.

### Position

- Boleh create dengan service scheme dan grade yang sah.
- Tidak boleh create dengan service scheme inactive.
- Tidak boleh create dengan grade inactive.
- Boleh update.
- Boleh deactivate.

### User

- Boleh assign position.
- Position inactive tidak boleh dipilih untuk pengguna baharu.
- User boleh mendapatkan service scheme dan grade melalui relationship position.

---

# 20. Acceptance Criteria

Modul dianggap siap apabila:

- [ ] Superadmin boleh melihat senarai Skim Perkhidmatan.
- [ ] Superadmin boleh tambah Skim Perkhidmatan.
- [ ] Superadmin boleh edit Skim Perkhidmatan.
- [ ] Superadmin boleh aktif/nonaktif Skim Perkhidmatan.
- [ ] Superadmin boleh melihat senarai Gred.
- [ ] Superadmin boleh tambah Gred.
- [ ] Superadmin boleh edit Gred.
- [ ] Superadmin boleh aktif/nonaktif Gred.
- [ ] Superadmin boleh melihat senarai Jawatan.
- [ ] Superadmin boleh tambah Jawatan.
- [ ] Superadmin boleh edit Jawatan.
- [ ] Superadmin boleh aktif/nonaktif Jawatan.
- [ ] Jawatan mempunyai relationship kepada Skim.
- [ ] Jawatan mempunyai relationship kepada Gred.
- [ ] Pengguna boleh mempunyai Jawatan.
- [ ] Skim dan Gred boleh diperoleh melalui relationship Jawatan.
- [ ] Role/permission sedia ada tidak rosak.
- [ ] Agensi sedia ada tidak rosak.
- [ ] Migration boleh dijalankan dengan berjaya.
- [ ] Seeder boleh dijalankan berulang kali tanpa duplicate.
- [ ] Validation server-side berfungsi.
- [ ] Authorization Superadmin berfungsi.
- [ ] Test berkaitan lulus.

---

# 21. Urutan Pelaksanaan Yang Dicadangkan

AI Agent hendaklah melaksanakan kerja mengikut urutan:

```text
PHASE 1
Analyse existing project
        ↓
PHASE 2
Design/confirm database integration
        ↓
PHASE 3
Create migrations
        ↓
PHASE 4
Create/update models & relationships
        ↓
PHASE 5
Create seeders
        ↓
PHASE 6
Implement authorization
        ↓
PHASE 7
Implement Skim Perkhidmatan CRUD
        ↓
PHASE 8
Implement Gred CRUD
        ↓
PHASE 9
Implement Jawatan CRUD
        ↓
PHASE 10
Integrate with User Management
        ↓
PHASE 11
Validation & error handling
        ↓
PHASE 12
Testing
        ↓
PHASE 13
Final review / regression check
```

---

# 22. Arahan Akhir Kepada AI Agent

Selepas membaca dokumen ini:

1. **Jangan terus coding.**
2. Analisis projek SPPICT v2 sedia ada terlebih dahulu.
3. Bentangkan ringkasan struktur yang ditemui.
4. Kenal pasti migration/model/component yang boleh digunakan semula.
5. Kenal pasti sebarang konflik dengan reka bentuk yang dicadangkan.
6. Jika terdapat konflik, utamakan struktur projek sedia ada dan laporkan cadangan penyelesaian.
7. Kemudian laksanakan perubahan secara berperingkat.
8. Selepas setiap fasa penting, jalankan pemeriksaan/test yang relevan.
9. Jangan mengubah functionality yang tidak berkaitan.
10. Jangan membuat andaian tentang nama rasmi jawatan atau gred yang tidak terdapat dalam sumber.
11. Gunakan data sumber sebagai initial master data.
12. Pastikan implementation mengikuti coding convention projek.
13. Pada akhir kerja, berikan ringkasan:
    - Files created
    - Files modified
    - Migrations added
    - Models added/modified
    - Routes added/modified
    - Components added/modified
    - Seeders added/modified
    - Tests added/modified
    - Commands executed
    - Test results
    - Any remaining issues

---

# 23. Nota Penting Untuk Fasa Seterusnya

Spesifikasi ini **tidak menetapkan nama rasmi jawatan bagi setiap skim/gred** kerana maklumat tersebut tidak terdapat dalam sumber yang diberikan.

Jika organisasi mempunyai sumber rasmi tambahan bagi:

```text
Skim Perkhidmatan
+
Gred
+
Nama Jawatan
```

sumber tersebut hendaklah digunakan sebelum mengisi master data Jawatan secara penuh.

Jangan menghasilkan nama jawatan secara andaian.

---

**End of Specification**
