# Laporan Fasa UM12 – Pengemaskinian Profil Pengguna Sendiri

**Projek:** SPPICT v2
**Tarikh:** 2026-10-21
**Branch:** `feature/ui-design`
**Rujukan:** `Prompt-UM12-Pengemaskinian-Profil-Pengguna-Sendiri.md`

**Status: BERJAYA.** 381 ujian: **381 lulus, 0 gagal** (14 ujian UM12 baharu). Tiada migration.

---

## 1. Ringkasan Pemeriksaan Kod Sebelum Perubahan

| Perkara | Penemuan |
|---|---|
| Git | Branch `feature/ui-design`. UM1–UM11 telah di-commit oleh pengguna (`e1f059c`, kemudian `840d4da` reka bentuk UI). Dua fail belum di-commit milik pengguna (`application-list.blade.php`, `UrusSetiaDashboardTest.php`) **tidak disentuh** |
| Pautan Profil | Sudah wujud dalam menu pengguna kanan atas (`navigation.blade.php`, `route('profile')`, desktop & mudah alih) |
| Route | `GET /profile` → `Route::view('profile','profile')` dengan `auth` + `active` + `password.changed`. `active` & `password.changed` juga persistent untuk Livewire (UM7) |
| Halaman | `resources/views/profile.blade.php`: `profile.update-profile-information-form` + `profile.update-password-form` (padam akaun dibuang dalam UM7A) |
| Komponen Profil (sebelum) | Mengemas kini **nama & e-mel** sahaja (UM8). Tiada telefon / gred |
| Model `User` | `phone_office`, `phone_mobile` (varchar), `grade_id` (FK), `email` (unik): semuanya dalam `$fillable`. Medan sensitif (`status`, `approved_*`, `must_change_password`, `inactive_reason`, `status_changed_*`) tidak dalam `$fillable` |
| Jawatan–Gred | Pivot `grade_position`; `Position::assignable()`, `Grade::assignable()` |
| Pengesahan e-mel | `MustVerifyEmail` **tidak** diaktifkan (keputusan UM7A); `MAIL_MAILER=log` |
| Audit | `User` Auditable (OwenIt); `password`, `remember_token` dikecualikan |
| Baseline ujian | 367/367 lulus |

**Percanggahan dengan prompt:** prompt menetapkan Nama sebagai **rujukan sahaja**, sedangkan Profil sebelum ini membenarkan nama diubah (UM8). Diikut prompt: **nama tidak lagi boleh diubah melalui Profil**.

---

## 2. Fail Ditambah dan Diubah

| Fail | Tindakan | Sebab |
|---|---|---|
| `resources/views/livewire/profile/update-profile-information-form.blade.php` | Diubah (komponen sedia ada; tiada halaman pendua) | Medan boleh dikemas kini & maklumat rujukan |
| `tests/Feature/SelfProfileUpdateTest.php` | Ditambah | 14 ujian UM12 |
| `tests/Feature/ProfileTest.php` | Diselaraskan | Dua ujian Breeze menetapkan `name`; kini menetapkan telefon + e-mel |
| `tests/Feature/UserModuleIntegrationTest.php` | Diselaraskan | Ujian UM8 menganggap `grade_id` tidak boleh diubah & `name` boleh diubah; dikemas kini mengikut UM12 |
| `docs/LAPORAN_FASA_UM12_PROFIL_PENGGUNA_SENDIRI.md` | Ditambah | Laporan ini |

Tiada perubahan pada route, navigasi, model, middleware, migration atau modul lain.

---

## 3. Pautan Profil → Borang

```
Menu pengguna (kanan atas) → "Profil" → GET /profile [auth, active, password.changed]
   → profile.blade.php
       ├─ livewire:profile.update-profile-information-form  (UM12)
       └─ livewire:profile.update-password-form            (sedia ada)
```

Borang memuatkan nilai semasa dari pangkalan data (`User::findOrFail(Auth::id())`). Selepas simpan, mesej **"Profil berjaya dikemas kini."** dipaparkan; ralat validasi dipaparkan di bawah medan dalam BM dan nilai borang dikekalkan.

---

## 4. Medan

| Medan | Boleh dikemas kini? | Catatan |
|---|---|---|
| No. Telefon (Pejabat) | ✅ | Teks; sifar di hadapan dikekalkan |
| No. Telefon (HP) | ✅ | Teks; sifar di hadapan dikekalkan |
| Gred Jawatan | ✅ (bersyarat) | Pilihan: Gred **aktif** yang dipautkan kepada **Jawatan pengguna sendiri** (pivot). Jika pengguna tiada Jawatan yang sah, medan dikunci dengan nota "hubungi Urus Setia" dan gred tidak diubah |
| E-mel Rasmi | ✅ | Format & keunikan |
| No. Kad Pengenalan | ❌ Rujukan | |
| Nama | ❌ Rujukan | (sebelum ini boleh diubah; kini dikunci mengikut prompt) |
| Jabatan/Agensi | ❌ Rujukan | |
| Jawatan | ❌ Rujukan | |
| Skim Perkhidmatan | ❌ Rujukan | Dari Jawatan |
| Tahap Capaian | ❌ Rujukan | Label BM (Spatie) |
| Status, kelulusan, kata laluan sementara, sebab tidak aktif, peranan | ❌ Tidak dipaparkan / tidak boleh diubah | |

Nota pada borang: *"Untuk meminda maklumat di atas, sila hubungi Urus Setia."*

---

## 5. Peraturan Pengesahan dan Kawalan Akses

### Validasi (server)

| Medan | Peraturan |
|---|---|
| Telefon pejabat / HP | wajib; `^0\d{8,10}$` (sama dengan UM2/UM4/UM5) |
| Gred | wajib (jika Jawatan sah); `exists:grades` aktif, bukan legacy, tidak dipadam; `exists:grade_position` dengan `position_id` **pengguna semasa dari DB** |
| E-mel | wajib; format e-mel; maks 255; dinormalisasi huruf kecil; unik tanpa mengira huruf termasuk akaun soft-deleted, kecuali akaun sendiri; mesej *"E-mel Rasmi ini telah didaftarkan."* (tiada butiran dalaman) |

### Kawalan akses

| Kawalan | Pelaksanaan |
|---|---|
| Semua tahap capaian | Superadmin, Urus Setia, Pengurusan, Pengguna Biasa (diuji) |
| Sasaran | Sentiasa pengguna log masuk (`Auth::id()`); **tiada** property ID/UUID pada komponen (diuji: `user_id`, `id`, `uuid` tidak wujud) |
| Senarai medan eksplisit | `fill()` hanya `phone_office`, `phone_mobile`, `email` (+ `grade_id` jika Jawatan sah) |
| Medan sensitif | Bukan property Livewire, jadi manipulasi payload ditolak (`PublicPropertyNotFoundException`, diuji) |
| Akaun tidak aktif / pending / rejected | Route `active` (redirect log masuk); persistent middleware Livewire (403 pada `/livewire/update`, diuji); `canUseSystem()` dalam tindakan (403, diuji) |
| Kata laluan sementara | Route `password.changed` → `/tukar-kata-laluan` (diuji) |

---

## 6. Pengesahan E-mel

- Mekanisme pengesahan e-mel **belum diaktifkan** (`User` bukan `MustVerifyEmail`; keputusan UM7A). Tiada aliran baharu direka.
- Apabila e-mel berubah, `email_verified_at` ditetapkan `NULL` (tingkah laku Breeze sedia ada dikekalkan), jadi e-mel baharu **tidak dianggap disahkan**. Ini tidak mengunci pengguna kerana `verified` tiada kesan tanpa `MustVerifyEmail`.
- Blok "e-mel belum disahkan / hantar semula" sedia ada dikekalkan dan hanya dipaparkan jika `MustVerifyEmail` diaktifkan kelak.
- **Cadangan susulan:** apabila pengesahan e-mel diaktifkan (prasyarat dalam laporan UM7A §3.5), e-mel baharu dari Profil akan automatik memerlukan pengesahan semula.

---

## 7. Perubahan Pangkalan Data

**Tiada.** Semua medan sudah wujud (UM1). Tiada migration dicipta atau dijalankan.

---

## 8. Audit

- Satu simpanan model dalam `DB::transaction` menghasilkan **satu** rekod audit OwenIt `updated` (nilai lama/baharu medan yang berubah, `user_id` = pengguna sendiri). Diuji: satu rekod sahaja; tiada `password` / `remember_token`.
- Kegagalan semasa simpan tidak meninggalkan data separa (diuji dengan simulasi exception).

---

## 9. Ujian

### `Tests\Feature\SelfProfileUpdateTest`: 14 ujian, 14 lulus

| # (prompt) | Ujian |
|---|---|
| 1–5 | `test_every_role_can_view_and_update_own_profile` ×4 (Superadmin, Urus Setia, Pengurusan, Pengguna Biasa: lihat rujukan; kemas kini telefon pejabat/HP, gred, e-mel) |
| 6–7 | `test_invalid_or_duplicate_email_is_rejected` (format, kosong, milik akaun lain tanpa mengira huruf, akaun soft-deleted; tiada mesej SQL) |
| 10 | `test_invalid_inactive_or_unlinked_grades_are_rejected`, `test_grade_is_locked_when_user_has_no_valid_position` |
| (telefon) | `test_phone_numbers_must_be_digits_only` |
| 8–9 | `test_only_own_profile_is_updated_and_sensitive_fields_are_protected` |
| 11 | `test_inactive_or_blocked_users_cannot_use_profile`, `test_livewire_payload_from_deactivated_session_is_blocked` (payload `/livewire/update` sebenar; kata laluan sementara → tukar kata laluan) |
| 13 | `test_profile_update_is_audited_once_without_secrets`, `test_failed_save_leaves_no_partial_update` |
| 12 | `test_profile_link_is_in_user_menu_and_password_form_still_works` + full suite |

### Keseluruhan (14)

| | Sebelum UM12 | Selepas UM12 |
|---|---|---|
| Jumlah | 367 | 381 |
| Lulus | 367 | **381** |
| Gagal | 0 | **0** |

Semasa pelaksanaan, 3 ujian sedia ada gagal kerana bergantung pada tingkah laku lama (nama boleh diubah, gred tidak boleh diubah di Profil). Ujian tersebut diselaraskan mengikut keperluan UM12 (§2).

---

## 10. Isu Belum Selesai dan Cadangan

| # | Perkara | Cadangan |
|---|---|---|
| 10.1 | Pengguna boleh menukar **E-mel Rasmi** sendiri tanpa pengesahan (pengesahan e-mel belum aktif) | Aktifkan pengesahan e-mel apabila SMTP sedia (UM7A §3.5), atau hadkan pertukaran e-mel kepada domain rasmi (cth. `@ns.gov.my`) jika diputuskan |
| 10.2 | Pengguna legacy tanpa Jawatan yang sah tidak boleh memilih Gred | Urus Setia/Superadmin melengkapkan Jawatan melalui modul pengurusan pengguna |
| 10.3 | Telefon pejabat & HP diwajibkan (konsisten dengan borang lain) | Pengguna legacy tanpa telefon perlu mengisi kedua-duanya semasa simpan pertama |
| 10.4 | Nama kini tidak boleh diubah oleh pengguna | Pembetulan nama melalui Urus Setia/Superadmin |

---

## 11. Pengekalan Perubahan dan Data

- ✓ Perubahan UM1–UM11/UM11A dikekalkan; tiada fail dipadam.
- ✓ Fail belum di-commit milik pengguna (`application-list.blade.php`, `UrusSetiaDashboardTest.php`) tidak disentuh.
- ✓ Tiada perubahan data pembangunan; pendaftaran, kelulusan, tahap capaian, status akaun, sebab tidak aktif, log masuk terakhir dan master data tidak terjejas (full suite lulus).

---

## 12. Pengesahan Operasi

- ✓ Tiada `migrate:fresh/refresh`, `db:wipe`, rollback, `DROP`, `TRUNCATE` atau pemadaman data.
- ✓ Tiada migration atau seeder dijalankan.
- ✓ `.env` tidak diubah.
- ✓ **Tiada commit, push, merge, reset, stash atau pertukaran branch.** `git diff --check` bersih.

---

*UM12 selesai. Menunggu semakan pengguna.*
