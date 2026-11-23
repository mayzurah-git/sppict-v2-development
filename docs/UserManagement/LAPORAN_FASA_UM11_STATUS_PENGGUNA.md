# LAPORAN FASA UM11 — PENGURUSAN STATUS AKTIF DAN TIDAK AKTIF PENGGUNA

**Projek:** SPPICT v2
**Tarikh:** 2026-10-21
**Branch:** `feature/user-management`
**Rujukan:** `Prompt-UM11-Pengurusan Status Aktif dan Tidak Aktif Pengguna.md` (bertajuk "Fasa UM10" dalam kandungan; dinamakan **UM11** kerana UM10 telah digunakan untuk *Kemas Kini Tahap Capaian*. Laporan ini menggantikan cadangan nama `LAPORAN_FASA_UM10_STATUS_PENGGUNA.md` supaya tidak bertindih).

---

## 1. Status

**BERJAYA**

- Urus Setia dan Superadmin boleh menandakan pengguna Aktif sebagai **Tidak Aktif** dengan salah satu daripada tiga sebab wajib, dan boleh **mengaktifkan semula** akaun Tidak Aktif.
- Pengguna Tidak Aktif disekat pada log masuk, sesi sedia ada dan permintaan Livewire.
- Perubahan diaudit. Peranan Spatie tidak disentuh.

**Ujian:** 352 ujian: **352 lulus, 0 gagal** (18 ujian UM11 baharu).
**Migration:** satu migration tambahan; **telah dijalankan** pada DB pembangunan selepas kebenaran pengguna (2026-10-21).

### Keputusan pengguna (2026-10-21)

1. Kebenaran diberikan untuk menjalankan migration.
2. Akaun Tidak Aktif boleh **diaktifkan semula oleh Urus Setia dan Superadmin**.

---

## 2. Ringkasan Struktur Semasa (Audit)

| Perkara | Penemuan |
|---|---|
| `users.status` | `varchar(20)`, nilai `pending`, `active`, `rejected` (enum `UserStatus`) |
| Penguatkuasaan | `LoginForm`, `EnsureUserIsActive` (HTTP + persistent Livewire), `User::canUseSystem()`, `ApplicationPolicy::create`, `User::eligibleOfficersFor`, `Meeting::notificationRecipients`, semuanya berasaskan `isActive()` / `scopeActive()` |
| Status permohonan vs akaun | `pending`/`rejected` ialah status **permohonan pendaftaran** (UM2/UM4). Status baharu `inactive` ialah status **akaun** bagi akaun yang pernah aktif; kedua-duanya dipisahkan |
| Sebab / tarikh / pegawai | Tiada medan. Audit OwenIt tidak merekod dalam konsol dan bukan sumber data paparan yang sesuai, maka **medan tambahan** diperlukan |
| Audit | Audit `updated` OwenIt bagi model `User` merekod perubahan atribut + `user_id` pelaku + masa. Tiada audit custom tambahan diperlukan |

---

## 3. Migration

`database/migrations/2026_10_19_000001_add_inactive_status_columns_to_users_table.php`: **tambahan sahaja**:

| Kolum | Jenis | Tujuan |
|---|---|---|
| `inactive_reason` | `varchar(30)` nullable | `retired` / `transferred_out` / `inactive_over_6_months` |
| `status_changed_at` | `timestamp` nullable | Masa status akaun terakhir ditukar |
| `status_changed_by` | FK `users.id` nullable, `ON DELETE SET NULL` | Pegawai yang menukar status |

`users.status` sedia ada menampung nilai baharu `inactive` tanpa perubahan skema.

| Perkara | Keadaan |
|---|---|
| Dijalankan pada DB pembangunan | ✓ 2026-10-21 (`--pretend` disemak dahulu: hanya `ADD COLUMN` + FK) |
| Data sedia ada | Tidak diubah. Selepas migration: 5 `active`, 1 `rejected`, 0 dengan `inactive_reason` |
| Legacy | Tiada kolum dibuang/diubah |

---

## 4. Tingkah Laku Baharu

### Nilai dan label

| Kod | Label UI |
|---|---|
| `UserStatus::Inactive` (`inactive`) | Tidak Aktif |
| `InactiveReason::Retired` (`retired`) | Bersara |
| `InactiveReason::TransferredOut` (`transferred_out`) | Bertukar Keluar |
| `InactiveReason::InactiveOver6Months` (`inactive_over_6_months`) | Tidak Aktif Melebihi 6 Bulan |

### Nyahaktif (Aktif → Tidak Aktif)

1. Butang **⛔ Nyahaktif** (hanya bagi akaun Aktif) membuka modal dengan medan wajib **Sebab Tidak Aktif**.
2. Butang **Nyahaktif** memaparkan dialog pengesahan: *"Nyahaktifkan akaun X atas sebab Y? Akses ke sistem akan disekat serta-merta."*
3. Server: sebab disahkan dengan `Rule::enum(InactiveReason)`; dalam transaksi + `lockForUpdate()`, sasaran dibaca semula dalam skop pelaku dan mesti masih `active`, dan Superadmin aktif terakhir ditolak.
4. Disimpan: `status=inactive`, `inactive_reason`, `status_changed_at`, `status_changed_by`.
5. Mesej: *"Akaun X telah ditukar kepada Tidak Aktif (Bersara). Akses ke sistem disekat serta-merta."* Jika pengguna masih Pegawai Bertanggungjawab, amaran supaya pegawai baharu dilantik turut dipaparkan.

### Aktifkan semula (Tidak Aktif → Aktif)

1. Butang **✅ Aktifkan Semula** (hanya bagi akaun Tidak Aktif) dengan dialog pengesahan.
2. Server: dalam transaksi + `lockForUpdate()`, mesti `inactive` dan dalam skop pelaku.
3. Disimpan: `status=active`, `inactive_reason=NULL`, `status_changed_at`, `status_changed_by`. Kata laluan, peranan dan profil **tidak berubah**.
4. Mesej: *"Akaun X telah diaktifkan semula."*

### Skop pelaku

| Pelaku | Halaman | Boleh nyahaktif / aktifkan semula |
|---|---|---|
| **Urus Setia** | Pengurusan Pengguna → **Senarai Pengguna** | Pengguna Biasa, Pengurusan, Urus Setia lain. **Bukan** Superadmin, **bukan** diri sendiri |
| **Superadmin** | **Pengurusan Pengguna & Peranan** | Semua akaun, kecuali menyahaktifkan Superadmin aktif terakhir |

### Paparan

- **Lajur Status** (kedua-dua halaman): badge Aktif / Tidak Aktif / Menunggu Kelulusan / Ditolak. Bagi Tidak Aktif turut dipaparkan **sebab**, **tarikh** dan **nama pegawai** yang menukar status.
- **Senarai Pengguna Urus Setia:** kini memaparkan pengguna Aktif **dan** Tidak Aktif dengan penapis **Status** baharu. Kemas kini profil hanya bagi akaun Aktif. `pending`/`rejected` kekal di Permohonan Pendaftaran.
- Rekod audit penuh tidak dipaparkan kepada pengguna; hanya maklumat status pada rekod pengguna.

---

## 5. Fail Ditambah dan Diubah

| Fail | Tindakan | Sebab |
|---|---|---|
| `database/migrations/2026_10_19_000001_add_inactive_status_columns_to_users_table.php` | Ditambah | Kolum status Tidak Aktif |
| `app/Enums/InactiveReason.php` | Ditambah | 3 sebab + label BM |
| `app/Livewire/Concerns/HandlesUserDeactivation.php` | Ditambah | Logik nyahaktif / aktifkan semula dikongsi |
| `resources/views/livewire/partials/user-status-badge.blade.php` | Ditambah | Badge status + butiran Tidak Aktif |
| `resources/views/livewire/partials/deactivate-user-modal.blade.php` | Ditambah | Modal sebab + pengesahan |
| `tests/Feature/UserStatusManagementTest.php` | Ditambah | 18 ujian UM11 |
| `docs/LAPORAN_FASA_UM11_STATUS_PENGGUNA.md` | Ditambah | Laporan ini |
| `app/Enums/UserStatus.php` | Diubah | `Inactive` + label + mesej log masuk |
| `app/Models/User.php` | Diubah | Cast `inactive_reason`/`status_changed_at`; `isInactive()`; `isLastActiveSuperadmin()` (dipindah dari UM10 untuk dikongsi); relationship `statusChanger()`; nota `$fillable` |
| `app/Livewire/Admin/UserManagement.php` | Diubah | Guna trait; skop Superadmin; guna `User::isLastActiveSuperadmin()`; eager-load `statusChanger` |
| `app/Livewire/Users/ManageUsers.php` | Diubah | Guna trait; `scopedUsers()` (semua status) vs `editableUsers()` (aktif); penapis status |
| `resources/views/livewire/admin/user-management.blade.php` | Diubah | Badge partial; butang Nyahaktif / Aktifkan Semula; modal |
| `resources/views/livewire/users/manage-users.blade.php` | Diubah | Lajur & penapis Status; butang; modal; mesej ralat |

Medan baharu **tidak** dalam `$fillable` (hanya `forceFill()` oleh aliran status).

---

## 6. Kawalan Keselamatan

| Kawalan | Pelaksanaan |
|---|---|
| Authorization | Route `role:` (persistent Livewire) + `ensureUrusSetia()` / `ensureSuperadmin()` dalam setiap tindakan (`openDeactivate`, `deactivate`, `reactivate`) |
| Pengguna Biasa / Pengurusan | 403 pada komponen & halaman (diuji) |
| Sasaran | UUID `#[Locked]`; dibaca semula dalam skop pelaku dalam transaksi + `lockForUpdate()` |
| Sebab | `Rule::enum`: kosong/nilai lain ditolak (diuji) |
| Peralihan sah sahaja | Nyahaktif: `active` sahaja; aktifkan semula: `inactive` sahaja; `pending`/`rejected` tidak disentuh (diuji) |
| Peranan | Tidak diubah oleh tindakan status (diuji) |
| Superadmin | Superadmin aktif terakhir tidak boleh dinyahaktifkan (konsisten dengan dasar UM10); Urus Setia tidak boleh menyentuh Superadmin |
| Log masuk | `LoginForm`: *"Akaun anda tidak aktif. Sila hubungi Urus Setia untuk maklumat lanjut."* |
| Sesi sedia ada | `EnsureUserIsActive`: HTTP → log keluar + redirect; Livewire/JSON → log keluar + 403 (diuji dengan payload `/livewire/update` sebenar) |
| Kesan lain (automatik) | Pengguna Tidak Aktif dikecualikan daripada senarai Pegawai Bertanggungjawab, penerima e-mel mesyuarat dan `ApplicationPolicy::create` |
| Audit | Audit `updated` OwenIt: `status`, `inactive_reason`, `status_changed_at`, `status_changed_by` (lama/baharu), `user_id` pelaku, masa. Tiada kata laluan |

---

## 7. Ujian

### `Tests\Feature\UserStatusManagementTest`: 18 ujian, 18 lulus

| # (prompt) | Ujian |
|---|---|
| 1–2 | `test_approved_accounts_default_to_active_and_registration_statuses_are_untouched` |
| 3–5, 11–12 | `test_admins_can_deactivate_active_users_with_each_reason` ×6 (Urus Setia & Superadmin × 3 sebab; peranan kekal; audit) |
| 6 | `test_missing_or_invalid_reason_is_rejected` |
| 7 | `test_unauthorised_users_cannot_change_status` (Pengguna Biasa/Pengurusan; Superadmin & diri sendiri di luar skop Urus Setia; UUID dikunci; peranan ditarik → komponen & HTTP 403) |
| 8 | `test_inactive_user_cannot_log_in` |
| 9 | `test_logged_in_user_is_blocked_immediately_after_deactivation` |
| 10 | `test_livewire_requests_from_inactive_user_are_blocked` |
| 13 | `test_admins_can_reactivate_inactive_users` ×2 (+ audit + boleh log masuk semula), `test_urus_setia_cannot_reactivate_out_of_scope_accounts` |
| 14 | `test_last_active_superadmin_cannot_be_deactivated` |
| Paparan | `test_lists_show_status_reason_and_changer`, `test_inactive_users_cannot_be_edited_by_urus_setia` |

### Keseluruhan (15)

| | Sebelum UM11 | Selepas UM11 |
|---|---|---|
| Jumlah | 334 | 352 |
| Lulus | 334 | **352** |
| Gagal | 0 | **0** |

Tiada ujian sedia ada diubah. Ujian dijalankan pada SQLite in-memory.

---

## 8. Pengesahan Operasi

- ✓ Migration dijalankan pada DB pembangunan **dengan kebenaran pengguna**; tambahan sahaja.
- ✓ Tiada seeder; tiada `migrate:fresh/refresh`, `db:wipe`, rollback, `DROP`, `TRUNCATE` atau pemadaman.
- ✓ Tiada perubahan status/peranan pengguna sedia ada (disahkan secara baca sahaja).
- ✓ `.env` tidak diubah; tiada notifikasi e-mel ditambah.
- ✓ **Tiada commit, push, merge, reset, stash atau pertukaran branch.** `git diff --check` bersih; perubahan UM1–UM10 dikekalkan.

---

## 9. Isu dan Keputusan Dasar Yang Masih Diperlukan

| # | Perkara | Keadaan |
|---|---|---|
| 9.1 | **Penyahaktifan automatik "Tidak Aktif Melebihi 6 Bulan"** | Tidak dilaksanakan (luar skop). Sebab ini dipilih secara manual. Pelaksanaan automatik memerlukan sumber tarikh aktiviti (tiada kolum `last_login_at`), dasar & penjadual |
| 9.2 | **Pengaktifan semula** tidak memaksa tukar kata laluan | Kata laluan lama kekal. Pertimbang `must_change_password` atau reset oleh Superadmin jika akaun lama diaktifkan semula |
| 9.3 | Superadmin boleh menyahaktifkan **dirinya sendiri** (jika bukan Superadmin aktif terakhir) | Konsisten dengan dasar UM10; sesi terus disekat |
| 9.4 | Pengguna Tidak Aktif yang masih **Pegawai Bertanggungjawab** | Amaran dipaparkan; pelantikan pegawai baharu kekal manual |
| 9.5 | Sejarah status penuh | Dalam audit OwenIt (Log Audit Superadmin); halaman pengguna hanya memaparkan perubahan terakhir |
| 9.6 | Tiada notifikasi e-mel kepada pengguna yang dinyahaktif/diaktifkan semula | Di luar skop |

---

## 10. Susulan UM11A — Keputusan Pengguna (2026-10-21) dan Penyahaktifan Automatik

### Keputusan

| # (§9) | Keputusan | Kesan |
|---|---|---|
| 9.2 | Pengaktifan semula **tidak** mewajibkan tukar kata laluan | Tiada perubahan (sudah begitu) |
| 9.1 | "Tidak Aktif Melebihi 6 Bulan" **dilaksanakan secara automatik** | Dilaksanakan (UM11A) |

Butiran dasar yang dipilih pengguna:

| Perkara | Pilihan |
|---|---|
| Aktiviti | **Log masuk terakhir** (`users.last_login_at`) |
| Akaun tanpa rekod log masuk (termasuk akaun sedia ada) | Tempoh dikira dari **tarikh terkemudian** antara: log masuk terakhir, akaun dicipta, akaun diluluskan, status terakhir ditukar (cth. diaktifkan semula), dan **tarikh ciri dilancar**. Tiada akaun sedia ada dinyahaktif serta-merta |
| Superadmin | **Dikecualikan** (hanya secara manual) |
| Jadual | **Setiap minggu**, Isnin 1:00 pagi (Asia/Kuala_Lumpur) |

### Pelaksanaan

| Komponen | Fail |
|---|---|
| Migration `last_login_at` (timestamp nullable + index; tambahan sahaja) | `database/migrations/2026_10_21_000001_add_last_login_at_to_users_table.php` |
| Rekod log masuk: event `Login` Laravel (borang log masuk **dan** kuki "Ingat saya"). Akaun Aktif sahaja. Kemas kini query terus tanpa rekod audit pada setiap log masuk. Kegagalan merekod tidak menghalang log masuk | `app/Listeners/RecordLastLogin.php` (auto-discovered) |
| Arahan `php artisan sppict:deactivate-dormant-users [--dry-run]`. Transaksi + `lockForUpdate` + semakan semula setiap akaun. `status=inactive`, sebab `inactive_over_6_months`, `status_changed_by=NULL` (sistem). Audit OwenIt diaktifkan sementara dalam konsol, jadi `updated` dengan `user_id=NULL` | `app/Console/Commands/DeactivateDormantUsers.php` |
| Jadual mingguan `weeklyOn(1, '01:00')->withoutOverlapping()` | `routes/console.php` |
| Konfigurasi `sppict.dormancy.months` (6) & `sppict.dormancy.baseline` (lalai `2026-10-21`). Env `SPPICT_DORMANCY_MONTHS` / `SPPICT_DORMANCY_BASELINE` didokumenkan dalam `.env.example` (`.env` tidak diubah) | `config/sppict.php`, `.env.example` |
| Paparan "Log masuk: dd/mm/yyyy / Tiada rekod" dalam lajur Status (Superadmin & Urus Setia) | `resources/views/livewire/partials/user-status-badge.blade.php` |
| Cast `last_login_at` (bukan `$fillable`) | `app/Models/User.php` |
| 9 ujian | `tests/Feature/DormantUserDeactivationTest.php` |

### Ujian UM11A: 9 lulus

Merangkumi: rekod log masuk (borang & "Ingat saya"); akaun pending/rejected/inactive tidak direkod; tiada audit pada setiap log masuk; penyahaktifan dengan sebab + audit sistem + peranan kekal + log masuk disekat; peraturan tarikh rujukan (6 kes); perlindungan tarikh ciri dilancar; Superadmin/pending/rejected/inactive dikecualikan; `--dry-run` & idempoten; jadual `0 1 * * 1` + `withoutOverlapping`.

**Full suite:** 361 ujian: **361 lulus, 0 gagal.**

### Status migration UM11A

**Telah dijalankan** pada DB pembangunan (2026-10-21) dengan kebenaran pengguna: `ALTER TABLE users ADD last_login_at TIMESTAMP NULL` + index. Semakan `--dry-run` selepas migration: **0 akaun** akan dinyahaktifkan (dilindungi oleh tarikh ciri dilancar).

### Keperluan deployment

1. `php artisan migrate`.
2. Tetapkan `SPPICT_DORMANCY_BASELINE` kepada **tarikh go-live** sebenar di production.
3. Jalankan penjadual Laravel setiap minit: cron `* * * * * php artisan schedule:run`, atau Windows Task Scheduler.
4. Disyorkan jalankan `php artisan sppict:deactivate-dormant-users --dry-run` sebelum larian pertama.

---

*UM11 & UM11A selesai; kedua-dua migration telah dijalankan pada DB pembangunan.*
