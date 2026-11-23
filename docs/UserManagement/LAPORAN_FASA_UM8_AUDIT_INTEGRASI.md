# LAPORAN FASA UM8 — AUDIT INTEGRASI DAN PENGUJIAN MENYELURUH MODUL PENGURUSAN PENGGUNA

**Projek:** SPPICT v2
**Tarikh:** 2026-10-13
**Branch:** `feature/user-management`
**Rujukan:** `Prompt-UM8-Audit-Integrasi-Pengujian-Menyeluruh-ModulPengurusanPengguna.md`; laporan UM0, UM2, UM4, UM5, UM6, UM7, UM7A

---

## 1. Ringkasan Eksekutif

**Status: SEDIA UNTUK PENERIMAAN — BERSYARAT**

- Semua aliran utama Modul Pengurusan Pengguna telah dipetakan dan diuji secara automatik melalui laluan HTTP, `/livewire/update` sebenar dan ujian komponen. Aliran yang diuji: pendaftaran kendiri, kelulusan/penolakan, penciptaan akaun oleh Urus Setia dan Superadmin, tukar kata laluan wajib, status akaun, Profil, data master dan sumber peranan.
- **Keputusan ujian: 303 ujian — 303 lulus, 0 gagal, 0 baseline** (1,995 assertion).
- **Matriks akses** 13 route × 8 jenis pelaku (tetamu, pending, rejected, kata laluan sementara, `pengguna_biasa`, `pentadbir_urus_setia`, `pengurusan`, `superadmin`) sepadan sepenuhnya dengan dasar UM4–UM7A.
- **Tiga kecacatan** ditemui dan dibaiki (tiada yang kritikal): ketidakselarasan semakan e-mel Profil (Sederhana), teks UI bahasa Inggeris pada halaman modul (Rendah), dan butang Daftar tanpa keadaan loading (Rendah).
- Data pembangunan (baca sahaja) konsisten: semua pengguna mempunyai agensi aktif, Gred dalam pivot jawatan dan peranan Spatie; tiada audit mengandungi kata laluan.
- Polisi UM7A dikekalkan. Pengesahan e-mel wajib **tidak** diaktifkan.

**Syarat penerimaan:** semakan manual paparan (desktop & skrin kecil) oleh pemilik sistem (§15) kerana Agent tidak menjalankan ujian pelayar/visual, serta keputusan bagi isu dasar terbuka (§12) yang **tidak** menghalang fungsi semasa.

---

## 2. Versi Aplikasi dan Persekitaran

| Perkara | Nilai |
|---|---|
| Laravel | 13.23.0 (`laravel/framework ^13.8`) |
| PHP | 8.4.25 (CLI, Windows / Herd) |
| Livewire / Volt | `^3.6.4` / `^1.7.0` |
| Spatie Permission | `^8.3` |
| OwenIt Auditing | `^14.0` |
| DB pembangunan | MySQL `sppictv2_db` (**baca sahaja** dalam UM8) |
| DB ujian | SQLite `:memory:` (`phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `APP_ENV=testing`, `MAIL_MAILER=array`). Disahkan sebelum menjalankan ujian |
| Git | `feature/user-management` @ `4585dfe`; 85 entri belum di-commit (UM1–UM8), dikekalkan |
| Migrasi terakhir | `2026_10_08_000002_add_user_management_columns_to_users_table.php` (UM1) |

---

## 3. Skop Audit dan Fail Diperiksa

| Lapisan | Fail |
|---|---|
| Route | `routes/web.php`, `routes/auth.php` (29 route aplikasi disenaraikan dengan `route:list`) |
| Middleware / bootstrap | `bootstrap/app.php`, `EnsureUserIsActive`, `EnsurePasswordIsChanged`, `EnsureUserDoesNotHaveRole`, `AuthenticateSession`, `AppServiceProvider` (persistent middleware, `Password::defaults()`) |
| Model / enum | `User`, `Agency`, `Position`, `Grade`, `ServiceScheme`, `ApplicationOfficer`, `Meeting`, `UserStatus` |
| Policy | `ApplicationPolicy` (`create`, `view`, `update`, `manageOfficers`, `updateProgress`) |
| Livewire | `Admin\UserManagement`, `Admin\{Grade,Position,ServiceScheme,Agency}Management`, `Admin\AuditLog`, `Users\RegisterUser`, `Users\RegistrationRequests`, `ApplicationForm`, `Meetings\*`, `MasterData\*`, trait `HandlesUserProfileFields`, `RateLimitsActions` |
| Volt | `pages.auth.{login,register,registration-pending,change-password,forgot-password,reset-password,verify-email,confirm-password}`, `profile.{update-profile-information-form,update-password-form}`, `layout.navigation` |
| Servis / sokongan | `ApplicationSubmissionService`, `App\Support\TemporaryPassword`, `PasswordPolicyMessages`, `CreateSuperadmin` |
| Migrasi | `users`, `grade_position`, `positions`, `grades`, `service_schemes` |
| Seeder | `DatabaseSeeder`, `RolesAndPermissionsSeeder`, `DemoAccountsSeeder`, `GradeSeeder` |
| Ujian | 30 fail ujian Feature |
| Data (SELECT sahaja) | `users`, `model_has_roles`, `roles`, `grade_position`, `positions`, `grades`, `service_schemes`, `agencies`, `audits` |

---

## 4. Aliran Pengguna dan Keputusan Pengujian

| # | Aliran | Disahkan | Ujian utama | Keputusan |
|---|---|---|---|---|
| 4.1 | Pendaftaran kendiri | Medan sebenar; agensi aktif sahaja; Gred ditapis pivot; Skim readonly dari Jawatan; No. KP 12 digit & telefon `^0\d{8,10}$`; unik (case-insensitive, termasuk soft-deleted); peranan `pengguna_biasa`; status `pending`; `approved_*` NULL; tiada log masuk automatik; mesej status; manipulasi role/status ditolak | `RegistrationTest` (20), `UserModuleIntegrationTest::test_self_register_to_approval_to_application_flow` | ✅ Lulus |
| 4.2 | Semakan Urus Setia | Akses Urus Setia sahaja; lulus/tolak; tiada proses semula; tiada lulus kendiri; transaksi + `lockForUpdate`; rekod ditolak dikekalkan; diluluskan → aktif & boleh log masuk; ditolak → tiada akses; UUID dikunci | `UrusSetiaUserManagementTest` (27), `test_rejected_applicant_never_gets_access` | ✅ Lulus |
| 4.3 | Cipta akaun oleh Urus Setia | Whitelist `pengguna_biasa`/`pentadbir_urus_setia`/`pengurusan`; master data sah; kata laluan sementara rawak mematuhi polisi; `must_change_password`; tiada kata laluan dalam audit; manipulasi peranan ditolak | `UrusSetiaUserManagementTest`, `PolicyDecisionsTest`, `test_urus_setia_created_account_must_change_password_before_use` | ✅ Lulus |
| 4.4 | Pengurusan Superadmin | Akses Superadmin sahaja; whitelist `pentadbir_urus_setia`/`superadmin`; peranan sedia ada dipaparkan & dikekalkan; validasi profil konsisten; reset kata laluan sementara; sasaran UUID dikunci; carian/penapis/pagination; tiada kemas kini separa | `SuperadminUserManagementTest` (26), `test_superadmin_created_urus_setia_can_process_requests_after_password_change`, `test_grade_must_come_from_pivot_not_legacy_position_grade_id` | ✅ Lulus |
| 4.5 | Tukar kata laluan wajib | Redirect; HTTP & Livewire disekat; kata laluan semasa disahkan; polisi UM7A; pengesahan sepadan; mesti berbeza; hash + flag false; gagal tidak mengubah; sesi semasa kekal & sesi lain dibatalkan; reset e-mel mematuhi polisi & mengosongkan flag | `ForcedPasswordChangeTest` (17), `SecurityHardeningTest` (sesi), matriks akses | ✅ Lulus |
| 4.6 | Status akaun | `pending`/`rejected` dilog keluar pada semua route (HTTP & Livewire); `active` ikut peranan; perubahan status semasa sesi berkuat kuasa serta-merta | Matriks akses, `SecurityHardeningTest::test_livewire_action_is_rejected_when_status_changes_mid_session` | ✅ Lulus |
| 4.7 | Profil & padam akaun | Hanya nama & e-mel; medan terlindung bukan property; e-mel unik konsisten (**dibaiki**); padam akaun dibuang; audit tanpa kata laluan | `ProfileTest`, `test_profile_cannot_change_protected_fields_and_uses_consistent_email_rules` | ✅ Lulus (selepas pembaikan D1) |
| 4.8 | Data master | Jawatan ↔ pelbagai Gred (pivot); Gred mesti dalam pivot; Skim dari Jawatan; `positions.grade_id` legacy tidak digunakan untuk pilihan baharu; tiada data dipadam | `UserManagementReadinessTest`, `test_grade_must_come_from_pivot_not_legacy_position_grade_id` | ✅ Lulus |
| 4.9 | Sumber peranan | Semua kebenaran guna Spatie; `role_id` tidak digunakan | `test_authorization_and_display_use_spatie_roles_not_legacy_role_id` | ✅ Lulus (lihat §8) |

---

## 5. Keputusan Pengujian Setiap Peranan (Matriks Akses)

Diuji oleh `UserModuleIntegrationTest::test_route_access_matrix` (8 set data × 13 route). ✓ = 200, ✗ = 403, → = redirect.

| Route | Tetamu | Pending | Rejected | Kata laluan sementara | pengguna_biasa | Urus Setia | Pengurusan | Superadmin |
|---|---|---|---|---|---|---|---|---|
| `/dashboard` | → login | → login | → login | → tukar | ✓ | ✓ | ✓ | ✓ |
| `/profile` | → login | → login | → login | → tukar | ✓ | ✓ | ✓ | ✓ |
| `/application/new` | → login | → login | → login | → tukar | ✓ | ✓ | ✗ | ✗ |
| `/admin/users` | → login | → login | → login | → tukar | ✗ | ✗ | ✗ | ✓ |
| `/admin/positions` | → login | → login | → login | → tukar | ✗ | ✗ | ✗ | ✓ |
| `/admin/grades` | → login | → login | → login | → tukar | ✗ | ✗ | ✗ | ✓ |
| `/admin/service-schemes` | → login | → login | → login | → tukar | ✗ | ✗ | ✗ | ✓ |
| `/admin/audit-logs` | → login | → login | → login | → tukar | ✗ | ✗ | ✗ | ✓ |
| `/admin/agencies` | → login | → login | → login | → tukar | ✗ | ✓ | ✗ | ✗ |
| `/urus-setia/pengguna/daftar` | → login | → login | → login | → tukar | ✗ | ✓ | ✗ | ✗ |
| `/urus-setia/pengguna/permohonan` | → login | → login | → login | → tukar | ✗ | ✓ | ✗ | ✗ |
| `/urus-setia/mesyuarat` | → login | → login | → login | → tukar | ✗ | ✓ | ✗ | ✗ |
| `/tukar-kata-laluan` | → login | → login | → login | ✓ | → dashboard | → dashboard | → dashboard | → dashboard |

Pending/rejected: sesi dilog keluar (disahkan `assertGuest`).

**Tindakan Livewire terus** (payload `/livewire/update` sebenar dengan kawalan positif): ditolak apabila status berubah, peranan ditarik, kata laluan sementara aktif, atau tetamu memainkan semula snapshot (`SecurityHardeningTest`, `ForcedPasswordChangeTest`).

**Manipulasi** (ID/UUID, property terkunci, peranan di luar whitelist, medan terlindung, input pendua/tidak sah): diuji dalam `RegistrationTest`, `UrusSetiaUserManagementTest`, `SuperadminUserManagementTest`, `PolicyDecisionsTest`, `UserModuleIntegrationTest`.

---

## 6. Status Akaun dan Pertukaran Kata Laluan

| Senario | Keputusan |
|---|---|
| `pending` / `rejected` log masuk | Ditolak dengan mesej status (BM) |
| `pending` / `rejected` dengan sesi sedia ada | Dilog keluar pada HTTP; 403 + log keluar pada Livewire |
| `inactive` | **Tidak wujud** dalam `UserStatus` (hanya `pending`, `active`, `rejected`); tiada status baharu ditambah (§12) |
| Kata laluan sementara | Redirect `/tukar-kata-laluan`; semua route dalaman & tindakan Livewire disekat |
| Tukar kata laluan (HTTP + Livewire sebenar) | Berjaya → flag `false`, hash baharu, sesi semasa kekal; sesi lain dibatalkan (`AuthenticateSession`) |
| Gagal validasi / exception | Kata laluan & flag tidak berubah |
| Had cubaan | 5 / 5 minit (tukar), login 5 / 60 s |
| Aliran hujung ke hujung (Urus Setia → Pengurusan; Superadmin → Urus Setia) | Log masuk → tukar wajib → akses ikut peranan ✓ |

---

## 7. Hubungan Data Master

| Semakan | Keputusan |
|---|---|
| Jawatan ↔ pelbagai Gred | ✓ pivot `grade_position` (DB pembangunan: 9 jawatan, 31 pautan) |
| Gred pengguna sah untuk jawatan | ✓ validation `exists:grade_position`; DB pembangunan: 0 pengguna dengan Gred di luar pivot |
| Skim dari Jawatan | ✓ readonly; tiada property boleh dihantar |
| `positions.grade_id` legacy | Dikekalkan; tidak digunakan untuk dropdown/validation baharu. Jawatan dengan `grade_id` legacy tanpa pivot **tidak** boleh dipilih (diuji). DB pembangunan: 0 percanggahan legacy vs pivot |
| Jawatan aktif tanpa Gred/Skim aktif | DB pembangunan: 0 |
| Agensi | 6 aktif; semua pengguna menunjuk agensi aktif |
| Mesej ralat | Mesej validation BM terkawal; tiada butiran dalaman didedahkan |
| Data/jadual dipadam | Tiada |

---

## 8. Role / Spatie berbanding `users.role_id`

| Lapisan | Sumber peranan |
|---|---|
| Middleware `role:` / `not-role:` | Spatie `RoleMiddleware` / `hasAnyRole()` |
| `ApplicationPolicy::create` | Spatie `hasAnyRole()` |
| `ensure…()` dalam Livewire | Spatie `hasRole()` |
| `@hasrole`, `@can` dalam Blade | Spatie / Policy |
| Paparan senarai Superadmin | Spatie (`roles` relation) |
| `Meeting::MEMBER_ROLES`, `eligibleOfficersFor` | Spatie |
| `users.role_id` | **Tidak digunakan** untuk kebenaran atau paparan. Hanya dalam `$fillable` dan relationship `User::role()` |

**Data pembangunan (baca sahaja):**

| ID | E-mel | `role_id` | Spatie | Sepadan? |
|---|---|---|---|---|
| 1 | admin.sppict@ | superadmin | superadmin | ✓ |
| 2 | urussetia.mkk@ | pentadbir_urus_setia | pentadbir_urus_setia | ✓ |
| 3 | pemohon.jkr@ | pengguna_biasa | pengguna_biasa | ✓ |
| 4 | watie@mppd.gov.my | NULL | pengguna_biasa | `role_id` kosong (pendaftaran baharu) |
| 5 | test1@ns.gov.my | NULL | pengguna_biasa | `role_id` kosong (pendaftaran baharu) |

**Penemuan:**
1. `role_id` legacy tidak diisi oleh aliran baharu (UM2/UM4/UM5), selaras dengan keputusan 6. Tiada kesan kepada kebenaran (diuji: `role_id = superadmin` + Spatie `pengguna_biasa` → ditolak di `/admin/users`, dipaparkan "Pemohon Agensi").
2. **Risiko laten:** relationship `User::role()` (legacy) **membayangi** scope Spatie `User::role('nama')`. Panggilan `User::role('superadmin')` akan gagal. Tiada kod semasa memanggilnya (disemak).
3. Tiada penyegerakan pukal dibuat. **Cadangan:** buang `role_id` daripada `$fillable` dan relationship `role()` dalam fasa pembersihan legacy (§12).

---

## 9. Kecacatan Ditemui dan Pembaikan

| ID | Kecacatan | Risiko | Bukti | Pembaikan |
|---|---|---|---|---|
| D1 | **Profil:** semakan unik e-mel berbeza daripada borang lain. Menggunakan `Rule::unique` (sensitif huruf pada pangkalan data bukan-MySQL), memerlukan input huruf kecil tanpa normalisasi, dan nama tidak dinormalisasi | Sederhana | `update-profile-information-form` vs trait `HandlesUserProfileFields` | Normalisasi (`Str::squish`, `Str::lower(trim)`), semakan unik case-insensitive termasuk soft-deleted (kecuali diri sendiri), mesej BM, `fill()` hanya `name`/`email` |
| D2 | Teks UI bahasa Inggeris pada halaman modul (Profil, tukar kata laluan Profil, lupa/reset kata laluan, sahkan e-mel, sahkan kata laluan, menu Profil/Log Out, laman utama Log in/Register) | Rendah | `__('Profile Information')` dsb. | Diterjemah ke BM formal (gantian rentetan tepat) |
| D3 | Butang "Daftar" (Self Register) tiada keadaan loading, jadi boleh dihantar berganda | Rendah | `register.blade.php` | `wire:loading.attr="disabled"` + teks "Mendaftar..." |

**Tiada kecacatan kritikal atau berisiko tinggi ditemui.** Kawalan akses backend, route dan Livewire disahkan berfungsi.

---

## 10. Fail Diubah dan Sebab

| Fail | Sebab |
|---|---|
| `resources/views/livewire/profile/update-profile-information-form.blade.php` | D1 + D2 |
| `resources/views/livewire/profile/update-password-form.blade.php` | D2 |
| `resources/views/livewire/pages/auth/register.blade.php` | D3 |
| `resources/views/livewire/pages/auth/verify-email.blade.php` | D2 |
| `resources/views/livewire/pages/auth/confirm-password.blade.php` | D2 |
| `resources/views/livewire/pages/auth/forgot-password.blade.php` | D2 |
| `resources/views/livewire/pages/auth/reset-password.blade.php` | D2 |
| `resources/views/profile.blade.php` | D2 |
| `resources/views/livewire/layout/navigation.blade.php` | D2 ("Profil", "Log Keluar") |
| `resources/views/livewire/welcome/navigation.blade.php` | D2 ("Log Masuk", "Daftar", "Papan Pemuka") |
| `tests/Feature/UserModuleIntegrationTest.php` (**baharu**) | 16 ujian integrasi UM8 |
| `docs/LAPORAN_FASA_UM8_AUDIT_INTEGRASI.md` (**baharu**) | Laporan ini |

Tiada ujian sedia ada diubah dalam UM8. Tiada migrasi, model, middleware atau dasar akses diubah.

---

## 11. Ringkasan Ujian

| Kategori | Bilangan |
|---|---|
| **Lulus** | **303** |
| Gagal akibat perubahan UM8 | 0 |
| Gagal baseline yang disahkan | 0 (`ProfileTest::test_user_can_delete_their_account` telah diganti dalam UM7A mengikut dasar 3.4; disahkan tiada lagi) |
| Tidak dapat dijalankan (persekitaran) | 0 |
| **Belum diuji (automatik)** | Ujian pelayar/visual: paparan desktop & skrin kecil, ralat JavaScript konsol, interaksi `wire:confirm` sebenar. Tiada Laravel Dusk/Playwright dalam projek |

Suite mengikut fasa: UM1 `UserManagementReadinessTest` (14), UM2 `RegistrationTest` (20), UM4 `UrusSetiaUserManagementTest` (27), UM5 `SuperadminUserManagementTest` (26), UM6 `ForcedPasswordChangeTest` (17), UM7 `SecurityHardeningTest` (23), UM7A `PolicyDecisionsTest` (18) + `ProfileTest` (4), UM8 `UserModuleIntegrationTest` (16), ditambah suite auth Breeze, Permohonan, Mesyuarat dan Master Data. **Semua lulus.**

Semasa UM8, dua ujian integrasi baharu gagal seketika kerana artifak ujian, bukan kecacatan kod: (a) `Volt::test()` memintas middleware, jadi hash sesi tidak dikemas kini; (b) `withHeaders()` membawa header `X-Livewire` ke permintaan berikutnya. Kedua-dua aliran ditulis semula menggunakan HTTP + `/livewire/update` sebenar dengan `flushHeaders()`. Tiada ujian dilonggarkan.

---

## 12. Isu Terbuka dan Cadangan Tindakan

| # | Isu | Jenis | Cadangan |
|---|---|---|---|
| 12.1 | Pengguna boleh menukar **E-mel Rasmi** sendiri melalui Profil tanpa kelulusan/pengesahan | Dasar | Putuskan: kunci e-mel (hanya pentadbir) atau wajib pengesahan e-mel |
| 12.2 | Tiada status `inactive`; akaun aktif tidak boleh dinyahaktifkan (selain soft delete yang tiada UI) | Dasar / skema | Jika diperlukan: tambah `UserStatus::Inactive` + UI Superadmin (tanpa migrasi; kolum `status` ialah string) |
| 12.3 | Pemohon ditolak tidak boleh mendaftar semula (e-mel & No. KP masih digunakan) | Dasar | Aliran mohon semula atau kebenaran Urus Setia |
| 12.4 | Tiada notifikasi pendaftaran/kelulusan/penolakan; tiada sebab penolakan | Fungsi baharu | Perlu keputusan + migrasi (sebab penolakan) |
| 12.5 | Pengesahan e-mel belum aktif | Dasar (UM7A) | Prasyarat dalam laporan UM7A §3.5 |
| 12.6 | Urus Setia boleh mendaftar permohonan; siapa menyemak permohonan Urus Setia sendiri | Dasar (UM7A 8.5) | Putuskan pemisahan tugas |
| 12.7 | Superadmin boleh sunting/reset dirinya & Superadmin lain | Dasar (UM5 12.3) | Pertimbang sekatan |
| 12.8 | Perubahan peranan (Spatie) tidak diaudit oleh OwenIt | Teknikal | Listener audit perubahan peranan |
| 12.9 | `users.role_id` & relationship `User::role()` legacy (risiko laten, §8) | Teknikal | Fasa pembersihan legacy |
| 12.10 | Kolum legacy `users.position`, `users.phone_number`, `positions.grade_id` | Teknikal | Pembersihan selepas semua profil lengkap |
| 12.11 | Akaun demo DB pembangunan masih menggunakan kata laluan lama | Operasi | Reset melalui modul Superadmin |
| 12.12 | Tetapan sesi production (`SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`) | Konfigurasi | Semakan `.env` production oleh pentadbir |
| 12.13 | Tiada ujian pelayar automatik | Ujian | Pertimbang Laravel Dusk untuk aliran kritikal |

Tiada isu terbuka di atas melibatkan kawalan akses yang boleh dipintas.

---

## 13. Pengesahan: Pengesahan E-mel Wajib Tidak Diaktifkan

- ✓ `App\Models\User` tidak melaksanakan `MustVerifyEmail` (diuji dalam `PolicyDecisionsTest`).
- ✓ Tiada perubahan `.env`, SMTP atau `MAIL_MAILER`.
- ✓ Tiada pengisian `email_verified_at` secara pukal.
- ✓ Tiada e-mel sebenar dihantar (`MAIL_MAILER=array` dalam ujian).
- ✓ Pengguna aktif yang belum mengesahkan e-mel boleh menggunakan sistem (diuji).

---

## 14. Pengesahan: Tiada Operasi Pangkalan Data atau Git Terlarang

- ✓ Tiada `migrate:fresh`, `migrate:refresh`, `db:wipe`, `DROP TABLE`, `TRUNCATE` atau rollback.
- ✓ Tiada migrasi baharu; tiada migrasi dijalankan.
- ✓ Tiada seeder dijalankan terhadap DB pembangunan/production.
- ✓ DB pembangunan diakses melalui **SELECT sahaja** (skrip audit dalam direktori scratchpad, di luar projek).
- ✓ Tiada pemadaman atau perubahan data pukal.
- ✓ **Tiada commit, push, merge, reset, stash atau pertukaran branch.** Hanya `git status`, `git diff --stat`, `git diff --check`, `git log`, `git branch --show-current` (baca sahaja).
- ✓ Semua perubahan UM1–UM7A dalam working tree dikekalkan.

---

## 15. Senarai Semakan Manual Sebelum Penerimaan

Disyorkan dilakukan oleh pemilik sistem dalam pelayar (desktop **dan** telefon / mod responsif):

| # | Semakan | ✓ |
|---|---|---|
| 1 | **Self Register** (`/register`): pilih Jawatan → Skim dipaparkan & senarai Gred berubah; tukar Jawatan → Gred dikosongkan; ralat BM dipaparkan; butang "Mendaftar..." semasa hantar; halaman "Pendaftaran Diterima" | ☐ |
| 2 | Log masuk akaun `pending` → mesej "menunggu kelulusan Urus Setia" | ☐ |
| 3 | **Urus Setia** → Pengurusan Pengguna → Permohonan Pendaftaran: No. KP bertopeng dalam senarai, penuh dalam "Semak"; Lulus/Tolak dengan dialog pengesahan | ☐ |
| 4 | **Urus Setia** → Daftar Pengguna: Tahap Capaian hanya 3 pilihan; panel kata laluan sementara dipaparkan sekali | ☐ |
| 5 | Log masuk dengan kata laluan sementara → terus ke "Tukar Kata Laluan"; cuba buka `/dashboard` → kembali ke halaman tukar | ☐ |
| 6 | **Superadmin** → Pengurusan Pengguna: carian (nama/e-mel/No. KP), penapis peranan, badge "Pengurusan", Edit (peranan semasa dikekalkan), Reset Pass dengan dialog pengesahan | ☐ |
| 7 | **Profil:** label BM; tiada bahagian padam akaun; tukar kata laluan dengan kata laluan sama ditolak | ☐ |
| 8 | **Pengurusan / Superadmin:** tiada menu "Permohonan Baru"; `/application/new` → 403 | ☐ |
| 9 | **Superadmin** → Jawatan: pilih pelbagai Gred (checkbox); papan pemuka → "Jawatan & Gred" membuka halaman Jawatan | ☐ |
| 10 | Konsol pelayar: tiada ralat JavaScript/Livewire pada halaman di atas | ☐ |
| 11 | Paparan skrin kecil: jadual boleh ditatal mendatar; modal tidak terpotong | ☐ |
| 12 | Reset kata laluan akaun demo DB pembangunan (12.11) | ☐ |
| 13 | Putuskan isu dasar §12.1–12.7 | ☐ |

---

*UM8 selesai. Menunggu semakan pemilik sistem. Fasa seterusnya tidak dimulakan.*
