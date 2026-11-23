# LAPORAN FASA UM7 — PENGERASAN KESELAMATAN DAN PENGUATKUASAAN AKSES

**Projek:** SPPICT v2
**Tarikh:** 2026-10-13
**Branch:** `feature/user-management`
**Rujukan:** `Prompt-UM7-Keselamatan-Penguatkusaan-Akses.md`, laporan UM0–UM6

---

## 1. Status Akhir

**BERJAYA DENGAN CATATAN**

Semua objektif UM7 dilaksanakan dan diuji (23 ujian keselamatan baharu; full suite 269/270, satu kegagalan baseline yang sama). Catatan:

1. **Polisi kata laluan kini lebih ketat** (min 12, huruf besar/kecil, nombor, simbol). Kata laluan sedia ada tidak terjejas; polisi hanya terpakai apabila kata laluan baharu ditetapkan. Perlu disahkan sebagai polisi rasmi (§14).
2. **`AuthenticateSession` diaktifkan pada kumpulan `web`.** Sesi lain terbatal apabila kata laluan berubah. Ini perubahan global (§9).
3. Beberapa isu dasar akses yang ditemui **tidak diubah** kerana memerlukan keputusan (§14).
4. Enam ujian sedia ada diselaraskan dengan perubahan yang disengajakan (§12).

---

## 2. Ringkasan Audit Awal dan Risiko

Baseline sebelum UM7: **247 ujian: 246 lulus, 1 gagal** (`ProfileTest::test_user_can_delete_their_account`). Branch `feature/user-management`; 42 entri belum di-commit (UM1–UM6).

| Risiko | Lokasi | Bukti | Pembaikan |
|---|---|---|---|
| Status aktif tidak disemak bagi tindakan Livewire | `AppServiceProvider` | Hanya `EnsurePasswordIsChanged` didaftar sebagai persistent middleware | `EnsureUserIsActive` didaftar sebagai persistent; 403 + log keluar bagi permintaan Livewire |
| Peranan route tidak disemak semula bagi tindakan Livewire | Semua komponen pada route `role:` / `not-role:` | Middleware Spatie `role` tidak persistent | `RoleMiddleware` & `EnsureUserDoesNotHaveRole` didaftar sebagai persistent |
| `AuditLog` tanpa sebarang guard | `app/Livewire/Admin/AuditLog.php` | Tiada `mount`/`ensure`; data audit dirender semula pada setiap `/livewire/update` | `boot()` guard (setiap permintaan) |
| `openModal`/`mount` tanpa guard | Grade/Position/ServiceScheme, MeetingName/Number/Year, MeetingList, MeetingForm | Tiada `ensure…()` dalam `openModal()`/`mount()` | `mount()` + `openModal()` dilindungi |
| Guard tempatan hanya semak peranan | Semua `ensure…()` kecuali UM4/UM5 | `abort_unless(hasRole(...))` | Semua guard kini `hasRole(...) && canUseSystem()` (aktif + tiada kata laluan sementara) |
| Kod mati dengan `save()` tanpa guard | `app/Livewire/Admin/PositionGradeManagement.php` | Tiada route; `save()` menulis Grade/Position tanpa semakan | `boot()` guard Superadmin; disyorkan dibuang (UM8) |
| Tiada had kadar: Self Register | `pages/auth/register` | Tiada `RateLimiter` | 10 / 10 minit / IP |
| Tiada had kadar: lupa kata laluan + **enumerasi akaun** | `pages/auth/forgot-password` | Mesej `__($status)` membezakan "tiada pengguna" vs "dihantar" | Had IP + e-mel dinormalisasi; mesej seragam |
| Enumerasi pada reset kata laluan | `pages/auth/reset-password` | Mesej berbeza untuk e-mel tidak wujud vs token tidak sah | Had IP; mesej seragam |
| Tiada had kadar: hantar semula pengesahan e-mel | `pages/auth/verify-email` | Action Volt tanpa had | 6 / minit / pengguna |
| Tiada had kadar: tukar kata laluan (tekaan kata laluan semasa) | `pages/auth/change-password` | Tiada had | 5 / 5 minit / pengguna |
| Tiada had kadar: reset oleh Superadmin | `Admin\UserManagement::resetPassword` | Tiada had | 3 / 10 minit / sasaran / Superadmin; 20 / 10 minit / Superadmin |
| Sesi lama kekal sah selepas tukar/reset kata laluan | Konfigurasi middleware | `AuthenticateSession` tidak digunakan; reset hanya memutar `remember_token` | `AuthenticateSession` pada kumpulan `web` |
| Polisi kata laluan lalai (min 8) | `Password::defaults()` tidak ditetapkan | Tiada konfigurasi | Polisi berpusat dalam `AppServiceProvider` |
| Kata laluan tetap `Password123!` | `RolesAndPermissionsSeeder` (3 akaun) | Dijalankan oleh `DatabaseSeeder` dalam **semua** persekitaran | Kata laluan rawak + `must_change_password`, akaun baharu sahaja |
| Route pengesahan e-mel tanpa `active` | `routes/auth.php` | Kumpulan `auth` + `password.changed` sahaja | `active` ditambah |

Kawalan yang **sudah baik** (disahkan, tidak diubah):

| Kawalan | Bukti |
|---|---|
| Login rate limit | `LoginForm`: 5 cubaan / 60 s, kunci `lower(email)\|ip` |
| `verification.verify` | `signed` + `throttle:6,1` + `EmailVerificationRequest` |
| Permohonan projek | `ApplicationForm::$applicationId` `#[Locked]`; `ApplicationSubmissionService::resolveApplication()` menyemak `ApplicationPolicy::update`; `deleteDocument` & `mount` guna `authorize` |
| Dokumen / minit | `ApplicationDocumentController` guna `Gate::authorize('view')`; route minit dilindungi `role:` |
| `ViewApplication` | `authorize('view')` di mount; `authorize('manageOfficers')` pada setiap tindakan pegawai |
| `ApplicationList` | Skop pemohon/pegawai aktif atau `SUBMITTED` bagi Urus Setia |

---

## 3. Komponen Livewire Yang Diaudit

| Kumpulan | Komponen | Status |
|---|---|---|
| Pengurusan Pengguna | `Admin\UserManagement`, `Users\RegisterUser`, `Users\RegistrationRequests` | Diaudit |
| Master data Superadmin | `Admin\GradeManagement`, `Admin\PositionManagement`, `Admin\ServiceSchemeManagement`, `Admin\PositionGradeManagement` (legacy), `Admin\AuditLog` | Diaudit |
| Master data Urus Setia | `Admin\AgencyManagement`, `MasterData\MasterDataManagement` (+ 4 subkelas) | Diaudit |
| Mesyuarat | `Meetings\MeetingForm`, `MeetingList`, `MeetingNameManagement`, `MeetingNumberManagement`, `MeetingYearManagement` | Diaudit |
| Permohonan | `ApplicationForm`, `ApplicationList`, `ViewApplication`, `Forms\ApplicationForm`, `ApplicationSubmissionService`, `OfficerHandoverService` (guard), `ApplicationPolicy` | Diaudit (authorization) |
| Auth / Profil (Volt) | `login`, `register`, `forgot-password`, `reset-password`, `verify-email`, `change-password`, `confirm-password`, `profile.update-profile-information-form`, `profile.update-password-form`, `profile.delete-user-form`, `layout.navigation` | Diaudit |
| **Tertangguh** | Logik perniagaan dalaman `ApplicationSubmissionService`/`OfficerHandoverService` (selain authorization), papan pemuka (`dashboard.blade.php`), templat e-mel notifikasi | Tidak diaudit secara mendalam dalam UM7 |

---

## 4. Fail Baharu dan Fail Diubah

### Baharu

| Fail | Tujuan |
|---|---|
| `app/Livewire/Concerns/RateLimitsActions.php` | Helper had kadar (kunci dinormalisasi, mesej BM) |
| `tests/Feature/SecurityHardeningTest.php` | 23 ujian UM7 |
| `docs/LAPORAN_FASA_UM7_PENGERASAN_KESELAMATAN.md` | Laporan ini |

### Diubah — kod

| Fail | Perubahan |
|---|---|
| `app/Providers/AppServiceProvider.php` | Persistent middleware: `EnsureUserIsActive`, `EnsurePasswordIsChanged`, `RoleMiddleware`, `EnsureUserDoesNotHaveRole`. Polisi `Password::defaults()` |
| `bootstrap/app.php` | `AuthenticateSession` ditambah pada kumpulan `web` |
| `app/Http/Middleware/EnsureUserIsActive.php` | Permintaan Livewire/JSON: log keluar + 403 (bukan redirect HTML) |
| `app/Models/User.php` | `canUseSystem()` (aktif && tiada kata laluan sementara) |
| `routes/auth.php` | `active` pada kumpulan `verify-email` / `verification.verify` / `confirm-password` |
| `app/Livewire/Admin/AuditLog.php` | `boot()` guard |
| `app/Livewire/Admin/PositionGradeManagement.php` | `boot()` guard (legacy) |
| `app/Livewire/Admin/{Grade,Position,ServiceScheme}Management.php` | `mount()` + guard dalam `openModal()`; `ensureSuperadmin()` + `canUseSystem()` |
| `app/Livewire/Admin/AgencyManagement.php`, `MasterData/MasterDataManagement.php` | `ensureUrusSetia()` + `canUseSystem()` |
| `app/Livewire/Meetings/{MeetingName,MeetingNumber,MeetingYear}Management.php` | `mount()` + guard dalam `openModal()`; `canUseSystem()` |
| `app/Livewire/Meetings/MeetingList.php` | `mount()` guard; `canUseSystem()` |
| `app/Livewire/Meetings/MeetingForm.php` | Guard dalam `mount()`; `canUseSystem()` |
| `app/Livewire/Users/RegisterUser.php`, `RegistrationRequests.php` | Guard guna `canUseSystem()` (setara dengan semakan UM4/UM6) |
| `app/Livewire/Admin/UserManagement.php` | Had kadar reset; guard guna `canUseSystem()` |
| `resources/views/livewire/admin/user-management.blade.php` | Paparan flash `error` (mesej had kadar) |
| `resources/views/livewire/pages/auth/register.blade.php` | Had kadar; paparan ralat `throttle`; mesej polisi BM |
| `resources/views/livewire/pages/auth/forgot-password.blade.php` | Had kadar; normalisasi e-mel; mesej seragam |
| `resources/views/livewire/pages/auth/reset-password.blade.php` | Had kadar; mesej ralat seragam |
| `resources/views/livewire/pages/auth/verify-email.blade.php` | Had kadar hantar semula; paparan ralat `throttle` |
| `resources/views/livewire/pages/auth/change-password.blade.php` | Had kadar; `Auth::setUser()` selepas simpan (selaras sesi); mesej polisi BM |
| `database/seeders/RolesAndPermissionsSeeder.php` | Kata laluan rawak + `must_change_password` bagi akaun baharu; tidak dijalankan |

### Diubah — ujian sedia ada (penyelarasan)

| Fail | Sebab |
|---|---|
| `tests/Feature/Auth/PasswordUpdateTest.php`, `PasswordResetTest.php` | Kata laluan baharu `new-password`/`password` → `KataLaluanBaru#2026` (polisi berpusat) |
| `tests/Feature/MeetingMasterDataTest.php` | `test_superadmin_cannot_call_actions_directly`: kini membuktikan sekatan pada `mount` **dan** pada `save` selepas peranan berubah |

---

## 5. Middleware Status Aktif dan Persistent Middleware

```
HTTP:      web[..., AuthenticateSession] → auth → verified → active → password.changed → role/not-role → route
Livewire:  web[..., AuthenticateSession] → (persistent, ikut route asal) auth → active → password.changed → role/not-role → action
                                                                        └─ lapisan kedua: ensure…() dalam action
```

- **`EnsureUserIsActive`:** status bukan `active` → log keluar + invalidate sesi. HTTP: redirect `/login` dengan mesej status. Livewire/JSON: `403`.
- **Persistent (`AppServiceProvider`):** Livewire menjalankan semula middleware ini bagi setiap `/livewire/update` mengikut route asal halaman. Susunan mengikut route. `auth` sudah persistent secara lalai dalam Livewire.
- **Tiada gelung redirect:** `/tukar-kata-laluan` tanpa `password.changed`. `/login`, `/register`, `/register/diterima`, `/forgot-password`, `/reset-password/*` dalam kumpulan `guest` tanpa `active`.
- **Semakan semula setiap permintaan:** guard sesi memuatkan semula rekod pengguna daripada DB pada setiap permintaan, jadi perubahan status/peranan/flag berkuat kuasa serta-merta (diuji dengan payload `/livewire/update` sebenar dan kawalan positif).
- **Kesan sampingan diterima:** jika status/peranan berubah semasa halaman dibuka, tindakan Livewire seterusnya (termasuk "Log Keluar" pada menu halaman tersebut) memulangkan 403. Memuat semula halaman akan melog keluar (status) atau mengalihkan pengguna (peranan).

---

## 6. Ringkasan Authorization Komponen

| Komponen/action | Risiko ditemui | Perubahan | Ujian |
|---|---|---|---|
| `Admin\UserManagement` (mount, openModal, saveUser, resetPassword) | — (UM5) | Guard `canUseSystem()`; had kadar reset | `SuperadminUserManagementTest`, `SecurityHardeningTest::test_ensure_guards_reject…`, `…rate_limited_per_target…`, `…status_changes_mid_session` |
| `Admin\AuditLog` (render/update) | Tiada guard | `boot()` | `test_audit_log_requires_active_superadmin_on_every_request`, `test_livewire_request_is_rejected_when_role_is_revoked_mid_session` |
| `Admin\Grade/Position/ServiceSchemeManagement` (mount, openModal, save, setStatus, delete) | `openModal`/`mount` tanpa guard; guard hanya peranan | `mount()`, guard `openModal`, `canUseSystem()` | `test_master_data_components_reject_non_superadmin…`, `test_ensure_guards_reject…`; `UserManagementReadinessTest` |
| `Admin\PositionGradeManagement` | Kod mati, `save()` tanpa guard | `boot()` guard | (tiada route; tidak boleh dimuatkan) |
| `Admin\AgencyManagement` | Guard hanya peranan | `canUseSystem()` | `AgencyManagementTest` |
| `MasterData\*` | Guard hanya peranan | `canUseSystem()` | `ApplicationMasterDataTest` |
| `Meetings\MeetingForm` (mount, save) | `mount` tanpa guard | Guard di `mount`, `canUseSystem()` | `test_meeting_pages_reject_non_urus_setia_on_mount`, `MeetingManagementTest` |
| `Meetings\MeetingList` (render, delete) | Tiada `mount` guard | `mount()` | `test_meeting_pages_reject…` |
| `Meetings\MeetingName/Number/YearManagement` | `openModal`/`mount` tanpa guard | `mount()`, guard `openModal` | `MeetingMasterDataTest` (diselaraskan) |
| `Users\RegisterUser`, `Users\RegistrationRequests` | — (UM4) | `canUseSystem()` (setara) | `UrusSetiaUserManagementTest` |
| `ApplicationForm`, `ViewApplication`, `ApplicationList` | Tiada isu authorization baharu (ID `#[Locked]`, polisi dalam servis) | Tiada perubahan | `ApplicationFormTest`, `ApplicationOfficerTest`, `ApplicationDocumentTest` |
| `profile.*`, `layout.navigation` | `delete-user-form` membenarkan semua peranan memadam akaun sendiri (soft delete) | **Tiada perubahan** (keputusan dasar, §14) | — |

Tiada sekatan peranan atau skop agensi baharu diperkenalkan; hanya penguatkuasaan dasar sedia ada pada setiap permintaan.

---

## 7. Rate Limiting

Semua had menggunakan `RateLimiter` Laravel (cache store aplikasi). Kunci tidak mengandungi kata laluan. Mesej: *"Terlalu banyak cubaan. Sila cuba semula dalam N saat/minit."*

| Endpoint | Had | Kunci | Sebab |
|---|---|---|---|
| Login (sedia ada, tidak diubah) | 5 / 60 s | `lower(email)\|ip` | Breeze |
| Self Register | 10 / 10 minit | IP | Membenarkan beberapa pembetulan validation; menyekat pendaftaran automatik |
| Lupa kata laluan | 10 / 15 minit (IP) **dan** 3 / 15 minit (e-mel) | IP; `lower(trim(email))` | Halang spam e-mel; normalisasi menghalang pintasan huruf besar/ruang. Broker Laravel turut 1 / 60 s / akaun |
| Reset kata laluan (hantar borang) | 10 / 15 minit | IP | Token tidak boleh diteka; had sebagai pertahanan tambahan |
| Hantar semula pengesahan e-mel | 6 / 60 s | ID pengguna | Sama seperti `verification.send` lalai Laravel |
| Tukar kata laluan wajib | 5 / 5 minit | ID pengguna | Menghalang meneka kata laluan semasa; di-reset selepas berjaya |
| Reset oleh Superadmin | 3 / 10 minit / sasaran **dan** 20 / 10 minit keseluruhan | `superadmin_id\|uuid_sasaran`; `superadmin_id` | Halang penyalahgunaan tanpa menyekat pentadbiran biasa (sasaran lain masih boleh direset). Apabila dihadkan: tiada perubahan, tiada kata laluan dipaparkan |

**Bukan enumerasi:** lupa kata laluan memaparkan mesej sama bagi e-mel berdaftar/tidak berdaftar/dihadkan broker. Reset kata laluan memaparkan mesej sama bagi e-mel tidak wujud/token tidak sah.

Diuji: had dicapai, normalisasi e-mel, dan pemulihan selepas tempoh tamat (`travel()`).

---

## 8. Polisi Kata Laluan

```php
Password::defaults(fn () => Password::min(12)->max(255)->letters()->mixedCase()->numbers()->symbols());
```

| Peraturan | Nilai |
|---|---|
| Panjang | 12–255 aksara |
| Huruf | Wajib, termasuk huruf besar **dan** kecil |
| Nombor | Wajib |
| Simbol | Wajib |
| `uncompromised()` | **Tidak** digunakan (memerlukan panggilan API luaran HIBP; tidak sesuai tanpa kelulusan rangkaian) |

**Digunakan oleh** (semua melalui `Password::defaults()`): Self Register (UM2), Tukar Kata Laluan wajib (UM6), reset melalui e-mel, dan kemas kini kata laluan di Profil.

**Tidak terpakai** kepada kata laluan sementara yang dijana sistem (`Str::password(12)` untuk UM4/UM5, `Str::password(16)` untuk seeder). Ini bukan input pengguna, dan pengguna wajib menukarnya kepada kata laluan yang mematuhi polisi.

Kata laluan sedia ada **tidak** diubah. Diuji: kegagalan polisi tidak mengubah kata laluan atau flag (`test_policy_is_applied_to_every_password_form_without_side_effects`).

---

## 9. Pengurusan Sesi

| Kawalan | Pelaksanaan |
|---|---|
| Mekanisme | `Illuminate\Session\Middleware\AuthenticateSession` pada kumpulan `web` (meliputi `/livewire/update`). Sesi menyimpan HMAC hash kata laluan dan dibatalkan jika hash pengguna berubah |
| Tukar kata laluan sendiri (UM6, Profil) | Sesi semasa **kekal**: middleware menyimpan hash baharu pada akhir permintaan yang sama. Halaman UM6 memanggil `Auth::setUser($user)` kerana menggunakan instance `User` berasingan. Sesi lain (peranti lain/kuki "Ingat saya") dibatalkan |
| Reset oleh Superadmin (UM5) | Semua sesi sasaran dibatalkan pada permintaan seterusnya; `remember_token` diputar; `must_change_password = true` |
| Reset melalui e-mel | Sesi lama dibatalkan (hash berubah); `remember_token` diputar (Breeze); flag dikosongkan (UM6) |
| `logoutOtherDevices()` | **Tidak digunakan.** Ia memerlukan kata laluan teks biasa dan mengehash semula (tidak sesuai untuk reset oleh Superadmin), manakala `AuthenticateSession` sudah memberikan kesan yang sama untuk semua aliran |
| Kesan kepada sesi sedia ada | Tiada log keluar serentak: sesi tanpa hash disimpan pada permintaan pertama |
| Superadmin reset kata laluan sendiri | Sesi Superadmin itu sendiri terbatal pada permintaan seterusnya (dijangka) |

Diuji: sesi lama selepas reset Superadmin → log masuk semula. Tukar kata laluan melalui HTTP + `/livewire/update` sebenar → sesi semasa kekal, sesi dengan hash lama dibatalkan.

---

## 10. Seeder dan Kata Laluan Tetap

### `RolesAndPermissionsSeeder`

| Soalan | Jawapan |
|---|---|
| Mencipta akaun contoh? | Ya: Superadmin, Urus Setia, Pemohon JKR |
| Mengubah akaun sedia ada? | **Tidak.** `firstOrCreate`: atribut hanya digunakan jika akaun belum wujud |
| Persekitaran | Dipanggil oleh `DatabaseSeeder` dalam **semua** persekitaran (hanya `MeetingSeeder` terhad kepada `local`) |
| Perubahan UM7 | `Password123!` → `Str::password(16)` bagi setiap akaun **baharu**, `must_change_password = true`, kata laluan dipaparkan **sekali** di konsol operator (`$this->command->warn`). Tidak dilog atau disimpan dalam teks biasa |
| Dijalankan? | **Tidak** terhadap database pembangunan. Hanya dalam ujian (SQLite in-memory) |

Ujian: akaun sedia ada tidak diubah; akaun baharu bukan `Password123!` dan wajib tukar; seeder dijalankan semula tidak mengubah kata laluan.

### Kata laluan tetap lain

| Lokasi | Jenis | Status |
|---|---|---|
| `app/`, `routes/`, `config/`, `resources/` | — | **Tiada** |
| `database/seeders/RolesAndPermissionsSeeder.php:188` | Komen dokumentasi sahaja | Bukan penggunaan |
| `database/factories/UserFactory.php` (`'password'`) | Fixture ujian | Disengajakan |
| `tests/**` (`KataLaluan…#2026`, `Sementara#2026`, `'password'`, assertion `assertNotSame('Password123!')`) | Fixture/assertion ujian | Disengajakan |
| `docs/LAPORAN_*` | Dokumentasi sejarah | Disengajakan |

---

## 11. Pengesahan E-mel dan SMTP (audit sahaja)

| Perkara | Status |
|---|---|
| `MustVerifyEmail` | **Tidak diaktifkan** (dikekalkan seperti UM6) |
| `MAIL_MAILER` | `log` (tidak diubah) |
| `email_verified_at` | Tidak diisi secara pukal |
| Route pengesahan | Kini `auth` + `active` + `password.changed`; `verification.verify` kekal `signed` + `throttle:6,1` |
| Hantar semula | **Had kadar ditambah** (6 / minit / pengguna) |

**Prasyarat sebelum pengaktifan** (fasa berasingan):
1. SMTP rasmi + `MAIL_FROM_ADDRESS` (oleh pentadbir sistem).
2. Keputusan backfill `email_verified_at` untuk 3 akaun sebenar.
3. Keputusan urutan pengesahan e-mel vs kelulusan Urus Setia.
4. Halaman `verify-email` dalam BM.
5. Ujian `Notification::fake()` untuk aliran penuh.

---

## 12. Ujian

### `Tests\Feature\SecurityHardeningTest`: 23 ujian, 23 lulus

| Kategori | Ujian |
|---|---|
| Status & middleware | `test_security_middleware_is_registered_as_livewire_persistent_middleware`, `test_active_user_can_use_internal_pages`, `test_guest_is_redirected_from_protected_pages`, `test_pending_and_rejected_sessions_are_logged_out_on_every_protected_route`, `test_livewire_action_is_rejected_when_status_changes_mid_session` (payload sebenar + kawalan), `test_livewire_request_is_rejected_when_role_is_revoked_mid_session` (payload sebenar + kawalan), `test_guest_cannot_replay_livewire_snapshot` |
| Authorization | `test_master_data_components_reject_non_superadmin_on_mount_and_actions`, `test_audit_log_requires_active_superadmin_on_every_request`, `test_meeting_pages_reject_non_urus_setia_on_mount`, `test_ensure_guards_reject_inactive_or_temporary_password_accounts` |
| Rate limiting | `test_self_register_is_rate_limited_per_ip_and_recovers`, `test_password_reset_request_is_limited_by_normalised_email_without_revealing_accounts`, `test_password_reset_submission_is_rate_limited`, `test_verification_resend_is_rate_limited`, `test_change_password_attempts_are_rate_limited`, `test_superadmin_password_reset_is_rate_limited_per_target_and_actor`, `test_login_rate_limit_uses_normalised_email` |
| Sesi | `test_old_session_is_invalidated_after_password_reset_by_superadmin`, `test_change_password_keeps_current_session_and_invalidates_other_sessions` |
| Polisi | `test_central_password_policy_is_configured`, `test_policy_is_applied_to_every_password_form_without_side_effects` |
| Seeder | `test_seeder_creates_accounts_with_random_temporary_passwords_and_keeps_existing_accounts` |

### Regresi dan perbandingan baseline

| | Sebelum UM7 | Selepas UM7 |
|---|---|---|
| Jumlah | 247 | 270 (+23) |
| Lulus | 246 | 269 |
| Gagal | 1 | 1 |
| Kegagalan | `ProfileTest::test_user_can_delete_their_account` | **Sama** (baseline; soft delete vs hard delete Breeze) |

Semasa pelaksanaan, tiga ujian sedia ada gagal akibat perubahan yang **disengajakan** dan telah diselaraskan (§4): `PasswordUpdateTest::test_password_can_be_updated`, `PasswordResetTest::test_password_can_be_reset_with_valid_token` (polisi kata laluan), dan `MeetingMasterDataTest::test_superadmin_cannot_call_actions_directly` (guard `mount`). **Tiada regresi baharu berbaki.**

Suite UM1 (`UserManagementReadinessTest`), UM2 (`RegistrationTest`), UM4 (`UrusSetiaUserManagementTest`), UM5 (`SuperadminUserManagementTest`), UM6 (`ForcedPasswordChangeTest`), Permohonan (`ApplicationFormTest`, `ApplicationOfficerTest`, `ApplicationDocumentTest`, `ApplicationMasterData*`), dan Mesyuarat (`MeetingManagementTest`, `MeetingMasterDataTest`, `JtictSubmissionWindowTest`) semuanya lulus.

---

## 13. Database Pembangunan dan Migrasi

- ✓ **Tiada migrasi baharu.** Migrasi terakhir kekal `2026_10_08_000002_add_user_management_columns_to_users_table.php` (UM1).
- ✓ Tiada migrasi atau seeder dijalankan terhadap `sppictv2_db`.
- ✓ Tiada perubahan kata laluan, status atau `email_verified_at` secara pukal.
- ✓ Semua ujian guna SQLite in-memory (`phpunit.xml`).

---

## 14. Keputusan Dasar Yang Masih Diperlukan

| # | Perkara | Keadaan semasa |
|---|---|---|
| 14.1 | **Polisi kata laluan rasmi** | Ditetapkan min 12 + huruf besar/kecil + nombor + simbol. Sahkan, atau nyatakan polisi jabatan (cth. tempoh luput, sejarah kata laluan; memerlukan migrasi) |
| 14.2 | **Akses membuat permohonan** | Route `/application/new` hanya `not-role:superadmin`: Urus Setia dan Pengurusan boleh mencipta permohonan melalui URL (menu disembunyikan). Tiada polisi `create` |
| 14.3 | **Agensi lalai permohonan** | `ApplicationSubmissionService::resolveApplication()` guna `agency_id ?? 1` jika pemohon tiada agensi (permohonan jatuh ke agensi id 1) |
| 14.4 | **Padam akaun sendiri** | `profile.delete-user-form` membenarkan semua peranan (termasuk Superadmin/Urus Setia) memadam (soft delete) akaun sendiri; berkait dengan kegagalan baseline `ProfileTest` |
| 14.5 | **Pengesahan e-mel** | §11 |
| 14.6 | **Komponen legacy `PositionGradeManagement`** | Tiada route; dikunci. Cadang dibuang |
| 14.7 | **Seeder dalam production** | Akaun contoh (Urus Setia/Pemohon JKR) masih dicipta dalam semua persekitaran (kini dengan kata laluan rawak). Perlukah dihadkan kepada `local`? |

---

## 15. Risiko Belum Selesai dan Cadangan UM8

| Risiko | Cadangan UM8 |
|---|---|
| Baseline `ProfileTest::test_user_can_delete_their_account` | Putuskan §14.4, kemudian selaraskan ujian/fungsi |
| Perubahan peranan (Spatie) tidak diaudit oleh OwenIt | Listener audit perubahan peranan |
| Tindakan Urus Setia (daftar pengguna, lulus/tolak) tanpa had kadar | Pertimbangkan jika diperlukan |
| Halaman Breeze `verify-email`, `confirm-password`, `forgot-password`, `reset-password`, profil masih sebahagiannya BI | Terjemahan BM |
| `SESSION_ENCRYPT=false`, `SESSION_SECURE_COOKIE` tidak ditetapkan | Semak tetapan production (`.env` oleh pentadbir) |
| Kolum legacy (`role_id`, `position`, `phone_number`, `positions.grade_id`) | Rancang pembersihan |
| Kerja UM1–UM7 belum di-commit (59 entri) | Commit berperingkat selepas semakan |
| Audit mendalam logik perniagaan permohonan/pegawai, papan pemuka, templat e-mel | Skop UM8 / fasa berasingan |

---

## 16. Pengesahan Kekangan Operasi

- ✓ Tiada `migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe`.
- ✓ Tiada `DROP`, `TRUNCATE` atau pemadaman pukal.
- ✓ Tiada seeder dijalankan terhadap database pembangunan.
- ✓ `.env`, SMTP dan pengesahan e-mel tidak diubah; `MustVerifyEmail` tidak diaktifkan.
- ✓ Tiada perubahan kata laluan/status akaun sedia ada secara pukal.
- ✓ Tiada migrasi baharu.
- ✓ **Tiada commit, push, merge, reset, stash atau pertukaran branch.** Branch kekal `feature/user-management`.
- ✓ Perubahan UM1–UM6 dalam working tree dikekalkan.
- ✓ Tiada perubahan polisi peranan atau skop agensi.
- ✓ `git diff --check` bersih; `git status --short`: 59 entri.

---

*UM7 selesai. Menunggu semakan laporan sebelum memulakan UM8 atau fasa seterusnya.*
