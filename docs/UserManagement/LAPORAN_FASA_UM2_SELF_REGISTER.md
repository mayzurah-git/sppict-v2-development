# LAPORAN FASA UM2 — SELF REGISTER

**Projek:** SPPICT v2
**Branch:** `feature/user-management` (lihat catatan di §1)
**Tarikh:** 2026-10-09
**Rujukan:** `docs/PROMPT-UM2-SELF REGISTER.md`, `docs/LAPORAN_FASA_UM0_AUDIT_PENGURUSAN_PENGGUNA.md`

---

## 1. Status Keseluruhan

**BERJAYA DENGAN CATATAN**

Self Register berfungsi sepenuhnya mengikut keputusan rasmi. Catatan:

- **Branch:** prompt menyebut `feature/application-module`, tetapi kerja dibuat pada `feature/user-management`. Branch ini dicipta daripada `feature/application-module` (commit `4585dfe`) atas permintaan pengguna sebelum UM1. Branch tidak ditukar, mengikut larangan menukar branch secara automatik.
- **Telefon wajib:** keperluan wajib/pilihan bagi telefon tidak dinyatakan dalam spesifikasi. Kedua-dua medan dijadikan **wajib**, selaras dengan borang semasa (`UserManagement` mewajibkan `phone_number`). Perlu disahkan (§14).
- Satu kegagalan ujian baseline (`ProfileTest::test_user_can_delete_their_account`) kekal. Ia dirancang untuk UM8.

---

## 2. Ringkasan Perubahan

- Borang Self Register ditulis semula: 11 medan dalam Bahasa Melayu, dan tiada pilihan Tahap Capaian.
- Dropdown dinamik **Jawatan → Skim Perkhidmatan (readonly) → Gred** menggunakan pivot `grade_position`.
- Validation server penuh: master aktif dan tidak dipadam, Gred mesti dipautkan dengan Jawatan, No. KP dan e-mel unik.
- Akaun baharu: role `pengguna_biasa` dan status `pending`, kedua-duanya ditetapkan oleh server dalam satu transaction.
- Pengguna **tidak** log masuk secara automatik. Mereka dihantar ke halaman "Pendaftaran Diterima".
- Log masuk disekat bagi akaun `pending` / `rejected`.
- Middleware baharu `active` disekat akses modul dalaman bagi sesi akaun yang tidak aktif.
- Pautan "Daftar di sini" ditambah pada halaman log masuk.
- 20 ujian Self Register (menggantikan 2 ujian templat Breeze).

---

## 3. Fail Dicipta dan Diubah

### Dicipta

| Fail | Tujuan |
|---|---|
| `app/Http/Middleware/EnsureUserIsActive.php` | Middleware `active`: log keluar dan redirect sesi akaun tidak aktif |
| `resources/views/livewire/pages/auth/registration-pending.blade.php` | Halaman "Pendaftaran Diterima" |
| `docs/LAPORAN_FASA_UM2_SELF_REGISTER.md` | Laporan ini |

### Diubah

| Fail | Perubahan |
|---|---|
| `resources/views/livewire/pages/auth/register.blade.php` | Borang, dropdown dinamik, validation, penyimpanan |
| `app/Models/Agency.php` | Scope `active()` |
| `app/Models/Position.php` | Scope `assignable()` (aktif + skim aktif + ≥1 gred sah) |
| `app/Models/Grade.php` | Scope `assignable()` (aktif + bukan legacy) |
| `app/Enums/UserStatus.php` | `inactiveMessage()` untuk mesej sekatan log masuk |
| `app/Livewire/Forms/LoginForm.php` | Sekat log masuk akaun tidak aktif |
| `bootstrap/app.php` | Alias middleware `active` |
| `routes/auth.php` | Route `register.pending` |
| `routes/web.php` | Middleware `active` pada `dashboard`, `profile` dan kumpulan route dalaman |
| `resources/views/livewire/pages/auth/login.blade.php` | Pautan "Daftar di sini" |
| `tests/Feature/Auth/RegistrationTest.php` | Ditulis semula: 20 ujian UM2 |

*Nota: fail UM1 (migration, `User.php`, `UserStatus.php`, `PositionManagement` dll.) juga belum di-commit dan masih kelihatan dalam `git status`.*

---

## 4. Aliran Self Register

```
Halaman Log Masuk ──► "Daftar di sini" ──► /register
        │
        ▼
Isi borang → pilih Jawatan → Skim dipaparkan + senarai Gred dimuat semula
        │
        ▼
Hantar ──► Validation server ──(gagal)──► mesej ralat BM, input selamat dikekalkan
        │
      (lulus)
        ▼
DB transaction: cipta User (status = pending) + assignRole('pengguna_biasa')
        │
        ▼
event Registered ──► redirect /register/diterima ("menunggu kelulusan Urus Setia")
        │
        ▼
Cuba log masuk ──► disekat: "Akaun anda sedang menunggu kelulusan Urus Setia."
        │
        ▼
(UM4) Urus Setia meluluskan → status = active → boleh log masuk
```

---

## 5. Medan Borang dan Sumber Data

| # | Medan | Jenis UI | Sumber / Simpanan | Peraturan |
|---|---|---|---|---|
| 1 | No. Kad Pengenalan | teks (numeric) | `users.no_kad_pengenalan` (varchar 12) | wajib, `digits:12`, unik (termasuk soft-deleted) |
| 2 | Nama Penuh | teks | `users.name` | wajib, max 255, ruang berlebihan dibuang (`Str::squish`) |
| 3 | Jabatan/Agensi | dropdown | `agencies` (`Agency::active()`) → `users.agency_id` | wajib, aktif, tidak dipadam |
| 4 | Jawatan | dropdown | `positions` (`Position::assignable()`) → `users.position_id` | wajib, aktif, skim aktif, ≥1 gred sah |
| 5 | Skim Perkhidmatan | readonly | `position->serviceScheme` | paparan sahaja, tidak dihantar/disimpan |
| 6 | Gred | dropdown bergantung | `position->grades()->assignable()` (pivot) → `users.grade_id` | wajib, aktif, dipautkan dengan Jawatan |
| 7 | No. Telefon Pejabat | teks (numeric) | `users.phone_office` | wajib, `^0\d{8,10}$` |
| 8 | No. Telefon HP | teks (numeric) | `users.phone_mobile` | wajib, `^0\d{8,10}$` |
| 9 | E-mel Rasmi | e-mel | `users.email` (lowercase) | wajib, format sah, unik tanpa mengira huruf |
| 10 | Kata Laluan | password | `users.password` (hash) | wajib, `Password::defaults()` |
| 11 | Pengesahan Kata Laluan | password | — | mesti sepadan |

Tiada medan Tahap Capaian / Role.

---

## 6. Dropdown Dinamik Jawatan → Skim → Gred

- `position_id` menggunakan `wire:model.live`. Setiap perubahan mencetuskan render semula.
- `updatedPositionId()` **mengosongkan `grade_id`** dan ralat berkaitan.
- `with()` **membaca semula Jawatan dari database** setiap render:
  ```php
  Position::assignable()->with('serviceScheme')->find($position_id)
  ```
  - Skim = `$position->serviceScheme` (readonly)
  - Gred = `$position->grades()->assignable()->orderByRaw('CAST(grades.code AS UNSIGNED)')`
- **`positions.grade_id` (legacy) tidak digunakan.**
- Jika Jawatan tiada Gred sah, dropdown Gred dinyahaktifkan dan mesej dipaparkan: *"Tiada Gred yang sah bagi Jawatan ini. Sila hubungi Urus Setia."*
- Jawatan legacy (id=1, tiada skim/gred) dan jawatan tanpa gred aktif tidak tersenarai kerana ditapis oleh `Position::assignable()`.

---

## 7. Validation Server dan Kawalan Manipulasi Request

| Kawalan | Pelaksanaan |
|---|---|
| Agensi aktif | `Rule::exists('agencies','id')->where(is_active=1, deleted_at IS NULL)` |
| Jawatan aktif | `Rule::exists('positions','id')->where(...)` + closure `Position::assignable()->whereKey()` (skim aktif, ≥1 gred sah) |
| Gred aktif | `Rule::exists('grades','id')->where(is_active=1, code NOT NULL, deleted_at IS NULL)` |
| Gred milik Jawatan | `Rule::exists('grade_position','grade_id')->where('position_id', $position_id)` |
| No. KP unik | `Rule::unique('users','no_kad_pengenalan')` + unique index DB |
| E-mel unik | closure `LOWER(email) = ?` termasuk soft-deleted, + unique index DB |
| Role | Tiada property `role`/`role_id` pada komponen. `assignRole('pengguna_biasa')` di server |
| Status / kelulusan | Tiada property `status`, `approved_by`, `approved_at`, `must_change_password`. Status ditetapkan dengan `forceFill`. Medan ini juga tidak dalam `$fillable` (UM1) |
| Mass assignment | `fill()` dengan senarai medan eksplisit daripada data tervalidasi sahaja |
| Hard-coded senarai | Tiada `Rule::in()`. Semua pilihan daripada database |

Cubaan menetapkan `role`, `role_id`, `status`, `approved_by`, `approved_at` atau `must_change_password` daripada client ditolak oleh Livewire (`PublicPropertyNotFoundException`). Perkara ini disahkan dalam ujian.

Mesej ralat dalam Bahasa Melayu disediakan melalui `messages()` dan `validationAttributes()`.

---

## 8. Role dan Status

| Perkara | Nilai |
|---|---|
| Role (Spatie `model_has_roles`) | `pengguna_biasa` |
| `users.status` | `pending` (`UserStatus::Pending`) |
| `approved_by` / `approved_at` | `NULL` |
| `must_change_password` | `false` (pengguna menetapkan password sendiri) |
| `users.role_id` (legacy) | `NULL`, tidak diisi. Spatie ialah sumber utama |
| `users.position` (legacy) | `NULL`. `ApplicationOfficer::snapshotFrom()` jatuh balik ke `position->title` |
| `users.phone_number` (legacy) | `NULL`. Snapshot pegawai mengutamakan `phone_mobile` (UM1) |
| `positions.grade_id` (legacy) | Tidak disentuh |

Role dan status tiga pengguna sedia ada tidak diubah.

---

## 9. Kawalan Akses Pengguna Belum Diluluskan

Tiga lapisan:

1. **Tiada log masuk automatik** selepas pendaftaran.
2. **`LoginForm::authenticate()`**: selepas kata laluan disahkan, akaun yang tidak `active` dilog keluar serta-merta dengan mesej:
   - `pending`: *"Akaun anda sedang menunggu kelulusan Urus Setia."*
   - `rejected`: *"Pendaftaran akaun anda tidak diluluskan. Sila hubungi Urus Setia."*
3. **Middleware `active`** (`EnsureUserIsActive`) pada `dashboard`, `profile` dan kumpulan route dalaman (`auth`, `verified`, `active`). Sesi yang tidak aktif (contohnya status ditukar semasa log masuk) dilog keluar dan dihantar ke log masuk. Role sahaja tidak dianggap bukti kelulusan.

**Tidak disekat:** `/login`, `/register`, `/register/diterima`, `/forgot-password`, `/reset-password`, `/verify-email`, `/confirm-password` dan logout.

**Kesan kepada role lain:** middleware hanya menyemak `status`. Semua pengguna sedia ada dan pengguna yang dicipta oleh Superadmin berstatus `active` (default DB + model), jadi Superadmin, Urus Setia, Pengurusan dan Pemohon sedia ada tidak terjejas. Disahkan oleh full suite (176/177).

**Bukan perubahan global:** middleware tidak ditambah pada kumpulan `web` dan tidak didaftar sebagai Livewire persistent middleware.

---

## 10. Email Verification dan Kebergantungan UM6

| Perkara | Keadaan |
|---|---|
| `User implements MustVerifyEmail` | ❌ Tidak (tidak diubah dalam UM2) |
| `event(new Registered($user))` | ✓ Dicetuskan. Listener Laravel tidak menghantar e-mel kerana model bukan `MustVerifyEmail` |
| `MAIL_MAILER` | `log` (tidak diubah) |
| Middleware `verified` | Masih tiada kesan (seperti UM0) |

**Kebergantungan UM6:** Self Register tidak bergantung pada e-mel pengesahan untuk berfungsi, kerana kawalan sebenar ialah kelulusan Urus Setia. Untuk mengaktifkan e-mel pengesahan dalam UM6, perkara berikut perlu diputuskan:
- Backfill `email_verified_at` untuk pengguna sedia ada.
- Urutan antara pengesahan e-mel dan kelulusan.
- Konfigurasi mail sebenar.

---

## 11. Ringkasan Ujian

| Suite | Ujian | Lulus | Gagal |
|---|---|---|---|
| `Tests\Feature\Auth\RegistrationTest` (UM2) | 20 | 20 | 0 |
| **Full suite** | **177** | **176** | **1** |

Senario yang diuji (rujuk §10 prompt):

| # | Senario | Ujian |
|---|---|---|
| 1 | Borang dipaparkan dengan medan yang diperlukan, tanpa role | `test_registration_screen_shows_required_fields_without_role_selection` |
| 2–5 | Pendaftaran sah, password hash, role `pengguna_biasa`, status `pending` | `test_valid_registration_creates_pending_pengguna_biasa_without_logging_in` |
| 6–7 | Manipulasi role/status ditolak | `test_role_status_and_approval_fields_cannot_be_set_by_client` |
| 8 | No. KP bukan 12 digit | `test_ic_number_must_be_exactly_twelve_digits` |
| 9 | No. KP pendua | `test_duplicate_ic_number_is_rejected` |
| 10 | E-mel pendua (tanpa mengira huruf, termasuk soft-deleted) | `test_duplicate_email_is_rejected_case_insensitively` |
| 11 | Agensi tidak aktif / dipadam | `test_inactive_or_deleted_agency_is_rejected` |
| 12 | Jawatan tidak aktif / dipadam | `test_inactive_or_deleted_position_is_rejected` |
| 13 | Gred tidak aktif / dipadam | `test_inactive_or_deleted_grade_is_rejected` |
| 14 | Gred bukan milik Jawatan | `test_grade_not_linked_to_selected_position_is_rejected` |
| 15 | Jawatan tanpa gred sah | `test_position_without_valid_grade_cannot_register` |
| 16 | Skim & Gred ikut Jawatan, Gred dikosongkan | `test_service_scheme_and_grades_follow_selected_position` |
| 17 | Pengesahan password tidak sepadan | `test_password_confirmation_must_match` |
| 18 | Akaun belum lulus tidak boleh log masuk / akses modul | `test_pending_or_rejected_user_cannot_log_in`, `test_pending_user_session_cannot_access_protected_modules` |
| 19 | Login, logout, halaman status berfungsi | `test_login_logout_and_status_pages_still_work_for_active_users`, `test_registration_pending_page_is_shown` |
| 20 | Pengguna & role sedia ada tidak terjejas | `test_registration_does_not_affect_existing_users_or_roles` |
| + | Master tidak aktif/dipadam tidak disenaraikan | `test_inactive_and_deleted_master_data_are_not_listed` |
| + | Format telefon | `test_phone_numbers_must_be_digits_only` |

Semua ujian menggunakan SQLite in-memory (`RefreshDatabase`). Data pembangunan tidak disentuh.

---

## 12. Kegagalan Ujian

| Ujian | Jenis | Catatan |
|---|---|---|
| `ProfileTest::test_user_can_delete_their_account` | **Baseline** (wujud sebelum UM1) | User guna soft delete; ujian Breeze menjangka hard delete. Dirancang UM8. Tidak diubah |

**Tiada kegagalan baharu.**

---

## 13. Perubahan Database

**Tiada.** UM2 menggunakan schema UM1 sepenuhnya:
- `users.no_kad_pengenalan`, `grade_id`, `phone_office`, `phone_mobile`, `status`
- pivot `grade_position`

Tiada migration dicipta atau dijalankan. Tiada seeder dijalankan.

---

## 14. Risiko dan Perkara Belum Selesai

| # | Perkara | Cadangan |
|---|---|---|
| 1 | **Telefon pejabat & HP dijadikan wajib** tanpa keputusan rasmi | Sahkan. Jika salah satu pilihan, tukar `required` → `nullable` (perubahan satu baris) |
| 2 | Format telefon `^0\d{8,10}$` (9–11 digit bermula 0) | Sahkan julat untuk nombor talian tetap / HP Malaysia |
| 3 | No. KP tidak menerima sengkang (`050101-05-1234` ditolak) | Ikut keputusan "digit sahaja". Boleh dilonggarkan dengan membuang sengkang secara automatik jika diputuskan |
| 4 | Tiada skrin kelulusan: akaun `pending` kekal tidak boleh digunakan sehingga UM4 | Dirancang UM4 |
| 5 | Tiada notifikasi kepada Urus Setia apabila ada pendaftaran baharu | Putuskan dalam UM4 |
| 6 | `UserManagement` (Superadmin) masih ada `Password123!` hard-coded dan tiada whitelist role | Di luar skop UM2. Dirancang UM4/UM5/UM7 |
| 7 | `RolesAndPermissionsSeeder` mencipta akaun contoh dengan `Password123!` | Di luar skop. Pertimbangkan dalam UM7 (contohnya hanya untuk `local`) |
| 8 | Middleware `active` tidak didaftar sebagai Livewire persistent middleware. Tindakan Livewire dalam halaman yang sudah dibuka tidak menyemak semula status sehingga muat semula halaman | Dirancang UM7 |
| 9 | Tiada rate limiting khusus pada borang Self Register | Pertimbangkan dalam UM7 |
| 10 | Halaman `welcome` masih memaparkan pautan "Register" (BI) | Kosmetik, di luar skop |
| 11 | Semua perubahan UM1 + UM2 belum di-commit | Menunggu arahan |

---

## 15. Pengesahan

- ✓ Tiada `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, rollback, DROP, TRUNCATE atau DELETE dijalankan.
- ✓ Tiada migration baharu dicipta atau dijalankan dalam UM2.
- ✓ Tiada seeder dijalankan. Data pembangunan dan tiga pengguna sedia ada tidak diubah.
- ✓ `.env` dan konfigurasi mail tidak diubah.
- ✓ Kolum legacy (`users.role_id`, `users.position`, `users.phone_number`, `positions.grade_id`) dikekalkan.
- ✓ Tiada password hard-coded ditambah (hanya kata laluan ujian dalam fail test).
- ✓ `git diff --check` bersih.
- ✓ **Tiada commit, push atau pertukaran branch dilakukan.**

---

*UM2 selesai. Menunggu semakan pengguna sebelum meneruskan UM3 / UM4 / UM5 / UM6 / UM7.*
