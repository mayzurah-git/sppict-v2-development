# LAPORAN FASA UM7A — PELAKSANAAN KEPUTUSAN DASAR MODUL PENGURUSAN PENGGUNA

**Projek:** SPPICT v2
**Tarikh:** 2026-10-13
**Branch:** `feature/user-management`
**Rujukan:** `Prompt-UM7A-Pelaksanaan-Keputusan-Dasar-Modul-Pengurusan-Pengguna.md`, `docs/LAPORAN_FASA_UM7_PENGERASAN_KESELAMATAN.md`

---

## 1. Ringkasan Pelaksanaan

**Status: BERJAYA**

Ketujuh-tujuh keputusan dasar (3.1–3.7) telah dilaksanakan dan diuji.

| # | Keputusan | Hasil |
|---|---|---|
| 3.1 | Polisi kata laluan berpusat | Dilaksanakan: semua laluan penetapan kata laluan (termasuk kata laluan sementara) mematuhi polisi; Profil kini menolak kata laluan yang sama dengan semasa |
| 3.2 | Hanya `pengguna_biasa` & `pentadbir_urus_setia` boleh mendaftar permohonan | Dilaksanakan: `ApplicationPolicy::create` dikuatkuasakan di route, komponen Livewire dan servis backend |
| 3.3 | Tiada fallback agensi tetap | Dilaksanakan: `agency_id ?? 1` dibuang; proses dihentikan dengan mesej jelas |
| 3.4 | Padam akaun sendiri dinyahaktifkan | Dilaksanakan: komponen dibuang; ujian dikemas kini mengikut dasar |
| 3.5 | Pengesahan e-mel kekal tidak aktif | Disahkan: tiada perubahan; ujian pengesahan ditambah |
| 3.6 | Komponen legacy `PositionGradeManagement` | Dibuang: tiada kebergantungan; pengganti lengkap |
| 3.7 | Seeder diasingkan; Superadmin melalui provisioning | Dilaksanakan: peranan sahaja / demo (local/testing) / arahan `sppict:create-superadmin` |

**Keputusan ujian:** 287 ujian: **287 lulus, 0 gagal**.

Tiada migrasi, seeder atau operasi Git dijalankan.

---

## 2. Keadaan Awal dan Penemuan Audit

| Perkara | Penemuan (kod semasa sebelum UM7A) |
|---|---|
| Branch / working tree | `feature/user-management`; 60 entri belum di-commit (UM1–UM7) |
| Baseline ujian | 270 ujian: 269 lulus, 1 gagal (`ProfileTest::test_user_can_delete_their_account`) |
| Polisi kata laluan | `Password::defaults()` = min 12 + huruf besar/kecil + nombor + simbol (UM7), digunakan oleh Self Register, Tukar Kata Laluan wajib, reset e-mel, Profil |
| Celah polisi | (a) Profil tidak menolak kata laluan baharu yang sama dengan semasa; (b) kata laluan sementara `Str::password(12)` (Urus Setia, Superadmin) tidak **dijamin** mengandungi huruf besar & kecil; (c) mesej polisi Profil & reset dalam BI |
| Penciptaan permohonan | Route `/application/new` & `/permohonan/baru`: `not-role:superadmin` sahaja. Pengurusan, Urus Setia dan pengguna **tanpa peranan** boleh mendaftar. Tiada `ApplicationPolicy::create` |
| Sumber peranan | Spatie (`model_has_roles`); `users.role_id` legacy tidak digunakan dalam semakan akses |
| Fallback agensi | `ApplicationSubmissionService::resolveApplication()`: `agency_id => $user->agency_id ?? 1` |
| Laluan cipta pengguna | Self Register, `RegisterUser` (Urus Setia), `Admin\UserManagement` (Superadmin): semuanya mewajibkan agensi aktif (trait `HandlesUserProfileFields`). Seeder: agensi dicipta sendiri (`firstOrCreate`) |
| Data agensi (DB pembangunan, baca sahaja) | 5 pengguna; kesemuanya mempunyai agensi yang wujud & aktif. 0 permohonan pada agensi id 1 |
| Padam akaun sendiri | `profile.delete-user-form` dimuatkan dalam `/profile` untuk semua peranan (soft delete) |
| Pengesahan e-mel | `User` tidak melaksanakan `MustVerifyEmail`; `MAIL_MAILER=log` |
| `PositionGradeManagement` | Hanya dirujuk oleh dirinya & view-nya. Route `admin.positions-grades` ialah `Route::redirect` ke `/admin/positions` (tidak memuatkan komponen). Pengganti: `PositionManagement` (pivot `grade_position`, 31 baris dalam DB pembangunan) + `GradeManagement` + `ServiceSchemeManagement` |
| Seeder | `RolesAndPermissionsSeeder` mencampurkan peranan, 2 agensi contoh dan 3 akaun demo; dipanggil oleh `DatabaseSeeder` dalam **semua** persekitaran |
| Ujian bergantung pada pemohon tanpa peranan | 6 kelas ujian permohonan mencipta pemohon tanpa peranan / sebagai tetamu |

---

## 3. Keputusan Bagi Setiap Perkara

### 3.1 Polisi Kata Laluan

| Keperluan | Pelaksanaan |
|---|---|
| Min 12, huruf besar, huruf kecil, nombor, simbol | `Password::defaults(fn () => Password::min(12)->max(255)->letters()->mixedCase()->numbers()->symbols())` dalam `AppServiceProvider` (disahkan secara langsung, bukan andaian) |
| Berbeza daripada semasa | Tukar Kata Laluan wajib (UM6) dan **Profil** (baharu): `different:current_password` |
| Pengesahan sepadan | `confirmed` pada semua borang |
| Pendaftaran kendiri | `Password::defaults()` |
| Urus Setia / Superadmin (cipta & reset) | Kata laluan **sementara** dijana oleh `App\Support\TemporaryPassword::generate()`, yang **dijamin mematuhi polisi** (dijana semula sehingga lulus `Password::default()`). Pengguna wajib menukarnya (`must_change_password`) kepada kata laluan yang mematuhi polisi |
| Tukar wajib / Profil / Reset | `Password::defaults()` |
| Mesej | Mesej BM berpusat `App\Support\PasswordPolicyMessages` digunakan oleh Profil & reset (Self Register & Tukar Kata Laluan sudah BM) |
| Tiada tempoh luput / sejarah | Tidak dilaksanakan (belum diputuskan) |
| Hash / log / audit | `Hash::make()`; `password` dalam `$auditExclude`; tiada kata laluan dalam log/sesi |

**Semua laluan penetapan kata laluan:** Self Register, `RegisterUser`, `UserManagement` (cipta + reset), `change-password`, `profile.update-password-form`, `reset-password`, `sppict:create-superadmin`, `DemoAccountsSeeder`. Tiada lagi `Str::password()` di luar penjana berpusat.

**Catatan reset melalui e-mel:** semakan "kata laluan baharu ≠ semasa" **tidak** ditambah pada reset. Semakan itu perlu dibuat sebelum token disahkan, dan ia akan menjadi *oracle* untuk menguji kata laluan semasa sesuatu akaun (risiko keselamatan). Dasar menyebut "apabila pengguna **menukar** kata laluan"; reset melalui e-mel bukan pertukaran berasaskan kata laluan semasa.

### 3.2 Kebenaran Mendaftar Permohonan Projek

| Peranan | Mendaftar permohonan |
|---|---|
| `pengguna_biasa` | ✓ |
| `pentadbir_urus_setia` | ✓ |
| `pengurusan` | ✗ |
| `superadmin` | ✗ |
| Tiada peranan / `pending` / `rejected` / kata laluan sementara | ✗ |
| Pemegang pelbagai peranan termasuk `pengurusan`/`superadmin` | ✗ (dikecualikan secara eksplisit) |

Penguatkuasaan (sumber peranan: Spatie):
1. **Policy:** `ApplicationPolicy::create()`: `canUseSystem()` && `hasAnyRole(CREATOR_ROLES)` && bukan `superadmin`/`pengurusan`.
2. **Route:** `/application/new` & `/permohonan/baru`: `can:create,App\Models\Application`. Middleware `Authorize` juga persistent dalam Livewire.
3. **Livewire:** `ApplicationForm::mount()` (tanpa UUID) dan `submitApplication()` / `saveDraft()` (permohonan baharu) memanggil `authorize('create')`. Ini disemak semula jika peranan berubah semasa sesi.
4. **Backend:** `ApplicationSubmissionService::resolveApplication()` menolak (`AuthorizationException`) jika `cannot('create')`.
5. **UI:** pautan menu & butang papan pemuka menggunakan `@can('create', Application::class)` (paparan sahaja).

Kebenaran melihat/menyemak/memproses (`view`, `update`, `manageOfficers`, `updateProgress`) **tidak diubah**. Route edit/view kekal `not-role:superadmin`.

### 3.3 Agensi Pengguna

- `agency_id ?? 1` **dibuang**. `applicantAgencyId()` baharu menghentikan proses jika pemohon tiada agensi atau agensinya tidak aktif / dipadam, dengan mesej: *"Akaun anda tidak mempunyai Jabatan/Agensi yang sah dan aktif. Sila hubungi Urus Setia untuk mengemas kini profil anda sebelum mendaftar permohonan."* Mesej dipaparkan dalam borang permohonan.
- Laluan cipta/kemas kini pengguna (Self Register, Urus Setia, Superadmin) sudah mewajibkan agensi aktif melalui validation `exists` + `is_active` (UM2/UM4/UM5); disahkan, tiada perubahan.
- `sppict:create-superadmin` mewajibkan kod agensi yang aktif.
- `DemoAccountsSeeder` menggunakan agensi yang dicipta/dirujuk secara eksplisit (`firstOrCreate` mengikut kod).
- **Tiada kemas kini pukal**; rekod pengguna tidak diubah apabila proses dihentikan (diuji).
- **Isu data:** tiada (kesemua 5 pengguna DB pembangunan mempunyai agensi aktif).

### 3.4 Pemadaman Akaun Sendiri

- Komponen `resources/views/livewire/profile/delete-user-form.blade.php` **dipadam**, jadi tiada tindakan Livewire `deleteUser` wujud lagi (bukan sekadar disembunyikan).
- Rujukan `<livewire:profile.delete-user-form />` dibuang daripada `profile.blade.php`.
- Tiada route berkaitan padam akaun (disahkan).
- Rekod pengguna, permohonan, kelulusan dan audit trail tidak disentuh.
- **`ProfileTest`:** dua ujian lama (`test_user_can_delete_their_account`, `test_correct_password_must_be_provided_to_delete_account`) menjangka pemadaman dibenarkan. Kedua-duanya diganti dengan `test_user_cannot_delete_their_own_account` (semua 4 peranan: tiada borang, komponen tidak wujud, akaun kekal & tidak soft-deleted). `test_profile_page_is_displayed` kini menjangka **tiada** borang padam akaun.

### 3.5 Pengesahan E-mel

**Tidak diaktifkan.** Tiada `MustVerifyEmail`, tiada perubahan `.env`/SMTP, tiada pengisian pukal `email_verified_at`, tiada e-mel sebenar dihantar. Diuji: `User` bukan `MustVerifyEmail`; pengguna aktif yang belum mengesahkan e-mel boleh mengakses papan pemuka dan borang permohonan.

**Persediaan sebelum pengaktifan:**
1. Konfigurasi SMTP rasmi + `MAIL_FROM_ADDRESS` (oleh pentadbir sistem).
2. Dasar untuk akaun sedia ada (5 akaun dalam DB pembangunan; semua `email_verified_at = NULL`): backfill atau wajib sahkan.
3. Susunan: pengesahan e-mel sebelum atau selepas kelulusan Urus Setia (Self Register); perlukah akaun yang didaftar Urus Setia/Superadmin disahkan.
4. Terjemahan halaman `verify-email` ke BM.
5. Ujian: `Notification::fake()`, pautan sah/tidak sah, sekatan `verified`, status/kelulusan tidak berubah, `pending`/`rejected` tidak mendapat akses.

### 3.6 Komponen Legacy `PositionGradeManagement`

| Semakan | Hasil |
|---|---|
| Route / menu / pautan | Tiada route memuatkan komponen. `admin.positions-grades` = redirect ke `/admin/positions` (dikekalkan; dipaut dari papan pemuka Superadmin) |
| Import / ujian / Livewire tag | Tiada (hanya dirinya & view-nya) |
| Pengganti | `PositionManagement` (Jawatan + Skim + pelbagai Gred melalui pivot), `GradeManagement`, `ServiceSchemeManagement`: lengkap & diuji (UM1) |
| Data | Komponen hanya menulis `grades.name/scheme` & `positions.title` (model lama); tiada data bergantung padanya |

**Tindakan:** `app/Livewire/Admin/PositionGradeManagement.php` dan `resources/views/livewire/admin/position-grade-management.blade.php` **dipadam**. Tiada jadual, model atau data dipadam. Classmap Composer dijana semula (`composer dump-autoload`) kerana autoload dioptimumkan masih memetakan kelas lama (perlu juga semasa deployment).

### 3.7 Seeder dan Akaun Awal

| Komponen | Tanggungjawab | Persekitaran |
|---|---|---|
| `RolesAndPermissionsSeeder` | **Peranan & permission sahaja** (idempotent; tiada pengguna/agensi) | Semua |
| `DemoAccountsSeeder` (baharu) | 2 agensi contoh + 3 akaun demo (Superadmin, Urus Setia, Pemohon) | **local / testing sahaja**; enggan berjalan di persekitaran lain (semakan dalaman + `DatabaseSeeder` hanya memanggilnya dalam `local`) |
| `php artisan sppict:create-superadmin {email} --name= --agency=` (baharu) | Provisioning terkawal Superadmin awal | Semua (dijalankan secara manual oleh operator) |

Keselamatan:
- **Tiada `Password123!`** atau kata laluan tetap dalam mana-mana seeder/arahan.
- Akaun demo & Superadmin baharu: kata laluan sementara rawak mematuhi polisi (`TemporaryPassword::generate(16)`), `must_change_password = true`, dipaparkan **sekali** di konsol operator (tidak dilog/disimpan).
- **Akaun sedia ada (termasuk soft-deleted) tidak disentuh:** kata laluan dan peranan tidak diubah apabila seeder/arahan dijalankan semula. Arahan provisioning enggan jika e-mel sudah wujud.
- `DatabaseSeeder` dalam production: hanya data rujukan (diuji: tiada pengguna/agensi demo dicipta).
- `MeetingSeeder` (local) bergantung pada akaun Urus Setia demo; susunan `DemoAccountsSeeder` → `MeetingSeeder` dikekalkan.

---

## 4. Fail Ditambah, Diubah dan Dipadam

### Ditambah

| Fail | Sebab |
|---|---|
| `app/Support/TemporaryPassword.php` | Penjana kata laluan sementara berpusat yang mematuhi polisi (3.1) |
| `app/Support/PasswordPolicyMessages.php` | Mesej BM polisi kata laluan berpusat (3.1) |
| `app/Console/Commands/CreateSuperadmin.php` | Provisioning Superadmin terkawal (3.7) |
| `database/seeders/DemoAccountsSeeder.php` | Akaun demo local/testing sahaja (3.7) |
| `tests/Feature/PolicyDecisionsTest.php` | 18 ujian UM7A |
| `docs/LAPORAN_FASA_UM7A_KEPUTUSAN_DASAR.md` | Laporan ini |

### Diubah — kod

| Fail | Sebab |
|---|---|
| `app/Policies/ApplicationPolicy.php` | `create()` + `CREATOR_ROLES` (3.2) |
| `routes/web.php` | `can:create` pada route cipta permohonan (3.2) |
| `app/Livewire/ApplicationForm.php` | `authorize('create')` di `mount` & tindakan simpan permohonan baharu (3.2) |
| `app/Services/ApplicationSubmissionService.php` | Semakan `create` di backend; buang `?? 1`; `applicantAgencyId()` (3.2, 3.3) |
| `resources/views/livewire/application-form.blade.php` | Paparan ralat agensi (3.3) |
| `resources/views/livewire/layout/navigation.blade.php`, `resources/views/dashboard.blade.php` | Pautan/butang "Permohonan Baru" ikut `@can('create')` (3.2) |
| `resources/views/livewire/profile/update-password-form.blade.php` | `different:current_password` + mesej BM (3.1) |
| `resources/views/livewire/pages/auth/reset-password.blade.php` | Mesej BM polisi (3.1) |
| `app/Livewire/Users/RegisterUser.php`, `app/Livewire/Admin/UserManagement.php` | `TemporaryPassword::generate()` (3.1) |
| `resources/views/profile.blade.php` | Buang borang padam akaun (3.4) |
| `database/seeders/RolesAndPermissionsSeeder.php` | Peranan & permission sahaja (3.7) |
| `database/seeders/DatabaseSeeder.php` | `DemoAccountsSeeder` dalam `local` sahaja (3.7) |

### Dipadam

| Fail | Sebab |
|---|---|
| `resources/views/livewire/profile/delete-user-form.blade.php` | Dasar 3.4 |
| `app/Livewire/Admin/PositionGradeManagement.php` | Dasar 3.6: tiada kebergantungan |
| `resources/views/livewire/admin/position-grade-management.blade.php` | Dasar 3.6 |

(Ketiga-tiganya dijejaki Git dan boleh dipulihkan daripada sejarah jika perlu.)

### Diubah — ujian sedia ada (penyelarasan dengan dasar)

| Fail | Perubahan |
|---|---|
| `tests/Feature/ProfileTest.php` | Ujian padam akaun diganti dengan ujian sekatan (3.4) |
| `tests/Feature/ApplicationDocumentTest.php`, `ApplicationMasterDataIntegrationTest.php`, `ApplicationOfficerTest.php`, `JtictSubmissionWindowTest.php` | Pemohon fixture diberi peranan `pengguna_biasa` (sebelum ini tanpa peranan; dalam sistem sebenar pemohon sentiasa `pengguna_biasa`) (3.2) |
| `tests/Feature/ApplicationCostSummaryTest.php`, `ApplicationFormTest.php` | Borang dimuatkan sebagai pemohon `pengguna_biasa` (sebelum ini tetamu tanpa log masuk) (3.2) |
| `tests/Feature/SecurityHardeningTest.php` | Ujian seeder menggunakan `DemoAccountsSeeder` (3.7) |

---

## 5. Ujian Dijalankan dan Keputusan Sebenar

### `Tests\Feature\PolicyDecisionsTest`: 18 ujian, 18 lulus

| Keperluan prompt | Ujian |
|---|---|
| 1–2 Polisi pada semua aliran, mesej sesuai | `test_generated_temporary_passwords_always_satisfy_policy`, `test_urus_setia_and_superadmin_temporary_passwords_satisfy_policy`, `test_profile_password_change_rejects_current_password_and_weak_passwords_with_malay_messages`, `test_password_reset_shows_malay_policy_message` (+ UM7 `test_policy_is_applied_to_every_password_form_without_side_effects`, `test_central_password_policy_is_configured`) |
| 3–4 Kebenaran permohonan | `test_create_policy_matrix`, `test_create_routes_follow_policy`, `test_navigation_shows_create_link_only_for_permitted_roles`, `test_unauthorised_roles_cannot_create_through_livewire`, `test_urus_setia_can_save_a_draft_application` |
| 5 Agensi | `test_application_is_not_created_without_valid_active_agency`, `test_no_user_creation_path_uses_a_fixed_agency_fallback` |
| 6 Padam akaun | `ProfileTest::test_user_cannot_delete_their_own_account` |
| 7 Pengesahan e-mel | `test_email_verification_remains_inactive` |
| 8 Komponen legacy | `test_legacy_position_grade_component_is_removed_and_route_redirects` |
| 9 Seeder | `test_roles_seeder_creates_reference_data_only`, `test_demo_accounts_are_never_created_in_production`, `test_demo_seeder_does_not_change_existing_accounts`, `test_create_superadmin_command_provisions_account_with_temporary_password`, `test_create_superadmin_command_refuses_existing_account_and_invalid_agency` |
| 10 Regresi | Full suite |

### Full suite

| | Sebelum UM7A | Selepas UM7A |
|---|---|---|
| Jumlah | 270 | 287 |
| Lulus | 269 | **287** |
| Gagal | 1 | **0** |

Semua suite UM1, UM2, UM4, UM5, UM6, UM7, Permohonan dan Mesyuarat lulus. Ujian dijalankan menggunakan SQLite in-memory (`phpunit.xml`).

---

## 6. Kegagalan Baseline

| Ujian | Sebelum | Selepas | Penjelasan |
|---|---|---|---|
| `ProfileTest::test_user_can_delete_their_account` | Gagal (baseline sejak sebelum UM1: soft delete vs jangkaan hard delete Breeze) | **Diganti** | Dasar 3.4 menyahaktifkan padam akaun sendiri. Ujian lama menguji fungsi yang kini tidak dibenarkan, maka diganti dengan `test_user_cannot_delete_their_own_account`. Dasar **tidak** diubah untuk meluluskan ujian lama |

Semasa pelaksanaan, 37 kegagalan sementara muncul dalam 6 kelas ujian permohonan. Puncanya fixture pemohon tanpa peranan / tetamu yang kini ditolak oleh dasar 3.2 (tingkah laku yang **dijangka**). Ia telah diselaraskan (§4). **Tiada regresi tingkah laku berbaki.**

---

## 7. Status Migrasi dan Seeder

- **Migrasi:** tiada migrasi baharu; tiada migrasi dijalankan. Migrasi terakhir kekal `2026_10_08_000002_add_user_management_columns_to_users_table.php`.
- **Seeder:** **tiada seeder dijalankan** terhadap DB pembangunan atau production. Seeder hanya dijalankan dalam ujian (SQLite in-memory).
- **Lain-lain:** `composer dump-autoload` dijalankan (menjana semula classmap selepas memadam kelas legacy; tiada kesan kepada DB).

---

## 8. Risiko atau Isu Data Belum Selesai

| # | Perkara | Catatan |
|---|---|---|
| 8.1 | **Deployment:** production yang sebelum ini bergantung pada `db:seed` untuk mencipta Superadmin kini perlu menjalankan `php artisan sppict:create-superadmin` | Kemas kini prosedur deployment |
| 8.2 | **Deployment:** jalankan `composer dump-autoload` (atau `composer install`) selepas menarik perubahan, kerana kelas legacy telah dibuang | Jika tidak, autoload dioptimumkan lama akan merujuk fail yang tiada |
| 8.3 | Akaun demo DB pembangunan (`admin.sppict@`, `urussetia.mkk@`, `pemohon.jkr@`) **masih menggunakan kata laluan lama** yang dicipta sebelum UM7 | UM7A tidak menukar kata laluan sedia ada (larangan). Disyorkan pemilik sistem reset melalui modul Superadmin |
| 8.4 | Pengguna sedia ada tanpa peranan / dengan peranan `pengurusan` tidak lagi boleh mencipta permohonan | Selaras dengan dasar; DB pembangunan: tiada pengguna tanpa peranan |
| 8.5 | Urus Setia kini boleh mendaftar permohonan atas nama agensi sendiri (BTM) | Selaras dengan dasar 3.2; aliran semakan Urus Setia terhadap permohonan sendiri belum diputuskan (konflik kepentingan) |
| 8.6 | Reset melalui e-mel tidak menolak kata laluan yang sama dengan semasa | Disengajakan (§3.1, elak oracle kata laluan) |
| 8.7 | Pengesahan e-mel | Belum diaktifkan (§3.5) |

---

## 9. Pengesahan: Pengesahan E-mel Wajib Belum Diaktifkan

✓ `App\Models\User` **tidak** melaksanakan `MustVerifyEmail` (diuji).
✓ Tiada perubahan `.env`, SMTP atau `MAIL_MAILER`.
✓ Tiada pengisian `email_verified_at` secara pukal.
✓ Tiada e-mel sebenar dihantar (`MAIL_MAILER=array` dalam ujian).
✓ Aliran Self Register (`pending`) dan kelulusan Urus Setia tidak berubah.

---

## 10. Pengesahan: Tiada Operasi Git

✓ **Tiada commit, push, merge, reset, stash atau pertukaran branch.** Branch kekal `feature/user-management`.
✓ Fail dipadam menggunakan `rm` biasa (tidak di-stage).
✓ `git diff --check` bersih; `git status --short`: 80 entri (termasuk 3 fail dipadam).
✓ Perubahan UM1–UM7 dikekalkan.

---

## 11. Semakan Manual Oleh Pemilik Sistem

1. **Log masuk sebagai `pengguna_biasa`:** menu "Permohonan Baru" dipaparkan; borang boleh dibuka; draf boleh disimpan.
2. **Log masuk sebagai Urus Setia:** menu "Permohonan Baru" kini dipaparkan; sahkan aliran ini dikehendaki.
3. **Log masuk sebagai Pengurusan / Superadmin:** tiada menu "Permohonan Baru"; `/application/new` memaparkan 403.
4. **Profil (semua peranan):** tiada bahagian "Delete Account"; tukar kata laluan dengan kata laluan yang sama ditolak dengan mesej BM.
5. **Superadmin:** papan pemuka → "Jawatan & Gred" membuka halaman Jawatan (redirect); Jawatan/Gred berfungsi.
6. **Kata laluan sementara** yang dipaparkan oleh Urus Setia/Superadmin mengandungi huruf besar, kecil, nombor dan simbol.
7. **Akaun tanpa agensi** (jika ada dalam data sebenar): cubaan simpan permohonan memaparkan mesej agensi tidak sah.
8. **Persekitaran baharu:** `php artisan migrate`, `php artisan db:seed` (rujukan), kemudian `php artisan sppict:create-superadmin emel@ns.gov.my --name="..." --agency=BTM`; rekod kata laluan sementara yang dipaparkan; log masuk dan tukar kata laluan.
9. **Akaun demo sedia ada** dalam DB pembangunan: reset kata laluan melalui modul Superadmin (8.3).
10. Kemas kini dokumentasi deployment (8.1, 8.2).

---

## 12. Cadangan Langkah Seterusnya (UM8)

1. Keputusan aliran pengesahan e-mel (§3.5) dan konfigurasi SMTP.
2. Dasar semakan Urus Setia terhadap permohonan yang didaftar oleh Urus Setia sendiri (8.5).
3. Audit perubahan peranan (Spatie) dalam OwenIt.
4. Terjemahan BM halaman Breeze yang berbaki (`verify-email`, `confirm-password`, `forgot-password`, Profil).
5. Pembersihan kolum legacy (`users.role_id`, `users.position`, `users.phone_number`, `positions.grade_id`) selepas profil semua akaun dilengkapkan.
6. Semakan tetapan sesi production (`SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`).
7. Commit berperingkat kerja UM1–UM7A selepas semakan.

---

*UM7A selesai. Menunggu semakan laporan sebelum memulakan UM8.*
