# LAPORAN FASA UM5 — PENGURUSAN PENGGUNA OLEH SUPERADMIN

**Projek:** SPPICT v2
**Tarikh:** 2026-10-13
**Lokasi laporan:** `docs/` (konvensyen laporan UM0–UM6)
**Rujukan:** `Prompt-UM5-PengurusanPengguna-SuperAdmin.md`, laporan UM0, UM2, UM4, UM6

---

## 1. Status Akhir

**BERJAYA DENGAN CATATAN**

Modul sedia ada `Admin\UserManagement` ditambah baik di tempat asal (route, nama komponen dan susun atur dikekalkan). Semua keperluan UM5 dipenuhi dan diuji (26 ujian baharu). Catatan:

1. **Borang kemas kini kini mewajibkan profil master data lengkap.** Menyunting akaun legacy (tanpa No. KP / Jawatan / Gred / telefon baharu) memerlukan medan tersebut diisi (§12.1).
2. **Dua ujian sedia ada diselaraskan** dengan API baharu (sasaran melalui UUID; profil lengkap). Tingkah laku yang diuji dikekalkan (§9).
3. Beberapa keputusan dasar direkodkan untuk pengesahan (§12).

---

## 2. Ringkasan Audit Sebelum Perubahan

| Perkara | Penemuan dalam `Admin\UserManagement` (sebelum UM5) |
|---|---|
| Reset kata laluan | `resetPassword(int $id)` menetapkan **`Password123!`** dan memaparkannya dalam flash. **Tiada semakan kebenaran dalam action** (UM6 membuktikan ia boleh dipanggil terus melalui `/livewire/update`) |
| Simpan pengguna | `saveUser()` **tiada semakan kebenaran** dalam action. `User::updateOrCreate(['id' => $this->userId], …)` |
| ID sasaran | `public ?int $userId`: **tidak `#[Locked]`** dan boleh diubah oleh browser |
| Peranan | `exists:roles,name` sahaja, **tiada whitelist**. Dropdown hard-coded 3 peranan (tiada `pengurusan`) |
| Medan | `position` teks bebas ("Jawatan & Gred"), `phone_number` tunggal, kata laluan ditaip manual oleh Superadmin |
| Agensi | Semua agensi (termasuk tidak aktif); validation `exists:agencies,id` tanpa semakan `is_active` |
| Carian | `where(name) ->orWhere(email)` **tanpa kurungan**: `roleFilter` terpintas apabila carian diisi |
| Paparan peranan | `pengurusan` dipaparkan sebagai "Pemohon" |
| Tindakan pemadaman / nyahaktif | **Tiada** (tidak ditambah) |
| Route | `GET /admin/users` → `auth`, `verified`, `active`, `password.changed`, `role:superadmin` |
| Komponen UM4/UM6 | `HandlesUserProfileFields`, `RegisterUser`, `RegistrationRequests`, `EnsurePasswordIsChanged` (persistent middleware), `password.change`: tidak diubah selain integrasi §3 |
| Baseline ujian (sebelum UM5) | 221 ujian: 220 lulus, 1 gagal (`ProfileTest::test_user_can_delete_their_account`) |

---

## 3. Fail Baharu dan Fail Diubah

### Baharu

| Fail | Tujuan |
|---|---|
| `tests/Feature/SuperadminUserManagementTest.php` | 26 ujian UM5 |
| `docs/LAPORAN_FASA_UM5_PENGURUSAN_PENGGUNA_SUPERADMIN.md` | Laporan ini |

### Diubah

| Fail | Perubahan |
|---|---|
| `app/Livewire/Admin/UserManagement.php` | Ditulis semula: trait profil dikongsi, whitelist peranan, kata laluan sementara, `ensureSuperadmin()` dalam setiap action, `#[Locked]`, transaksi, carian berkumpulan |
| `resources/views/livewire/admin/user-management.blade.php` | Medan master data, dropdown Jawatan → Skim (readonly) → Gred, panel kata laluan sementara, lajur No. KP (bertopeng) & Status, badge semua peranan, `wire:confirm` pada reset |
| `app/Livewire/Concerns/HandlesUserProfileFields.php` | **Tambahan serasi ke belakang:** `profileRules(?int $ignoreUserId = null)` untuk mod kemas kini, dan `fillProfileFieldsFrom(User)`. UM2/UM4 memanggil tanpa argumen, jadi tingkah laku tidak berubah |
| `tests/Feature/Auth/ForcedPasswordChangeTest.php` | Payload `/livewire/update` memanggil `resetPassword` dengan **UUID** (sebelum ini ID integer) |
| `tests/Feature/ApplicationOfficerTest.php` | `test_superadmin_is_warned_when_moving_an_active_officer_to_another_agency`: buka melalui UUID, isi profil master data lengkap, tetapkan peranan semasa pemohon. Amaran Pegawai Bertanggungjawab masih diuji |

Tiada fail UM2/UM4/UM6 lain diubah.

---

## 4. Perubahan Fungsi dan Keselamatan

| Fungsi | Sebelum | Selepas |
|---|---|---|
| Daftar | Teks bebas, kata laluan manual, sebarang peranan | Master data (trait UM2/UM4), kata laluan sementara, whitelist `pentadbir_urus_setia` / `superadmin` |
| Kemas kini | Sebarang medan/peranan; kata laluan pilihan | Profil master data; peranan semasa di luar whitelist **dikekalkan** tetapi tidak boleh diberikan kepada orang lain; kata laluan **tidak** diubah melalui borang |
| Reset kata laluan | `Password123!`, flash, tiada semakan | `Str::password(12)`, hash, `must_change_password = true`, `remember_token` diputar, paparan sekali, `wire:confirm` |
| Kebenaran | Middleware route sahaja | Middleware route + `ensureSuperadmin()` dalam `mount()`, `openModal()`, `saveUser()`, `resetPassword()` (peranan `superadmin` + status `active` + tiada kata laluan sementara) |
| ID sasaran | `?int $userId` boleh diubah | `#[Locked] ?string $userUuid`; sasaran dibaca semula dari DB pada simpan |
| Transaksi | Tiada | Cipta (simpan + `assignRole`), kemas kini (simpan + `syncRoles`), reset |
| Status akaun baharu | Default `active` | `active` (eksplisit, selaras dengan UM4) + `must_change_password = true` |
| Medan legacy | `position`, `phone_number` ditulis | **Tidak ditulis**; dibaca sahaja untuk paparan fallback. `role_id` tidak disentuh |
| Agensi tukar (amaran Pegawai Bertanggungjawab) | Ada | **Dikekalkan** |

---

## 5. Kata Laluan Sementara

| Aspek | Pelaksanaan |
|---|---|
| Penjanaan | `Illuminate\Support\Str::password(12)` (huruf besar/kecil, nombor, simbol; rawak secara kriptografi) |
| Penyimpanan | `Hash::make()` sahaja; plaintext tidak disimpan |
| Flag | `must_change_password = true` melalui `forceFill()`. Dikuatkuasakan oleh middleware UM6 `password.changed` tanpa kod tambahan (diuji: sasaran reset diredirect ke `/tukar-kata-laluan`) |
| Paparan | Property `#[Locked] $temporaryPassword` + `$temporaryPasswordFor`, dalam panel hijau "dipaparkan sekali sahaja". Dikosongkan oleh "Saya telah merekodkan kata laluan ini" atau muat semula halaman |
| Masa paparan | Ditetapkan **selepas** `DB::transaction` berjaya. Jika transaksi gagal (diuji dengan exception), tiada akaun dan tiada kata laluan dipaparkan. Jika validation gagal, `temporaryPassword` kekal `null` |
| Tidak disimpan dalam | Session/flash (`SESSION_ENCRYPT=false`), log aplikasi, audit (`password` dalam `$auditExclude`), mesej exception |
| E-mel | Tidak dihantar (UM6: `MAIL_MAILER=log`) |
| Reset juga | Memutar `remember_token`, jadi sesi "Ingat saya" sedia ada terbatal. Sesi aktif sasaran dipaksa ke halaman tukar kata laluan oleh UM6 |

---

## 6. Polisi Peranan

| Konteks | Peranan dibenarkan |
|---|---|
| **Daftar baharu** | `pentadbir_urus_setia`, `superadmin` (`UserManagement::ASSIGNABLE_ROLES`) |
| **Kemas kini** | Whitelist di atas **+ peranan semasa pengguna itu** (dibaca dari DB, bukan browser). Contoh: pengguna `pengurusan` boleh dikekalkan `pengurusan` atau dinaikkan ke Urus Setia/Superadmin, tetapi Superadmin tidak boleh menetapkan `pengurusan` kepada pengguna Urus Setia |
| Ditolak | `pengguna_biasa` / `pengurusan` (untuk akaun baharu atau pengguna lain), nama tidak wujud, kosong |

Penguatkuasaan di server: `Rule::in(allowedRoles($editing))` + `Rule::exists('roles','name')`. Penetapan melalui Spatie `assignRole()` / `syncRoles()` (`model_has_roles`). `syncRoles` hanya dipanggil apabila peranan benar-benar berubah, supaya peranan sedia ada tidak tersentuh semasa kemas kini profil.

Paparan senarai dan penapis meliputi **keempat-empat** peranan (`ROLE_LABELS`), termasuk badge `Pengurusan` yang betul dan "Tiada Peranan".

---

## 7. Validasi Master Data

Menggunakan `HandlesUserProfileFields::profileRules()` (sama seperti UM2/UM4):

| Medan | Peraturan |
|---|---|
| Agensi | `exists` aktif & tidak dipadam (dropdown: `Agency::active()`) |
| Jawatan | `exists` aktif & tidak dipadam + `Position::assignable()` (skim aktif, ≥1 gred sah) |
| Gred | `exists` aktif, bukan legacy, tidak dipadam + `exists:grade_position` bagi jawatan yang dipilih |
| Skim Perkhidmatan | **Tiada input.** Dipaparkan readonly daripada `Position → ServiceScheme` di server; tiada property untuk dihantar oleh browser (diuji) |
| No. KP | `digits:12`, unik (termasuk soft-deleted; abaikan diri sendiri semasa kemas kini) |
| E-mel | Format, unik tanpa mengira huruf (termasuk soft-deleted; abaikan diri sendiri semasa kemas kini) |
| Telefon | `^0\d{8,10}$`, wajib (polisi UM2) |

Polisi soft-deleted **tidak diubah**.

---

## 8. Carian dan Perlindungan Livewire

- **Carian:** `name` / `email` / `no_kad_pengenalan` dikumpulkan dalam `where(function …)`, jadi penapis peranan sentiasa dikenakan (diuji). `roleFilter` hanya diterima jika wujud dalam `ROLE_LABELS`. Pagination di-reset apabila carian/penapis berubah. No. KP dipaparkan bertopeng (`060606****66`).
- **Tindakan yang mengubah data atau mendedahkan maklumat:** `openModal`, `saveUser`, `resetPassword`. Kesemuanya memanggil `ensureSuperadmin()`. `dismissTemporaryPassword` / `closeModal` hanya mengosongkan state tempatan.
- **Property dikunci:** `userUuid`, `temporaryPassword`, `temporaryPasswordFor`.
- **Bukan property** (tidak boleh dihantar): `password`, `status`, `approved_by`, `approved_at`, `must_change_password`, `role_id`, `userId`, `service_scheme_id`.
- **Sasaran tindakan:** UUID. ID integer / UUID palsu → `ModelNotFoundException`, tiada perubahan (diuji). Reset hanya mengubah sasaran; pengguna lain tidak terjejas (diuji).
- **Persistent middleware UM6** (`password.changed`) kekal. Superadmin dengan kata laluan sementara turut ditolak oleh `ensureSuperadmin()`.

---

## 9. Ujian

### `Tests\Feature\SuperadminUserManagementTest`: 26 ujian, 26 lulus

| Kategori | Ujian |
|---|---|
| Akses | `test_superadmin_can_open_user_management`; `test_unauthorised_users_cannot_open_or_mount_component` ×4 (Urus Setia, Pengurusan, Pengguna Biasa, tanpa peranan); `test_every_action_rechecks_superadmin_role` |
| Peranan | `test_superadmin_can_register_whitelisted_roles` ×2 (Urus Setia, Superadmin); `test_dropdown_only_offers_superadmin_whitelist_for_new_account`; `test_non_whitelisted_or_unknown_roles_are_rejected_on_create` (`pengguna_biasa`, `pengurusan`, tidak wujud, kosong) |
| Validasi | `test_master_data_and_identity_validation` (KP, agensi tidak aktif, jawatan tidak aktif, telefon, e-mel & KP soft-deleted); `test_grade_not_linked_to_position_is_rejected`; `test_service_scheme_is_derived_by_server` |
| Kata laluan | `test_temporary_password_is_generated_hashed_and_shown_once_after_success`; `test_sensitive_fields_and_locked_properties_cannot_be_set`; `test_failed_creation_leaves_no_account_and_no_password`; `test_reset_password_generates_new_temporary_password_for_target_only` (termasuk redirect UM6); `test_page_load_does_not_reset_any_password`; `test_reset_with_unknown_uuid_changes_nothing`; `test_passwords_are_not_recorded_in_audit_or_log` |
| Kemas kini | `test_editing_keeps_existing_non_whitelisted_role`; `test_editing_cannot_assign_a_non_whitelisted_role_from_another_user`; `test_editing_can_change_role_within_whitelist_and_ignores_own_unique_values`; `test_edit_target_cannot_be_switched_by_payload` |
| Carian & paparan | `test_search_does_not_bypass_role_filter`; `test_actual_roles_including_pengurusan_are_displayed` |

### Ujian sedia ada yang diselaraskan

| Ujian | Sebab | Hasil |
|---|---|---|
| `ForcedPasswordChangeTest::test_livewire_actions_on_protected_pages_are_blocked` | `resetPassword` kini menerima UUID | Lulus. Kawalan positif (flag `false`) **dan** sekatan 403 (flag `true`) kekal |
| `ApplicationOfficerTest::test_superadmin_is_warned_when_moving_an_active_officer_to_another_agency` | API buka melalui UUID + profil master data wajib | Lulus. Amaran Pegawai Bertanggungjawab kekal |

### Regresi

| Suite | Keputusan |
|---|---|
| `Auth\RegistrationTest` (UM2) | Lulus |
| `UrusSetiaUserManagementTest` (UM4) | Lulus |
| `Auth\ForcedPasswordChangeTest` (UM6) | Lulus |
| `UserManagementReadinessTest` (UM1) | Lulus |
| **Full suite** | **247 ujian: 246 lulus, 1 gagal** |

---

## 10. Baseline vs Kegagalan Baharu

| | Sebelum UM5 | Selepas UM5 |
|---|---|---|
| Jumlah ujian | 221 | 247 (+26) |
| Lulus | 220 | 246 |
| Gagal | 1 | 1 |
| Kegagalan | `ProfileTest::test_user_can_delete_their_account` | **Sama.** Baseline (soft delete vs jangkaan hard delete Breeze); tidak berpunca daripada UM5; tidak diubah |

Semasa pembangunan, `ApplicationOfficerTest::test_superadmin_is_warned_…` gagal seketika kerana API lama (ID integer). Ia telah diselaraskan (§9) dan **tiada regresi baharu** berbaki.

---

## 11. Migrasi dan Database Pembangunan

- ✓ **Tiada migrasi baharu.** Migrasi terakhir kekal `2026_10_08_000002_add_user_management_columns_to_users_table.php` (UM1).
- ✓ Tiada migrasi atau seeder dijalankan dalam UM5.
- ✓ Database pembangunan (MySQL `sppictv2_db`) tidak disentuh. Semua ujian guna SQLite in-memory.
- ✓ Tiada perubahan status atau kata laluan secara pukal.

---

## 12. Isu Yang Memerlukan Keputusan Rasmi

| # | Perkara | Keadaan semasa | Keputusan diperlukan |
|---|---|---|---|
| 12.1 | **Kemas kini akaun legacy** | Borang mewajibkan No. KP, Jawatan (master), Gred, telefon pejabat & HP. Akaun lama (3 akaun sebenar: tiada KP/Jawatan master/Gred) mesti dilengkapkan semasa disunting | Sahkan, atau benarkan medan tertentu `nullable` dalam mod kemas kini |
| 12.2 | **Status akaun dicipta Superadmin** | `active` + kata laluan sementara (selaras UM4 §10.2) | Sahkan bersama keputusan UM4 |
| 12.3 | **Sempadan pengurusan akaun** | Superadmin boleh menyunting/reset **semua** akaun (termasuk dicipta Urus Setia, Superadmin lain, dan dirinya sendiri), sama seperti sebelum UM5. Urus Setia hanya boleh mendaftar/meluluskan (UM4) dan tidak boleh menyunting/reset | Perlukah sekatan, contohnya Superadmin tidak boleh menurunkan peranan dirinya sendiri / reset kata laluan sendiri melalui modul ini? |
| 12.4 | **Status melalui modul Superadmin** | Superadmin tidak boleh mengubah status (`pending`/`active`/`rejected`); kelulusan kekal milik Urus Setia (UM4). Tiada fungsi nyahaktif/padam | Perlukah Superadmin boleh nyahaktif/aktifkan semula akaun? Perlu status baharu (cth. `inactive`) dan mungkin migrasi |
| 12.5 | **Audit perubahan peranan** | `syncRoles`/`assignRole` (Spatie) tidak diaudit oleh OwenIt; perubahan profil diaudit | Perlukah audit khusus perubahan peranan (cth. event listener → `audits`)? |
| 12.6 | Tukar e-mel oleh Superadmin | `email_verified_at` tidak di-reset (pengesahan e-mel belum aktif, UM6 §8) | Tentukan bersama pengaktifan pengesahan e-mel |
| 12.7 | `RolesAndPermissionsSeeder` | Masih mencipta 3 akaun contoh dengan `Password123!` (3 kejadian). Di luar skop UM5; tidak dijalankan | UM7: hadkan kepada `local` atau guna kata laluan rawak + `must_change_password` |

---

## 13. Baki Risiko dan Cadangan Fasa Seterusnya (UM7 / UM8)

| Risiko | Cadangan |
|---|---|
| `active` bukan Livewire persistent middleware (hanya `password.changed`) | Daftar `EnsureUserIsActive` sebagai persistent middleware (UM7) |
| Komponen lain (cth. modul Mesyuarat, Master Data, Position/Grade) bergantung pada `ensure…()` masing-masing; belum diaudit untuk semakan status aktif | Audit menyeluruh semakan kebenaran Livewire (UM7) |
| Tiada had kadar pada Self Register, reset kata laluan Superadmin dan hantar semula pengesahan e-mel | Rate limiting (UM7) |
| Tiada `logoutOtherDevices` selepas tukar/reset kata laluan (hanya `remember_token` diputar semasa reset) | UM7 |
| Polisi kata laluan masih default Laravel (min 8) | Tetapkan `Password::defaults()` rasmi |
| `ProfileTest::test_user_can_delete_their_account` gagal (baseline); halaman Profil Breeze membenarkan pengguna memadam akaun sendiri (soft delete) | UM8: putuskan sama ada fungsi padam akaun sendiri dikekalkan, kemudian betulkan ujian |
| Kolum legacy `role_id`, `position`, `phone_number`, `positions.grade_id` | Rancang pembersihan selepas semua akaun dilengkapkan (keputusan 6) |
| Kerja UM1–UM6 + UM5 belum di-commit (41 entri) | Commit berperingkat apabila diluluskan |

---

## 14. Pengesahan Operasi Git dan Database

- ✓ Tiada `migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe`.
- ✓ Tiada `DROP`, `TRUNCATE` atau pemadaman pukal.
- ✓ Tiada seeder dijalankan; `.env`, SMTP dan pengesahan e-mel tidak diubah.
- ✓ **Tiada commit, push, merge, reset, stash atau pertukaran branch.** Branch kekal `feature/user-management`.
- ✓ Perubahan UM1/UM2/UM4/UM6 dalam working tree dikekalkan; tiada fail dipulihkan atau dibuang.
- ✓ `git diff --check` bersih. Tiada `Password123!` berbaki dalam `app/`, `resources/` atau `routes/`.

---

*UM5 selesai. Menunggu semakan laporan sebelum meneruskan UM7 atau fasa seterusnya.*
