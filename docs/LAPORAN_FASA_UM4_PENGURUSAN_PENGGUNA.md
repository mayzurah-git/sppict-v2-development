# LAPORAN FASA UM4 — PENDAFTARAN PENGGUNA OLEH URUS SETIA & KELULUSAN PERMOHONAN

**Projek:** SPPICT v2
**Tarikh:** 2026-10-12
**Rujukan:** `Prompt-UM4-UrusSetiaRegisterPengguna.md`, `docs/LAPORAN_FASA_UM0_AUDIT_PENGURUSAN_PENGGUNA.md`, `docs/LAPORAN_FASA_UM2_SELF_REGISTER.md`

---

## 1. Status Pelaksanaan

**BERJAYA DENGAN CATATAN**

Kedua-dua fungsi (A: pendaftaran oleh Urus Setia, B: kelulusan/penolakan Self Register) berfungsi dan diuji sepenuhnya. Catatan utama:

1. **Kewajipan tukar kata laluan belum dikuatkuasakan.** Flag `must_change_password = true` ditetapkan, tetapi mekanisme yang memaksa pertukaran semasa log masuk pertama belum wujud dalam projek. Ini memerlukan UM6 (§10.1).
2. **Status akaun yang didaftar oleh Urus Setia = `active`.** Ini tafsiran daripada keputusan sedia ada; perlu disahkan (§10.2).
3. **Tiada medan sebab penolakan.** Skema tidak ditambah; perlu keputusan (§10.3).
4. **Pembetulan kesan sampingan** pada `Meeting::notificationRecipients()` dan `User::eligibleOfficersFor()` supaya pemohon `pending`/`rejected` dikecualikan (§3, §4).

---

## 2. Branch Git dan Status Working Tree

| Perkara | Nilai |
|---|---|
| Branch | `feature/user-management` (dicipta daripada `feature/application-module` @ `4585dfe`) |
| Commit terakhir | `4585dfe` — perubahan UM1, UM2 dan UM4 **belum di-commit** |
| Staged | Tiada |
| Branch / merge / reset / stash | Tidak dilakukan |

Working tree mengandungi perubahan UM1 + UM2 + UM4 (lihat §4).

---

## 3. Ringkasan Audit Sebelum Perubahan

| Komponen | Penemuan |
|---|---|
| Schema UM1 | `users.status`, `approved_by`, `approved_at`, `must_change_password`, `no_kad_pengenalan`, `grade_id`, `phone_office`, `phone_mobile` dan pivot `grade_position` tersedia. **Tiada migration diperlukan** |
| `UserStatus` | `pending` / `active` / `rejected`, dengan `label()` dan `inactiveMessage()` |
| `User` | Auditable (OwenIt, `password` & `remember_token` dikecualikan). `status`, `approved_*`, `must_change_password` tidak dalam `$fillable`. Scope `active()` / `pending()` |
| Self Register (UM2) | Volt `pages.auth.register`; logik medan profil terbenam dalam komponen |
| Kawalan akses | `LoginForm` menyekat `pending`/`rejected`; middleware `active` pada route dalaman |
| User Management sedia ada | `Admin\UserManagement`: Superadmin sahaja, teks bebas, `Password123!` (di luar skop UM4, untuk UM5) |
| Corak authorization Urus Setia | Middleware route `role:pentadbir_urus_setia` + `ensureUrusSetia()` dalam setiap action (cth. `AgencyManagement`) |
| Fungsi kelulusan | **Tiada** |
| Notifikasi | `MeetingAnnouncement`, `ResponsibleOfficerChanged` sahaja. Tiada notifikasi pendaftaran. `MAIL_MAILER=log` |
| Session | `SESSION_DRIVER=database`, `SESSION_ENCRYPT=false`. Oleh itu kata laluan sementara **tidak boleh** dihantar melalui flash session |
| ⚠ Kesan sampingan status | `Meeting::notificationRecipients()` dan `User::eligibleOfficersFor()` tidak menapis status. Pemohon `pending`/`rejected` (role `pengguna_biasa`) akan menerima e-mel mesyuarat dan boleh dilantik Pegawai Bertanggungjawab. `Meeting.php` sendiri mengandungi TODO untuk menapis status pengguna |
| Legacy | `User::role()` (relationship `role_id`) mengatasi scope Spatie `User::role()`. `User::$position` (kolum legacy) mengatasi relationship `position()`; gunakan `positionRelation` |

Kesimpulan: struktur semasa membolehkan UM4 dilaksanakan dengan selamat tanpa perubahan skema.

---

## 4. Fail Dicipta dan Diubah

### Dicipta

| Fail | Sebab |
|---|---|
| `app/Livewire/Concerns/HandlesUserProfileFields.php` | Trait medan profil dikongsi (No. KP, agensi, Jawatan → Skim → Gred, telefon, e-mel): rules, mesej BM, pilihan dropdown, normalisasi. Satu sumber validation untuk Self Register, Urus Setia dan (kelak) Superadmin |
| `app/Livewire/Users/RegisterUser.php` | Fungsi A: pendaftaran oleh Urus Setia |
| `app/Livewire/Users/RegistrationRequests.php` | Fungsi B: senarai, butiran, lulus, tolak |
| `resources/views/livewire/users/register-user.blade.php` | UI Fungsi A |
| `resources/views/livewire/users/registration-requests.blade.php` | UI Fungsi B |
| `tests/Feature/UrusSetiaUserManagementTest.php` | 27 ujian UM4 |
| `docs/LAPORAN_FASA_UM4_PENGURUSAN_PENGGUNA.md` | Laporan ini |

### Diubah

| Fail | Sebab |
|---|---|
| `resources/views/livewire/pages/auth/register.blade.php` | Refactor untuk guna trait dikongsi. **Tingkah laku tidak berubah** (20/20 ujian UM2 lulus) |
| `routes/web.php` | Kumpulan route `urus-setia/pengguna` (`users.create`, `users.requests`), `role:pentadbir_urus_setia` |
| `resources/views/livewire/layout/navigation.blade.php` | Menu dropdown "Pengurusan Pengguna" untuk Urus Setia |
| `app/Models/Meeting.php` | `notificationRecipients()` menambah `->active()`; TODO lama dibuang |
| `app/Models/User.php` | `eligibleOfficersFor()` menambah `->active()` |

**Justifikasi dua perubahan terakhir:** UM4 melengkapkan kitaran status (`pending` → `active`/`rejected`). Tanpa penapisan ini, pemohon yang ditolak terus menerima e-mel makluman mesyuarat dan boleh dilantik sebagai pegawai projek. Perubahan adalah satu baris setiap satu, diuji, dan memenuhi TODO yang telah dirancang dalam `Meeting.php`.

---

## 5. Fungsi A — Pendaftaran oleh Urus Setia

**Route:** `GET /urus-setia/pengguna/daftar` → `users.create`

| Aspek | Pelaksanaan |
|---|---|
| Medan | No. KP, Nama Penuh, Jabatan/Agensi, Jawatan, Skim (readonly), Gred, Tahap Capaian, Tel. Pejabat, Tel. HP, E-mel Rasmi |
| Master data | Daripada database melalui scope `Agency::active()`, `Position::assignable()`, `$position->grades()->assignable()` (pivot `grade_position`). Tiada hard-code |
| Validation | Sama seperti UM2 (trait dikongsi): 12 digit KP unik, telefon `^0\d{8,10}$` wajib, e-mel unik tanpa mengira huruf (termasuk soft-deleted), gred mesti dipautkan dengan jawatan |
| Tahap Capaian | Whitelist server `RegisterUser::ASSIGNABLE_ROLES` = `pengguna_biasa`, `pentadbir_urus_setia`, `pengurusan`. Rule `Rule::in(...)` + `Rule::exists('roles','name')`. `superadmin` ditolak walaupun dihantar secara manual |
| Role | `assignRole()` Spatie (`model_has_roles`). `users.role_id` tidak disentuh |
| Kata laluan | `Str::password(12)` (dijana secara kriptografi, berbeza setiap kali), disimpan sebagai hash. **Tiada kata laluan hard-coded** |
| Paparan kata laluan | Dipaparkan **sekali** dalam panel hijau kepada Urus Setia melalui property `#[Locked] $temporaryPassword`. Tidak disimpan dalam session, log atau audit. Hilang selepas klik "Saya telah merekodkan kata laluan ini" atau muat semula halaman |
| Status | `active` |
| `must_change_password` | `true` |
| `approved_by` / `approved_at` | `NULL`. Bukan kelulusan Self Register; pencipta direkod dalam audit (`audits.user_id`) |
| Atomik | `DB::transaction` (cipta + assignRole) |
| Penghantaran berganda | `wire:loading.attr="disabled"` pada butang |

---

## 6. Fungsi B — Kelulusan dan Penolakan Self Register

**Route:** `GET /urus-setia/pengguna/permohonan` → `users.requests`

### Senarai

- Penapis: **Menunggu Kelulusan** (default) / **Ditolak** (rujukan). Pengguna aktif tidak dipaparkan.
- Lajur: Nama & E-mel, No. KP (**bertopeng**, cth. `050101****34`), Jabatan/Agensi, Jawatan / Gred, Tarikh Mohon, Status.
- Carian nama / e-mel / No. KP. Pagination 10. Bilangan permohonan `pending` dipaparkan dalam header.
- Susunan: permohonan terlama dahulu.

### Butiran (modal "Semak")

Nama, No. KP penuh, e-mel, agensi, jawatan, skim, gred, kedua-dua telefon, tarikh mohon dan status. Kata laluan tidak dipaparkan. Butang Lulus/Tolak hanya muncul bagi rekod `pending` yang bukan milik pengguna semasa.

### Tindakan

| | Luluskan | Tolak |
|---|---|---|
| Pengesahan UI | `wire:confirm` | `wire:confirm` ("tidak boleh dibatalkan") |
| Semakan kebenaran | `ensureUrusSetia()` (role + status aktif) | sama |
| Kunci baris | `lockForUpdate()` dalam `DB::transaction` | sama |
| Mesti `pending` | Ya, jika tidak: "Permohonan ini telah diproses…" | sama |
| Bukan diri sendiri | Ya, `$applicant->is(auth()->user())` | sama |
| `status` | `active` | `rejected` |
| `approved_by` | ID Urus Setia (daripada sesi, bukan input) | Tidak diubah (kekal `NULL`) |
| `approved_at` | `now()` | Tidak diubah (kekal `NULL`) |
| Rekod dipadam? | Tidak | **Tidak** (dikekalkan untuk rujukan) |
| Audit | OwenIt `updated`: `status` lama/baharu + `approved_*`, `user_id` = Urus Setia | `status` lama/baharu, `user_id` = Urus Setia |
| Mesej | "Permohonan {nama} telah diluluskan. Akaun kini aktif." | "Permohonan {nama} telah ditolak." |

Selepas diluluskan, pengguna boleh log masuk serta-merta (disahkan oleh ujian).

---

## 7. Authorization dan Keselamatan

| Kawalan | Pengesahan |
|---|---|
| Akses route | `auth` + `verified` + `active` + `role:pentadbir_urus_setia` |
| Akses action Livewire | `ensureUrusSetia()` dalam `mount()` dan **setiap** action: role `pentadbir_urus_setia` **dan** status aktif. Menutup jurang middleware route yang tidak diulang untuk permintaan Livewire |
| Peranan lain | `pengguna_biasa`, `pengurusan`, `superadmin`, tanpa peranan → 403 (halaman & action) |
| Superadmin | Tiada akses ke fungsi Urus Setia (selaras dengan corak modul Mesyuarat/Master Data). Urus Setia tiada akses ke fungsi Superadmin |
| Role tampering | Whitelist server; `superadmin` ditolak |
| Medan sensitif | `status`, `approved_by`, `approved_at`, `must_change_password`, `password`, `role_id` bukan property komponen (`PublicPropertyNotFoundException`). `temporaryPassword`, `createdUserName`, `viewingUuid` ialah `#[Locked]` |
| Mass assignment | `fill()` hanya dengan atribut profil tervalidasi; medan sensitif melalui `forceFill()` di server sahaja |
| Manipulasi ID | Tindakan menggunakan UUID; UUID tidak sah / bukan permohonan → `ModelNotFoundException`. Pengguna aktif tidak boleh "ditolak" (gagal semakan `pending`) |
| Kelulusan kendiri | Disekat di server menggunakan ID sesi |
| Concurrency | `lockForUpdate()` + semakan `pending` dalam transaction |
| Integriti | Transaction; kegagalan di tengah operasi tidak meninggalkan data separa (diuji) |
| Audit | Kata laluan (plaintext & hash) tidak muncul dalam audit (diuji) |
| Pemohon tidak aktif | Dikecualikan daripada penerima e-mel mesyuarat & senarai Pegawai Bertanggungjawab |

---

## 8. Ujian

### `Tests\Feature\UrusSetiaUserManagementTest`: 27 ujian, 27 lulus

**Pendaftaran oleh Urus Setia**

| # | Keperluan | Ujian |
|---|---|---|
| 1 | Urus Setia boleh membuka fungsi | `test_urus_setia_can_open_registration_and_requests_pages` |
| 2 | Tanpa kebenaran tidak boleh buka/hantar (4 kes) | `test_unauthorised_users_cannot_open_pages_or_call_actions` ×4, `test_actions_are_rechecked_when_role_is_revoked_mid_session` |
| 3 | Whitelist peranan (3 kes) | `test_urus_setia_can_register_whitelisted_roles` ×3 |
| 4 | `superadmin` ditolak | `test_superadmin_or_unknown_role_is_rejected_even_if_sent_manually` |
| 5 | Gred tidak berkaitan ditolak | `test_grade_not_linked_to_position_is_rejected` |
| 6 | E-mel & KP pendua ditolak | `test_duplicate_email_and_ic_are_rejected_including_soft_deleted` |
| 7 | Medan sensitif tidak boleh ditetapkan | `test_sensitive_fields_cannot_be_set_through_input` |
| 8 | Kata laluan sementara + wajib tukar | `test_created_account_uses_generated_temporary_password_and_must_change_it` |
| 9 | Kata laluan tiada dalam audit | `test_password_is_not_recorded_in_audit_log` |
| + | Tiada akaun separa jika gagal | `test_failed_registration_leaves_no_partial_account` |

**Kelulusan dan penolakan**

| # | Keperluan | Ujian |
|---|---|---|
| 1 | Urus Setia melihat `pending` (KP bertopeng) | `test_urus_setia_sees_pending_requests_with_masked_ic` |
| 2 | Tanpa kebenaran tidak boleh lihat/proses | `test_unauthorised_users_cannot_open_pages_or_call_actions`, `test_actions_are_rechecked_when_role_is_revoked_mid_session` |
| 3 | Kelulusan: status, `approved_by`, `approved_at`, audit, boleh log masuk | `test_approval_activates_account_and_records_approver` |
| 4 | Penolakan mengekalkan rekod | `test_rejection_keeps_record_without_approval_fields` |
| 5 | Bukan `pending` tidak boleh diproses semula | `test_requests_that_are_no_longer_pending_cannot_be_processed_again` |
| 6 | Tidak boleh lulus diri sendiri | `test_user_cannot_approve_own_registration` |
| 7 | Manipulasi ID | `test_invalid_or_unknown_uuid_cannot_be_processed`, `test_viewing_uuid_is_locked_against_manipulation`, `test_active_users_cannot_be_opened_as_requests` |
| 8 | Tiada kemas kini separa | `test_failed_approval_leaves_no_partial_update` |
| 9 | Self Register UM2 kekal berfungsi | `test_self_register_still_creates_pending_account` + `RegistrationTest` (20/20) |
| + | Pemohon tidak aktif dikecualikan daripada e-mel mesyuarat & pegawai | `test_pending_and_rejected_users_are_excluded_from_meeting_recipients_and_officers` |

### Keputusan keseluruhan

| Suite | Ujian | Lulus | Gagal |
|---|---|---|---|
| `UrusSetiaUserManagementTest` (UM4) | 27 | 27 | 0 |
| `Auth\RegistrationTest` (UM2, selepas refactor) | 20 | 20 | 0 |
| **Full suite** | **204** | **203** | **1** |

**Kegagalan:** `ProfileTest::test_user_can_delete_their_account`. Ini **isu baseline yang sama** (wujud sebelum UM1): User guna soft delete, manakala ujian Breeze menjangka hard delete. Tidak diubah, dirancang untuk UM8. **Tiada kegagalan baharu.**

Semua ujian menggunakan SQLite in-memory (`RefreshDatabase`). Tiada migration atau seeder dijalankan pada database pembangunan.

---

## 9. Pengesahan Operasi Pangkalan Data

- ✓ Tiada `migrate:fresh`, `migrate:refresh`, `migrate:rollback` atau `db:wipe`.
- ✓ Tiada `DROP TABLE`, `TRUNCATE` atau pemadaman data pukal.
- ✓ Tiada migration baharu dicipta atau dijalankan.
- ✓ Tiada seeder dijalankan.
- ✓ `.env` tidak diubah.
- ✓ Data pembangunan (3 pengguna sedia ada) tidak disentuh.

---

## 10. Isu Belum Selesai / Memerlukan Keputusan

| # | Perkara | Keadaan | Keputusan diperlukan |
|---|---|---|---|
| 10.1 | **Kuat kuasa `must_change_password`** | Flag ditetapkan `true`, tetapi tiada middleware/halaman yang memaksa pertukaran kata laluan. Pengguna boleh terus menggunakan kata laluan sementara | Laksanakan dalam UM6: middleware yang redirect ke halaman tukar kata laluan + kosongkan flag selepas tukar |
| 10.2 | **Status akaun didaftar oleh Urus Setia** | Ditetapkan `active`, berdasarkan: (a) keputusan 5 hanya mewajibkan kelulusan bagi Self Register; (b) pendaftar ialah pelulus itu sendiri; (c) konsisten dengan `UserManagement` sedia ada dan default DB | Sahkan. Jika perlu pengesahan lain, ubah satu baris dalam `RegisterUser::save()` |
| 10.3 | **Sebab penolakan** | Tiada medan dalam skema; tidak ditambah. Hanya status yang direkod dalam audit | Perlukah medan `rejection_reason` (+ `rejected_by`, `rejected_at`)? Memerlukan migration baharu |
| 10.4 | **Penghantaran kata laluan sementara** | Dipaparkan sekali kepada Urus Setia untuk disampaikan secara manual. Tiada e-mel | Perlukah e-mel kepada pengguna (bergantung pada konfigurasi mail sebenar, UM6)? |
| 10.5 | **Notifikasi** | Tiada notifikasi dilaksanakan: (a) kepada Urus Setia apabila ada permohonan baharu; (b) kepada pemohon apabila diluluskan/ditolak. `MAIL_MAILER=log` | Tentukan notifikasi yang diperlukan, saluran (e-mel/in-app) dan kandungan |
| 10.6 | **Pemohon yang ditolak** | Akaun kekal `rejected`. E-mel dan No. KP masih "digunakan", jadi mereka tidak boleh mendaftar semula | Perlukah aliran mohon semula / pemadaman lembut rekod ditolak? |
| 10.7 | **Pengurusan akaun sedia ada oleh Urus Setia** | UM4 hanya mendaftar dan meluluskan. Urus Setia belum boleh senarai/kemas kini/nyahaktif akaun aktif | Tentukan skop (UM5 / fasa berasingan) |
| 10.8 | Livewire persistent middleware | Tidak didaftarkan; diganti oleh `ensureUrusSetia()` dalam setiap action untuk komponen UM4 | Pertimbangkan secara global dalam UM7 |
| 10.9 | `Admin\UserManagement` (Superadmin) | Masih ada `Password123!`, teks bebas, tiada whitelist | UM5 |
| 10.10 | Dua sumber role (`users.role_id` vs Spatie) | `role_id` tidak diisi oleh UM2/UM4 | Ikut keputusan 6: kekal sementara |

---

## 11. Pengesahan Git

- ✓ **Tiada commit dan tiada push dibuat.**
- ✓ Tiada merge, reset, stash atau pertukaran branch.

---

## 12. Cadangan Langkah Seterusnya — UM5 (Pendaftaran oleh Superadmin)

1. **Gunakan semula** `HandlesUserProfileFields` dan corak `RegisterUser` (kata laluan sementara, `#[Locked]`, transaction, `ensure...()` dalam setiap action).
2. Whitelist Superadmin = `pentadbir_urus_setia`, `superadmin` sahaja (keputusan UM0).
3. Tulis semula `Admin\UserManagement`:
   - buang `Password123!` dan tukar "Reset Pass" kepada kata laluan sementara + `must_change_password`;
   - ganti medan teks bebas `position` dengan master Jawatan/Gred;
   - tambah `#[Locked]` pada `userId`;
   - betulkan bug carian `orWhere` tanpa kurungan;
   - paparkan role `pengurusan` dengan betul.
4. Putuskan sama ada Superadmin boleh mengemas kini akaun sedia ada yang dicipta oleh Urus Setia (dan sebaliknya).
5. Pertimbangkan untuk mendahulukan **UM6 (kuat kuasa tukar kata laluan)** sebelum akaun sebenar didaftarkan dalam production, kerana UM4 dan UM5 bergantung padanya (§10.1).

---

*UM4 selesai. Menunggu semakan dan arahan sebelum meneruskan UM5 / UM6 / UM7.*
