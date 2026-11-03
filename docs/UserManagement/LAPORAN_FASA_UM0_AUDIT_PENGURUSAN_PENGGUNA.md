# LAPORAN AUDIT FASA UM0 — MODUL PENGURUSAN PENGGUNA

**Projek:** SPPICT v2
**Branch:** `feature/application-module`
**Tarikh audit:** 2026-10-08
**Skop:** Audit sahaja. Tiada fail projek diubah. Database dibaca sahaja (`db:table` dan SELECT).

---

## Ringkasan

- Self Register sedia ada ialah templat asal Laravel Breeze: medan nama, e-mel dan kata laluan sahaja. Tiada role diberikan dan tiada e-mel pengesahan dihantar.
- Pengurusan Pengguna hanya boleh diakses oleh Superadmin. Urus Setia tiada akses.
- Jadual Position–Grade Association **tidak wujud**. Setiap Jawatan mempunyai **satu** Gred yang disimpan terus pada `positions.grade_id`.
- Terdapat 6 perkara yang perlu diputuskan sebelum implementasi (lihat §19).

---

## 1. Status Modul Semasa

| Komponen | Status |
|---|---|
| Self Register | Templat asal Breeze (Volt), tidak disesuaikan |
| Pendaftaran oleh Superadmin | Wujud (`app/Livewire/Admin/UserManagement.php`). Guna medan teks bebas, tiada master Jawatan/Gred |
| Pendaftaran oleh Urus Setia | Tiada. Route `admin.users` hanya untuk `role:superadmin` |
| Master Agensi / Jawatan / Skim / Gred | CRUD wujud dan siap. Belum disambungkan ke borang pengguna |
| Komponen legacy | `app/Livewire/Admin/PositionGradeManagement.php` tiada route (`/admin/positions-grades` diredirect). Ini kod mati |

---

## 2. Struktur Database (sebenar, `sppictv2_db`)

### `users` (3 rekod, ketiga-tiganya `email_verified_at = NULL`)

| Column | Type | Null | Constraint |
|---|---|---|---|
| id | bigint PK | ✗ | |
| uuid | char(36) | ✗ | unique |
| agency_id | bigint | ✓ | FK agencies, **ON DELETE CASCADE** |
| name | varchar(255) | ✗ | |
| email | varchar(255) | ✗ | unique |
| role_id | bigint | ✓ | FK roles, set null |
| position | varchar(255) | ✓ | teks legacy (cth. "Jurutera Awam (J44)") |
| position_id | bigint | ✓ | FK positions, set null (semua 3 rekod = NULL) |
| phone_number | varchar(255) | ✓ | satu medan sahaja |
| email_verified_at | timestamp | ✓ | |
| password, remember_token, timestamps, deleted_at | | | soft delete ✓ |

Medan yang diminta dalam keperluan:

| Medan | Ada? |
|---|---|
| `no_kad_pengenalan` | ❌ tiada |
| `grade_id` | ❌ tiada |
| `phone_office` / `phone_mobile` | ❌ tiada, hanya `phone_number` |
| `role_id` | ✓ ada, tetapi role sebenar diurus oleh Spatie `model_has_roles` (data semasa konsisten) |

### Master lain

| Table | PK / Unique | is_active (default) | Soft delete | FK | Data |
|---|---|---|---|---|---|
| agencies | id; uuid, code | ✓ (true) | ✓ | — | 6 rekod, semua aktif |
| service_schemes | id; uuid, code | ✓ (true) | ✓ | — | 18 skim (A–X), semua aktif |
| grades | id; uuid, code, name | ✓ (false) | ✓ | — | 19 rekod. 15 baharu (kod 1–15, aktif). 4 legacy (F5, N1–N3, `code=NULL`, tidak aktif) |
| positions | id; uuid, **title unique** | ✓ (false) | ✓ | service_scheme_id, grade_id (restrict) | 3 rekod. id=1 legacy tiada skim/gred dan tidak aktif |
| roles | id; (name, guard_name) | — | — | — | pengguna_biasa(1), pentadbir_urus_setia(2), pengurusan(3), superadmin(4) |
| password_reset_tokens | email PK | — | — | — | Breeze standard |

**Position–Grade Association: ❌ TIDAK WUJUD.** Tiada jadual pivot dalam database (disahkan melalui `information_schema`).

---

## 3. Model & Relationship

| Model | Relationship | Auditable |
|---|---|---|
| User | `agency()` belongsTo, `position()` / `positionRelation()` belongsTo Position, `role()` belongsTo Role, HasRoles (Spatie) | ❌ |
| Agency | `users()` hasMany | ❌ |
| Position | `serviceScheme()` belongsTo, `grade()` belongsTo, `users()` hasMany | ✓ |
| ServiceScheme | `positions()` hasMany | ✓ |
| Grade | `positions()` hasMany | ✓ |

`User::$fillable` mengandungi `role_id`, `agency_id` dan `position_id`. Ini risiko mass assignment (lihat §15).

---

## 4. Self Register Semasa

Fail: `resources/views/livewire/pages/auth/register.blade.php`, route `register` (middleware `guest`). Pautan "Register" dipaparkan pada laman welcome.

- **Medan:** name, email, password, password_confirmation. Tiada medan lain.
- **Role:** ❌ tiada `assignRole`. Pengguna baharu tiada role langsung.
- **Agensi:** tiada.
- Selepas daftar, pengguna terus `Auth::login` dan diredirect ke dashboard.
- ⚠ **Risiko:** route `/application/new` hanya ada `not-role:superadmin`. Pengguna yang mendaftar sendiri (tanpa role, tanpa agensi) **boleh membuka borang permohonan**.

---

## 5. Pendaftaran oleh Urus Setia

**MISSING.** Route `admin.users` → `role:superadmin`. Menu hanya dipaparkan dalam `@hasrole('superadmin')`. Permission `manage-users` diberikan kepada Urus Setia dalam seeder, tetapi **tidak digunakan di mana-mana**.

---

## 6. Pendaftaran oleh Superadmin

Fail: `app/Livewire/Admin/UserManagement.php` dan `resources/views/livewire/admin/user-management.blade.php`

- Dropdown role dalam UI: Pemohon Agensi, Urus Setia, Superadmin. **Pengurusan tiada.**
- Validation server: `role` → `exists:roles,name`. Mana-mana role diterima, tiada whitelist.
- Keperluan baharu hanya membenarkan Urus Setia dan Superadmin. Sistem semasa juga membenarkan Pengguna Biasa.
- Medan `role` dihantar dalam `$data` tetapi tiada dalam `$fillable`, jadi diabaikan. `users.role_id` **tidak dikemas kini** dan akan berbeza daripada Spatie selepas edit.
- Tiada `ensureSuperadmin()` dalam `saveUser()` / `resetPassword()`. Komponen master lain ada semakan ini.

---

## 7. Role Matrix (implementasi sebenar)

| Actor | Self Register | Pengguna Biasa | Urus Setia | Pengurusan | Superadmin |
|---|---|---|---|---|---|
| Pemohon (guest) | ✓ *(tanpa role)* | ✗ | ✗ | ✗ | ✗ |
| Urus Setia | — | ✗ | ✗ | ✗ | ✗ |
| Superadmin | — | ✓ | ✓ | ✗ UI / ✓ melalui request manipulation | ✓ |

**Matrix sasaran (keperluan):**

| Actor | Self Register | Pengguna Biasa | Urus Setia | Pengurusan | Superadmin |
|---|---|---|---|---|---|
| Pemohon | ✓ (role default) | ✗ | ✗ | ✗ | ✗ |
| Urus Setia | — | ✓ | ✓ | ✓ | ✗ |
| Superadmin | — | ✗ | ✓ | ✗ | ✓ |

---

## 8. Master Data Dependency

| Soalan | Jawapan sebenar |
|---|---|
| Position bergantung kepada Agency? | **Tidak.** `positions` tiada `agency_id`. Jawatan adalah global |
| Service Scheme bergantung kepada Position? | **Ya**, `positions.service_scheme_id` (1 skim bagi setiap jawatan) |
| Grade bergantung kepada Position? | **Ya, 1:1**, `positions.grade_id` |
| Position–Grade Association digunakan? | **Tidak wujud** |
| Tukar Position → pilihan Grade berubah? | Tiada UI. Borang pengguna tidak guna Position langsung |
| Skim readonly / display-only? | Tiada UI |
| Query semula bila Position berubah? | Tiada |

---

## 9. Position → Service Scheme → Grade

- **A. Position → Scheme:** **satu** skim sahaja (belongsTo).
- **B. Position → Grade:** **disimpan terus** pada Position (`grade_id`). Tiada association.
- **C. User → Position:** `users.position_id` wujud tetapi **tidak digunakan oleh mana-mana borang**. Borang admin guna teks bebas `position` ("Jawatan & Gred").
- **D. User → Grade:** tiada medan. Gred hanya boleh dibaca secara tidak langsung melalui `user->position->grade`.

⚠ **Konflik dengan keperluan.** "Gred dropdown berdasarkan Jawatan + association" memerlukan satu Jawatan boleh mempunyai banyak Gred. Struktur semasa (title unique + satu `grade_id`) bermakna dropdown Gred akan sentiasa mengandungi tepat satu pilihan. Dokumen `docs/SUPERADMIN-JAWATAN-SKIM-GRED-SPEC.md` memang menetapkan `Position belongsTo Grade`. **NEEDS DECISION.**

---

## 10. Form / Livewire / Blade

| Field | Self Register | Urus Setia | Superadmin (UserManagement) |
|---|---|---|---|
| No Kad Pengenalan | ✗ | ✗ | ✗ |
| Nama | ✓ | ✗ | ✓ |
| Jabatan/Agensi | ✗ | ✗ | ✓ dropdown (semua agensi, termasuk tidak aktif) |
| Jawatan | ✗ | ✗ | ⚠ teks bebas, bukan master |
| Skim Perkhidmatan | ✗ | ✗ | ✗ |
| Gred | ✗ | ✗ | ⚠ digabung dalam teks "Jawatan & Gred" |
| Telefon Pejabat | ✗ | ✗ | ⚠ satu medan "No. Telefon" |
| Telefon HP | ✗ | ✗ | (sama seperti di atas) |
| E-mel Rasmi | ✓ | ✗ | ✓ |
| Tahap Capaian | ✗ (tiada, betul) | ✗ | ✓ (hard-coded 3 pilihan) |
| Kata laluan | ✓ + pengesahan | ✗ | ✓ (admin taip) |

Fail lain yang berkaitan:

- `resources/views/livewire/layout/navigation.blade.php`
- `resources/views/livewire/profile/update-profile-information-form.blade.php` (nama dan e-mel sahaja)
- `routes/auth.php`
- `app/Http/Controllers/Auth/VerifyEmailController.php`
- `app/Http/Middleware/EnsureUserDoesNotHaveRole.php`

---

## 11. Validation

| Medan | Self Register | UserManagement |
|---|---|---|
| IC | — | — |
| name | required, max:255 | required, max:255 |
| email | required, lowercase, email, unique | required, email, unique (ignore id). **Tiada `lowercase`** |
| agency | — | `exists:agencies,id`. **Tidak semak `is_active` / `deleted_at`** |
| position | — | string bebas |
| grade / scheme | — | — |
| phone | — | `string max:50`. Tiada format nombor |
| role | — | `exists:roles,name`. **Tiada whitelist** |
| password | `Password::defaults()` | `min:8` sahaja |

Komponen master (`PositionManagement`) sudah guna `Rule::exists(...)->where('is_active', true)->whereNull('deleted_at')`. Corak ini boleh diguna semula untuk borang pengguna.

### Isu "data type: number" (NEEDS DECISION — tidak diubah)

- **No KP:** nombor 12 digit. Nombor KP bagi yang lahir tahun 2000-an bermula dengan `0` (cth. `050101…`). Jika disimpan sebagai integer/bigint, sifar di depan akan hilang.
- **Telefon:** nombor telefon Malaysia bermula dengan `0` (cth. `06…`, `01…`). Masalah yang sama berlaku.
- Pilihan untuk diputuskan: input "digit sahaja" (validation `digits:12` / regex), tetapi kolum disimpan sebagai string.

---

## 12. Email Verification

| Item | Status |
|---|---|
| `email_verified_at` | ✓ |
| Route `verification.notice` / `verification.verify` (signed, throttle) | ✓ |
| Halaman verify-email + butang resend | ✓ (teks Bahasa Inggeris, templat Breeze) |
| Middleware `verified` pada route | ✓ digunakan |
| **`User implements MustVerifyEmail`** | ❌ **tiada** |
| Notification dihantar | ❌ (listener `Registered` hanya menghantar jika model MustVerifyEmail) |
| Mail | `MAIL_MAILER=log`, `MAIL_FROM_ADDRESS=hello@example.com` |

Akibatnya, middleware `verified` **tidak berkesan** (EnsureEmailIsVerified hanya menyemak model MustVerifyEmail). Ketiga-tiga pengguna sedia ada belum disahkan.

⚠ Jika MustVerifyEmail diaktifkan tanpa backfill data, semua pengguna sedia ada dan pengguna yang didaftar admin akan tersekat daripada dashboard.

---

## 13. Password / Activation

- Self Register: pengguna tetapkan kata laluan sendiri.
- Admin: Superadmin taip kata laluan secara manual. Tidak dihantar melalui e-mel. Tiada activation link.
- **Reset Pass:** kata laluan diset ke `'Password123!'` (hard-coded) dan dipaparkan dalam flash message. Ini risiko keselamatan.
- Forgot password (Breeze) wujud, tetapi bergantung kepada mail yang kini ditetapkan ke `log`.
- **NEEDS DECISION:** mekanisme kata laluan untuk Self Register dan pengguna yang didaftar admin (admin set / jana + e-mel / pautan set kata laluan).

---

## 14. User Status

Tiada kolum status: tiada active, pending, approved, rejected atau locked. Yang ada hanya soft delete (`deleted_at`). "Pengguna aktif" = tidak soft-deleted. Self Register terus aktif selepas daftar, tanpa kelulusan.

**NEEDS DECISION:** adakah Self Register perlu kelulusan Urus Setia?

---

## 15. Authorization & Security

| Risiko | Penemuan | Tahap |
|---|---|---|
| Mass assignment | `role_id`, `agency_id`, `position_id` dalam `$fillable`. Semasa selamat kerana borang menghantar medan tertentu sahaja. Berisiko jika borang baharu guna `$this->all()` / `fill($validated)` | Sederhana |
| Role tampering | `exists:roles,name` tanpa whitelist. Bila Urus Setia diberi akses, mereka boleh menghantar `role=superadmin` | **Tinggi** (untuk keperluan baharu) |
| `$userId` tidak `#[Locked]` | Bila Urus Setia diberi akses, mereka boleh mengedit akaun Superadmin dengan menukar `userId` | **Tinggi** (untuk keperluan baharu) |
| Tiada semakan role dalam action | Spatie `role` middleware bukan persistent middleware Livewire. `saveUser` / `resetPassword` tiada `abort_unless` | Sederhana |
| Agency tampering | Self Register akan datang: `agency_id` perlu `exists` + aktif. Profil semasa tidak membenarkan tukar agensi (✓) | — |
| Position/Grade tampering | Belum relevan (tiada medan). Perlu semak grade sah untuk position yang dipilih | — |
| Duplicate e-mel | ✓ unique DB + validation | — |
| Duplicate IC | Tiada kolum | — |
| Pengguna tanpa role boleh buka `/application/new` | Ya | **Tinggi** (bila Self Register dibuka) |
| `users.agency_id` ON DELETE CASCADE | Hard delete agensi akan memadam pengguna. Semasa ini agensi hanya soft delete | Rendah |
| Bug carian | `where name OR email` tanpa kurungan dalam `render()`, jadi `roleFilter` terpintas bila carian diisi | Rendah |

---

## 16. Audit Trail

`owen-it/laravel-auditing ^14` dipasang (tiada `config/audit.php`, guna default). **User dan Agency tidak Auditable** — 0 rekod audit untuk User. Perubahan role melalui Spatie (`syncRoles`) juga tidak diaudit. Position, Grade dan ServiceScheme diaudit.

---

## 17. Tests

| Test | Kesan keperluan baharu |
|---|---|
| `tests/Feature/Auth/RegistrationTest.php` | **Akan gagal.** Medan wajib baharu; redirect ke dashboard mungkin berubah jika verification diaktifkan |
| `tests/Feature/Auth/EmailVerificationTest.php` | Kemungkinan kekal lulus |
| `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/ProfileTest.php` | Mungkin terjejas jika MustVerifyEmail atau medan wajib ditambah |
| `tests/Feature/AgencyManagementTest.php` | Tidak terjejas |
| UserManagement / Position / Grade / ServiceScheme / sekatan role | ❌ **Tiada test** |

`UserFactory` tiada state agensi, jawatan atau IC.

---

## 18. Hard-coded Data

- Pilihan role dalam dropdown, penapis dan badge (`user-management.blade.php`). Role `pengurusan` dipaparkan sebagai "Pemohon".
- Kata laluan reset `'Password123!'`.
- Nama role sebagai string literal di banyak tempat (routes, navigation, `Meeting::MEMBER_ROLES`).
- Kod legacy `getKey() === 1` dalam `PositionManagement`.
- Akaun dan agensi contoh dalam `RolesAndPermissionsSeeder`.

---

## 19. Gap Analysis

| Requirement | Status | Lokasi | Gap | Cadangan |
|---|---|---|---|---|
| Self Register: No KP | MISSING / NEEDS DECISION | users | Tiada kolum. Isu sifar di depan | Putuskan jenis storan. Tambah kolum unique |
| Self Register: Nama, E-mel | PARTIAL | register.blade.php | Ada, tetapi templat BI | Sesuaikan |
| Self Register: Agensi | MISSING | register | — | Dropdown `agencies` aktif |
| Self Register: Jawatan | MISSING | register | — | Dropdown `positions` aktif (kecuali legacy) |
| Skim (derived) | MISSING | — | Relationship tersedia | Paparan readonly `position->serviceScheme` |
| Gred ikut Jawatan + association | **NEEDS DECISION** | positions.grade_id | Tiada association. 1 Jawatan = 1 Gred | Pilih model 1:1 atau pivot |
| Telefon Pejabat / HP | MISSING / NEEDS DECISION | users.phone_number | Satu medan sahaja | Putuskan pecahan medan + format |
| E-mel pengesahan | PARTIAL | User.php | Tiada MustVerifyEmail. Mail = log | Aktifkan + backfill pengguna sedia ada |
| Role default Self Register | **NEEDS DECISION** | register | Tiada role diberikan | Tetapkan (cth. `pengguna_biasa`) |
| Urus Setia daftar Pengguna Biasa / Urus Setia / Pengurusan | MISSING | routes, UserManagement | Tiada akses | Route + whitelist role server-side |
| Superadmin daftar Urus Setia / Superadmin sahaja | PARTIAL | UserManagement | Pengguna Biasa juga boleh dipilih. Tiada whitelist | Whitelist mengikut actor |
| Validation master aktif | MISSING | UserManagement | Tiada semakan `is_active` | `Rule::exists()->where(...)` |
| Status / kelulusan | NEEDS DECISION | users | Tiada kolum | — |
| Password / activation | NEEDS DECISION | — | — | — |
| Audit User | MISSING | User.php | Tidak Auditable | Tambah Auditable + log perubahan role |
| Tests | MISSING | tests/ | Tiada test pengurusan pengguna | Tambah |

### Perkara yang perlu diputuskan

1. Jenis storan No KP dan telefon.
2. Model Jawatan–Gred: 1:1 atau many-to-many.
3. Role default untuk Self Register.
4. Mekanisme kata laluan / aktivasi.
5. Sama ada Self Register perlu kelulusan (status).
6. Nasib `users.role_id` dan teks legacy `users.position` — kekalkan atau dibuang? Teks `position` masih dipakai oleh `ApplicationOfficer` sebagai fallback.

---

## 20. Fail Yang Dicadangkan Untuk Diubah

| Fail | Sebab |
|---|---|
| `app/Models/User.php` | MustVerifyEmail, Auditable, fillable / relationship baharu |
| `resources/views/livewire/pages/auth/register.blade.php` | Medan baharu, dependency, role default |
| `app/Livewire/Admin/UserManagement.php` + blade | Master dropdown, whitelist role mengikut actor, `#[Locked]`, authorization dalam action, buang kata laluan hard-coded |
| `routes/web.php` | Akses `admin.users` untuk Urus Setia; halang pengguna tanpa role/agensi di `/application/new` |
| `resources/views/livewire/layout/navigation.blade.php` | Menu untuk Urus Setia |
| `resources/views/livewire/pages/auth/verify-email.blade.php` | Teks BM |
| `app/Models/Position.php` / `app/Models/Grade.php` | Hanya jika pivot dipilih |
| `database/factories/UserFactory.php`, `tests/Feature/Auth/RegistrationTest.php` + test baharu | Regression |
| `.env` | Konfigurasi mail sebenar (oleh pemilik sistem) |

---

## 21. Database Change Required

**Ada.** Tiada migration dicipta dalam fasa ini. Keperluan:

- `users.no_kad_pengenalan` — unique, nullable untuk 3 rekod sedia ada.
- Telefon pejabat dan telefon HP — tambah kolum atau guna semula `phone_number`.
- **Jika pivot dipilih:** jadual `grade_position` + `users.grade_id` (FK grades).
- **Jika status/kelulusan dipilih:** kolum status pada `users`.
- **Backfill data** (keputusan): `email_verified_at` untuk 3 pengguna sedia ada, dan `position_id` daripada teks legacy.

---

## 22. Cadangan Fasa Seterusnya

| Fasa | Skop |
|---|---|
| **UM1** | Keputusan + Database / Model readiness: kolum, pivot (jika dipilih), relationship, Auditable |
| **UM2** | Self Register: borang, role default, validation master aktif |
| **UM3** | Dependency dinamik Jawatan → Skim (readonly) → Gred |
| **UM4** | Pendaftaran oleh Urus Setia: route, whitelist role, halang edit akaun Superadmin |
| **UM5** | Pendaftaran oleh Superadmin: whitelist Urus Setia / Superadmin, guna semula borang |
| **UM6** | Email Verification / Activation: MustVerifyEmail, backfill, mail, kata laluan |
| **UM7** | Pengerasan authorization: `#[Locked]`, semakan dalam action, halang pengguna tanpa role, bug carian |
| **UM8** | Testing & Regression |

---

## Lampiran: Keadaan Git

- **Branch:** `feature/application-module`
- **Staged:** tiada
- **Modified:** 44 fail
- **Untracked:** kira-kira 60 item, termasuk modul Mesyuarat, Master Data dan migration bulan 09–10 yang belum di-commit

Kerja modul pengguna akan bercampur dengan perubahan besar yang belum di-commit ini. Disyorkan commit atau asingkan dahulu sebelum UM1.
