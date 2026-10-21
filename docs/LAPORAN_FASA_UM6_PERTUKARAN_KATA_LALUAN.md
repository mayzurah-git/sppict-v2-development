# LAPORAN FASA UM6 — PENGUATKUASAAN PERTUKARAN KATA LALUAN & AUDIT PENGESAHAN E-MEL

**Projek:** SPPICT v2
**Tarikh:** 2026-10-12
**Rujukan:** `Prompt-UM6-PenguatkuasaanTukarPassword-Pengesahan-Emel.md`, laporan UM0, UM2, UM4

---

## 1. Status

**BERJAYA DENGAN CATATAN**

- **Fungsi A (wajib tukar kata laluan):** dilaksanakan sepenuhnya dan diuji, termasuk sekatan HTTP dan tindakan Livewire.
- **Fungsi B (pengesahan e-mel):** **audit sahaja, tiada perubahan.** Mengaktifkan `MustVerifyEmail` sekarang akan menyekat semua pengguna sedia ada, dan e-mel tidak boleh sampai kepada penerima (`MAIL_MAILER=log`). Keputusan diperlukan (§8, §12).

---

## 2. Branch dan Working Tree

| Perkara | Nilai |
|---|---|
| Branch | `feature/user-management` |
| Commit terakhir | `4585dfe` |
| Perubahan belum di-commit | UM1 + UM2 + UM4 + UM6 (36 entri dalam `git status --short`) |
| Staged | Tiada |
| Branch / merge / reset / stash | Tidak dilakukan |

---

## 3. Ringkasan Audit Sebelum Perubahan

| Perkara | Penemuan |
|---|---|
| `must_change_password` | Ditetapkan `true` oleh UM4 (`RegisterUser`), tetapi **tidak disemak di mana-mana**. Pengguna boleh terus guna kata laluan sementara |
| Halaman tukar kata laluan | Tiada halaman khusus. Borang Breeze `profile.update-password-form` wujud dalam `/profile`, tetapi profil mesti disekat semasa wajib tukar |
| `LoginForm` | Menyekat `pending`/`rejected` (UM2). Tiada semakan kata laluan sementara |
| Middleware | `active` pada `dashboard`, `profile` dan kumpulan route dalaman. Route `auth.php` (`verify-email`, `confirm-password`) hanya `auth` |
| Livewire | Middleware route **tidak diulang** untuk `/livewire/update` melainkan didaftarkan sebagai *persistent middleware*. Jika persistent middleware memulangkan redirect, Livewire menukarnya kepada `abort(redirect)` |
| Komponen tanpa semakan dalaman | `Admin\UserManagement::resetPassword()` (Superadmin) tiada semakan role/status dalam action, jadi bergantung sepenuhnya pada middleware |
| Reset kata laluan (e-mel) | `pages.auth.reset-password` → `Password::reset()` dengan `forceFill(password, remember_token)`. Tidak menyentuh `must_change_password` |
| Polisi kata laluan | `Password::defaults()` (default Laravel: min 8). Tiada polisi tersuai |
| `MustVerifyEmail` | **Tidak dilaksanakan** pada `User` |
| Mail | `MAIL_MAILER=log`, `MAIL_FROM_ADDRESS="hello@example.com"` |

---

## 4. Fail Dicipta dan Diubah

### Dicipta

| Fail | Tujuan |
|---|---|
| `app/Http/Middleware/EnsurePasswordIsChanged.php` | Middleware `password.changed` |
| `resources/views/livewire/pages/auth/change-password.blade.php` | Halaman Tukar Kata Laluan (Volt) |
| `tests/Feature/Auth/ForcedPasswordChangeTest.php` | 17 ujian UM6 |
| `docs/LAPORAN_FASA_UM6_PERTUKARAN_KATA_LALUAN.md` | Laporan ini |

### Diubah

| Fail | Perubahan |
|---|---|
| `bootstrap/app.php` | Alias `password.changed` |
| `app/Providers/AppServiceProvider.php` | `Livewire::addPersistentMiddleware([EnsurePasswordIsChanged::class])` |
| `routes/web.php` | `password.changed` pada `dashboard`, `profile` dan kumpulan route dalaman |
| `routes/auth.php` | Route baharu `password.change` (`/tukar-kata-laluan`, `auth` + `active`). `password.changed` pada `verify-email`, `verification.verify`, `confirm-password` |
| `resources/views/livewire/pages/auth/login.blade.php` | Selepas log masuk, akaun dengan kata laluan sementara terus ke `password.change` |
| `resources/views/livewire/pages/auth/reset-password.blade.php` | Reset melalui pautan e-mel turut menetapkan `must_change_password = false` |
| `app/Livewire/Users/RegisterUser.php`, `RegistrationRequests.php` | `ensureUrusSetia()` turut menolak akaun dengan kata laluan sementara |

---

## 5. Cara Middleware Menguatkuasakan Pertukaran

```
Permintaan → auth → [verified] → active → password.changed → route
                                             │
                     must_change_password = true?
                          │                     │
                        tidak                  ya
                          │                     ├─ X-Livewire / JSON → 403
                          ▼                     └─ HTTP biasa → redirect /tukar-kata-laluan
                       teruskan
```

- **Sumber kebenaran:** `$request->user()->must_change_password`. Guard sesi memuatkan semula rekod pengguna daripada database pada setiap permintaan, jadi nilai flag tidak datang daripada browser.
- **Susunan:** `active` dijalankan dahulu. Akaun `pending`/`rejected` dilog keluar sebelum semakan kata laluan.
- **Livewire:** `EnsurePasswordIsChanged` didaftarkan sebagai persistent middleware. Setiap `/livewire/update` daripada komponen yang dimuatkan pada route dilindungi akan menjalankan semula middleware ini dan ditolak dengan 403. Ujian membuktikannya dengan payload sebenar: panggilan `resetPassword` yang sama **berjaya** ketika flag `false` dan **ditolak** ketika flag `true` (§9).
- **Lapisan tambahan:** komponen UM4 (`RegisterUser`, `RegistrationRequests`) menyemak flag dalam `ensureUrusSetia()` pada setiap action.
- **Halaman tukar kata laluan:** semasa simpan, rekod pengguna dibaca semula (`User::findOrFail(Auth::id())`).

---

## 6. Route Semasa `must_change_password = true`

### Dibenarkan

| Route | Sebab |
|---|---|
| `GET /tukar-kata-laluan` (`password.change`) + tindakan Livewirenya | Halaman tukar kata laluan (tanpa `password.changed`, jadi tiada redirect loop) |
| Log keluar | Butang "Log Keluar" pada halaman tukar kata laluan (`Logout` action) |
| `POST /livewire/update` bagi komponen pada `password.change` | Teknikal Livewire |
| `/`, `/login`, `/register`, `/forgot-password`, `/reset-password/{token}` | Halaman awam / tetamu (tidak berubah) |

### Disekat (redirect ke `password.change`, atau 403 untuk Livewire/JSON)

`/dashboard`, `/profile`, semua route dalam kumpulan dalaman (`/application/*`, `/urus-setia/*`, `/admin/*`, `/mesyuarat/*/minit`, dokumen permohonan), `/verify-email`, `/verify-email/{id}/{hash}` dan `/confirm-password`.

### Selepas berjaya ditukar

`/tukar-kata-laluan` redirect ke dashboard (halaman hanya untuk akaun berflag). Pengguna lain menukar kata laluan melalui Profil.

---

## 7. Pengesahan, Hashing dan Audit Kata Laluan Baharu

| Kawalan | Pelaksanaan |
|---|---|
| Kata laluan semasa | Rule Laravel `current_password` (semakan hash terhadap pengguna yang log masuk) |
| Polisi | `Password::defaults()` (sama seperti Self Register & profil; tidak dilemahkan) |
| Pengesahan | `confirmed` |
| Mesti berbeza | `different:current_password`, digabungkan dengan semakan `current_password` |
| Hashing | `Hash::make()` |
| Simpan | `forceFill(['password' => …, 'must_change_password' => false])->save()` dalam `DB::transaction`. Satu kemas kini atomik; flag hanya dikosongkan jika simpan berjaya |
| Gagal | Flag kekal `true` dan kata laluan lama kekal (diuji dengan simulasi exception) |
| Input selepas ralat | Ketiga-tiga medan kata laluan dikosongkan; tiada kata laluan dalam mesej ralat |
| URL / flash | Tiada kata laluan dalam URL, query string atau flash. Flash hanya "Kata laluan berjaya ditukar." |
| Audit | OwenIt merekod `must_change_password: 1 → 0` sahaja. `password` dalam `$auditExclude`. Plaintext dan hash tidak muncul (diuji) |
| Tidak diubah | `status`, peranan, agensi, jawatan, gred, `approved_*`, `email_verified_at` |
| Reset melalui e-mel | `Password::reset` turut menetapkan `must_change_password = false`, kerana pengguna telah memilih kata laluan sendiri melalui pautan yang ditandatangani |

---

## 8. Keputusan Audit Pengesahan E-mel (Fungsi B)

| # | Soalan audit | Penemuan |
|---|---|---|
| 1 | `User` melaksanakan `MustVerifyEmail`? | **Tidak** |
| 2 | `Registered` menghasilkan e-mel? | **Tidak.** Listener `SendEmailVerificationNotification` hanya menghantar jika model `MustVerifyEmail` |
| 3 | Route pengesahan tersedia & dilindungi? | `verification.notice` (Volt), `verification.verify` (`signed`, `throttle:6,1`, `EmailVerificationRequest`: semak ID + `sha1(email)`). Tiada route `verification.send`; hantar semula ialah action Volt `sendVerification()` **tanpa had kadar** |
| 4 | Pengguna belum sah boleh akses laluan terhad? | Ya, middleware `verified` **tiada kesan** kerana model bukan `MustVerifyEmail`. Semua 3 pengguna sebenar masih `email_verified_at = NULL` |
| 5 | `pending`/`rejected` boleh salah guna laluan pengesahan? | Tidak praktikal: mereka tidak boleh log masuk (`LoginForm`) dan semua laluan pengesahan memerlukan `auth`. Kumpulan `verify-email` tiada `active`, tetapi sesi `pending` tidak boleh diwujudkan |
| 6 | `MAIL_MAILER=log`? | Ya. E-mel hanya ditulis ke `storage/logs`, tidak dihantar. `MAIL_FROM_ADDRESS` masih `hello@example.com` |
| 7 | URL, tandatangan, tempoh sah | Mekanisme Laravel standard (`URL::temporarySignedRoute`, tempoh `auth.verification.expire` default 60 minit) |

### Kenapa tidak dilaksanakan dalam UM6

1. **Pengguna sedia ada akan tersekat.** Ketiga-tiga akaun sebenar (Superadmin, Urus Setia, Pemohon) belum disahkan. Menandakan mereka "disahkan" secara automatik dilarang oleh prompt.
2. **E-mel tidak sampai.** Dengan `MAIL_MAILER=log`, pengguna tidak boleh menerima pautan, jadi semua akaun baharu akan tersekat selama-lamanya.
3. **Keputusan perniagaan belum dibuat** tentang urutan antara pengesahan e-mel dan kelulusan Urus Setia, serta sama ada akaun yang didaftar oleh pentadbir perlu disahkan.

### Cadangan pelaksanaan minimum (apabila diputuskan)

1. Konfigurasi SMTP sebenar + `MAIL_FROM_ADDRESS` rasmi (oleh pentadbir sistem, bukan Agent).
2. Putuskan backfill `email_verified_at` untuk akaun sedia ada (contohnya migration data untuk 3 akaun sebenar, dengan kelulusan).
3. `User implements MustVerifyEmail`.
4. Tambah had kadar pada `sendVerification()` (contohnya `RateLimiter`, 6/minit).
5. Tambah `active` pada kumpulan route `verify-email` untuk konsistensi.
6. Terjemah halaman `verify-email` ke BM.
7. Ujian: `Notification::fake()`, pautan sah/tidak sah, sekatan `verified`, status & `approved_*` tidak berubah, `pending`/`rejected` tidak mendapat akses.

---

## 9. Keputusan Ujian

### `Tests\Feature\Auth\ForcedPasswordChangeTest`: 17 ujian, 17 lulus (108 assertion)

| # | Keperluan prompt | Ujian |
|---|---|---|
| 1 | Diarahkan ke halaman tukar selepas log masuk | `test_user_with_temporary_password_is_sent_to_change_page_after_login` |
| 2 | Dashboard & laluan dalaman disekat | `test_internal_routes_are_blocked_until_password_is_changed` (8 URI + JSON + X-Livewire) |
| 3 | Halaman tukar boleh dibuka | `test_change_password_page_is_accessible`, `test_change_password_page_requires_login_and_active_account` |
| 4 | Kata laluan semasa salah ditolak | `test_wrong_current_password_is_rejected` |
| 5 | Tidak memenuhi polisi ditolak | `test_new_password_must_satisfy_policy` |
| 6 | Pengesahan tidak sepadan ditolak | `test_password_confirmation_must_match` |
| 7 | Sama dengan semasa ditolak | `test_new_password_must_differ_from_current_password` |
| 8–10 | Hash, flag `false` selepas berjaya, dashboard dibuka, log masuk dengan kata laluan baharu | `test_successful_change_hashes_password_clears_flag_and_unlocks_dashboard` |
| 11 | Flag kekal jika gagal | `test_flag_stays_set_when_save_fails` |
| 12 | `pending`/`rejected` tetap tidak boleh log masuk | `test_pending_and_rejected_users_still_cannot_log_in` |
| 13 | Pengguna tanpa flag tidak terjejas | `test_users_without_flag_are_unaffected` |
| 14 | Pintasan URL / Livewire | `test_livewire_actions_on_protected_pages_are_blocked` (payload `/livewire/update` sebenar, dengan kawalan positif), `test_urus_setia_actions_are_blocked_with_temporary_password`, `test_flag_cannot_be_changed_through_component_input` |
| 15 | Kata laluan tiada dalam audit | `test_password_is_not_recorded_in_audit` |
| + | Reset melalui e-mel mengosongkan flag | `test_password_reset_via_email_clears_flag` |

### Keseluruhan

| Suite | Ujian | Lulus | Gagal |
|---|---|---|---|
| `ForcedPasswordChangeTest` (UM6) | 17 | 17 | 0 |
| **Full suite** | **221** | **220** | **1** |

Semua ujian guna SQLite in-memory (`phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `MAIL_MAILER=array`). Database pembangunan tidak disentuh.

---

## 10. Kegagalan Baharu vs Baseline

| Ujian | Jenis | Catatan |
|---|---|---|
| `ProfileTest::test_user_can_delete_their_account` | **Baseline** (sama seperti UM1/UM2/UM4) | User guna soft delete; ujian Breeze menjangka hard delete. Tidak diubah (UM8) |

**Tiada kegagalan baharu.** Ujian UM2 (20) dan UM4 (27) kekal lulus.

---

## 11. Pengesahan Operasi Pangkalan Data

- ✓ Tiada `migrate:fresh`, `migrate:refresh`, `migrate:rollback` atau `db:wipe`.
- ✓ Tiada `DROP TABLE`, `TRUNCATE` atau pemadaman pukal.
- ✓ Tiada migration baharu.
- ✓ Tiada seeder dijalankan.
- ✓ Tiada kata laluan pengguna sedia ada ditetapkan semula; tiada flag diubah secara pukal.
- ✓ `.env` tidak diubah.

---

## 12. Isu Belum Selesai dan Keputusan Diperlukan

| # | Perkara | Keputusan / tindakan |
|---|---|---|
| 12.1 | **Pengesahan e-mel** belum diaktifkan (§8) | Sahkan SMTP, backfill akaun sedia ada, urutan dengan kelulusan |
| 12.2 | `sendVerification()` tiada had kadar | Tambah apabila 12.1 dilaksanakan |
| 12.3 | **`Admin\UserManagement::resetPassword()` masih set `Password123!`** dan tidak menetapkan `must_change_password` (disahkan semula oleh ujian kawalan UM6) | UM5: guna kata laluan sementara + flag |
| 12.4 | `active` belum didaftar sebagai Livewire persistent middleware (hanya `password.changed`) | Dicadangkan dalam UM7 (satu baris dalam `AppServiceProvider`) |
| 12.5 | Tiada log keluar sesi lain selepas tukar kata laluan (`Auth::logoutOtherDevices`) | Pertimbangkan dalam UM7 |
| 12.6 | Polisi kata laluan hanya default Laravel (min 8) | Tentukan polisi rasmi (panjang, campuran aksara, `uncompromised`) melalui `Password::defaults()` dalam `AppServiceProvider` |
| 12.7 | Self Register tidak diwajibkan tukar kata laluan | Selaras dengan prompt (tiada keputusan rasmi) |

---

## 13. Pengesahan Git

- ✓ **Tiada commit dan tiada push dibuat.**
- ✓ Tiada merge, reset, stash atau pertukaran branch.

---

## 14. Cadangan Langkah Seterusnya — UM5 (Pendaftaran oleh Superadmin)

UM6 kini menyediakan mekanisme yang boleh digunakan semula terus oleh UM5:

1. **Daftar pengguna oleh Superadmin:** guna `HandlesUserProfileFields` + corak `RegisterUser`. Untuk kata laluan sementara, tetapkan `forceFill(['must_change_password' => true])`. Middleware `password.changed` akan menguatkuasakannya secara automatik tanpa kod tambahan.
2. **Whitelist Superadmin:** `pentadbir_urus_setia`, `superadmin` sahaja.
3. **"Reset Pass" Superadmin:** gantikan `Password123!` dengan `Str::password(12)`, paparkan sekali melalui property `#[Locked]` (bukan flash, kerana `SESSION_ENCRYPT=false`), dan tetapkan `must_change_password = true`.
4. **`Admin\UserManagement`:** tambah `ensureSuperadmin()` dalam setiap action, `#[Locked]` pada `userId`, dan betulkan carian `orWhere`.
5. Tambah ujian UM5 mengikut corak `UrusSetiaUserManagementTest` dan `ForcedPasswordChangeTest`.

---

*UM6 selesai. Menunggu semakan dan arahan sebelum meneruskan UM5 / UM7 / UM8.*
