# LAPORAN FASA UM10 — KEMAS KINI TAHAP CAPAIAN DAN PROFIL PENGGUNA SEDIA ADA

**Projek:** SPPICT v2
**Tarikh:** 2026-10-19
**Branch:** `feature/user-management`
**Rujukan:** keputusan pengguna 2026-10-15 dan 2026-10-19 (susulan laporan UM9 §8–§9)

---

## 1. Ringkasan

**Status: BERJAYA**

| Pelaku | Daftar akaun baharu | Tukar Tahap Capaian pengguna sedia ada | Kemas kini profil pengguna sedia ada |
|---|---|---|---|
| **Superadmin** | Superadmin, Urus Setia *(tidak berubah)* | **Mana-mana 4 peranan** *(baharu; termasuk turunkan Urus Setia → Pengguna Biasa/Pengurusan)* | Semua pengguna *(tidak berubah)* |
| **Urus Setia** | Pengguna Biasa, Urus Setia, Pengurusan *(tidak berubah)* | **Pengurusan ↔ Pengguna Biasa sahaja** *(baharu)* | **Pengguna Biasa, Pengurusan, Urus Setia** *(baharu)* |

Peranan Urus Setia hanya boleh ditukar oleh Superadmin. Urus Setia boleh terus meluluskan pemohon Self Register sebagai Urus Setia tanpa kelulusan pihak kedua (UM9, kekal).

**Ujian:** 334 ujian: **334 lulus, 0 gagal** (19 ujian UM10 baharu). Tiada migrasi.

---

## 2. Perubahan

### Superadmin (`Admin\UserManagement`)

- **Daftar baharu:** whitelist kekal `pentadbir_urus_setia`, `superadmin`.
- **Kemas kini:** pilihan Tahap Capaian = semua 4 peranan (`ROLE_LABELS`). Label "(peranan semasa)" dibuang.
- **Guard baharu:** Superadmin **aktif terakhir** tidak boleh ditukar peranannya, supaya sistem tidak kehilangan semua Superadmin. Superadmin `pending` tidak dikira. Jika ada Superadmin aktif lain, penurunan dibenarkan.
- **Audit:** setiap perubahan peranan direkodkan (`role_updated`, lihat §4).

### Urus Setia: halaman baharu "Senarai Pengguna" (`/urus-setia/pengguna`, route `users.index`)

- **Menu:** Pengurusan Pengguna → **Senarai Pengguna**.
- **Skop senarai/kemas kini:**
  - pengguna **aktif** berperanan Pengguna Biasa, Pengurusan atau Urus Setia;
  - **bukan** Superadmin (termasuk pemegang pelbagai peranan yang salah satunya Superadmin);
  - **bukan** akaun sendiri (gunakan Profil);
  - pengguna `pending`/`rejected` kekal diurus di "Permohonan Pendaftaran".
- **Carian & penapis:** nama/e-mel/No. KP (berkumpulan, tidak memintas skop) + penapis Tahap Capaian; pagination; No. KP bertopeng.
- **Medan profil:** sama seperti borang lain (trait `HandlesUserProfileFields`): No. KP, nama, agensi, Jawatan → Skim (readonly) → Gred (pivot), telefon, e-mel. Unik diabaikan bagi diri pengguna itu.
- **Tahap Capaian:**
  - Pengguna Biasa / Pengurusan: pilihan **Pengguna Biasa ↔ Pengurusan**.
  - Urus Setia: dikunci, dengan nota "Peranan Urus Setia hanya boleh ditukar oleh Superadmin".
- **Tidak diurus di sini:** kata laluan, status, medan kelulusan.

### Kod dikongsi

- `App\Support\RoleChangeAudit` (baharu): rekod audit OwenIt (custom event) bagi perubahan peranan. Digunakan oleh kelulusan UM9 (`approved`), Superadmin dan Urus Setia (`role_updated`).
- `User::activeOfficerReferences()` (baharu): amaran "masih Pegawai Bertanggungjawab" apabila agensi ditukar, dikongsi oleh Superadmin & Urus Setia.

---

## 3. Fail Ditambah dan Diubah

| Fail | Tindakan | Sebab |
|---|---|---|
| `app/Livewire/Users/ManageUsers.php` | **Ditambah** | Senarai & kemas kini pengguna oleh Urus Setia |
| `resources/views/livewire/users/manage-users.blade.php` | **Ditambah** | UI Senarai Pengguna |
| `app/Support/RoleChangeAudit.php` | **Ditambah** | Audit perubahan peranan berpusat |
| `tests/Feature/UserRoleManagementTest.php` | **Ditambah** | 19 ujian UM10 |
| `docs/LAPORAN_FASA_UM10_KEMAS_KINI_TAHAP_CAPAIAN.md` | **Ditambah** | Laporan ini |
| `app/Livewire/Admin/UserManagement.php` | Diubah | 4 peranan semasa kemas kini; guard Superadmin terakhir; audit; guna `activeOfficerReferences()` |
| `resources/views/livewire/admin/user-management.blade.php` | Diubah | Buang label "(peranan semasa)" |
| `app/Livewire/Users/RegistrationRequests.php` | Diubah | Guna `RoleChangeAudit` (tingkah laku UM9 sama) |
| `app/Models/User.php` | Diubah | `activeOfficerReferences()` |
| `routes/web.php` | Diubah | Route `users.index` (kumpulan `role:pentadbir_urus_setia`) |
| `resources/views/livewire/layout/navigation.blade.php` | Diubah | Pautan "Senarai Pengguna" |
| `tests/Feature/SuperadminUserManagementTest.php` | Diselaraskan | Dua ujian polisi lama UM5 (Superadmin tidak boleh beri `pengurusan` semasa kemas kini) dikemas kini mengikut keputusan UM10 |
| `tests/Feature/UserModuleIntegrationTest.php` | Diselaraskan | Route `/urus-setia/pengguna` ditambah ke matriks akses UM8 |

---

## 4. Keselamatan dan Integriti

| Kawalan | Pelaksanaan |
|---|---|
| Akses halaman | `auth` + `verified` + `active` + `password.changed` + `role:pentadbir_urus_setia` (persistent untuk Livewire) |
| Akses tindakan | `ensureUrusSetia()` dalam `mount`, `openModal`, `save` (peranan + aktif + tiada kata laluan sementara) |
| Sasaran | UUID `#[Locked]`; dibaca semula dalam skop (`editableUsers()`) pada `openModal` & `save`, dan sekali lagi dengan `lockForUpdate()` dalam transaksi (peranan sasaran mungkin berubah serentak) |
| Peranan dibenarkan | Dikira di server daripada peranan semasa sasaran (DB): `Rule::in` + `exists:roles,name` + semakan semula dalam transaksi |
| Eskalasi | Urus Setia tidak boleh memberi Urus Setia/Superadmin; tidak boleh mengubah Urus Setia/Superadmin (diuji) |
| Atomik | Profil + `syncRoles` + audit dalam satu transaksi; kegagalan penetapan peranan membatalkan kemas kini profil (diuji) |
| Audit | Profil: audit `updated` OwenIt sedia ada. Peranan: `role_updated` dengan `old_values.roles` / `new_values.roles`, `user_id` = pelaku. Tiada kata laluan |
| Superadmin terakhir | Tidak boleh diturunkan (diuji) |

---

## 5. Ujian

### `Tests\Feature\UserRoleManagementTest`: 19 ujian, 19 lulus

| Kategori | Ujian |
|---|---|
| Superadmin | daftar baharu kekal 2 peranan; tukar mana-mana peranan ×5 (Urus Setia → Pengguna Biasa/Pengurusan, Pengguna Biasa → Urus Setia, Pengurusan → Superadmin, Superadmin → Urus Setia) + audit; Urus Setia diturunkan hilang akses Urus Setia; guard Superadmin aktif terakhir |
| Urus Setia: akses | hanya Urus Setia (Superadmin/Pengurusan/Pengguna Biasa/kata laluan sementara → 403) |
| Urus Setia: skop | senarai hanya pengguna aktif dalam skop (tiada Superadmin, pending, pemegang peranan campuran Superadmin, diri sendiri); penapis & carian; tidak boleh buka Superadmin/diri sendiri/pending; UUID dikunci |
| Urus Setia: peranan | Pengurusan ↔ Pengguna Biasa ×2 + audit + authorization ikut peranan baharu; eskalasi ditolak; peranan Urus Setia dikunci tetapi profil boleh dikemas kini |
| Integriti | validasi profil dikongsi (Gred di luar pivot, e-mel pendua tanpa mengira huruf); medan terlindung tidak berubah; semakan semula kebenaran & skop sasaran semasa sesi; rollback jika penetapan peranan gagal |

### Keseluruhan

| | Sebelum UM10 | Selepas UM10 |
|---|---|---|
| Jumlah | 315 | 334 |
| Lulus | 315 | **334** |
| Gagal | 0 | **0** |

---

## 6. Pengesahan Operasi

- ✓ Tiada migrasi baharu; tiada migrasi atau seeder dijalankan; DB pembangunan tidak disentuh.
- ✓ Tiada perubahan peranan/data pengguna sedia ada secara pukal.
- ✓ `.env` tidak diubah.
- ✓ **Tiada commit, push, merge, reset, stash atau pertukaran branch.** `git diff --check` bersih.

---

## 7. Catatan dan Perkara Untuk Keputusan

| # | Perkara | Keadaan |
|---|---|---|
| 7.1 | Urus Setia tidak boleh mengemas kini akaun **sendiri** melalui Senarai Pengguna | Ditetapkan untuk mengelak kekeliruan; profil sendiri melalui halaman Profil (nama & e-mel sahaja). Sahkan jika Urus Setia perlu mengemas kini agensi/jawatan sendiri |
| 7.2 | Urus Setia tidak boleh **reset kata laluan** pengguna | Tidak diminta; hanya Superadmin (UM5) |
| 7.3 | Pengguna **pending/rejected** tidak disenaraikan dalam Senarai Pengguna | Diurus di Permohonan Pendaftaran |
| 7.4 | Superadmin boleh menukar peranan **dirinya sendiri** (jika bukan Superadmin aktif terakhir) | Selaras dengan dasar semasa; sesi akan kehilangan akses Superadmin serta-merta |
| 7.5 | Item tambahan pengguna (mesej terputus) | Menunggu prompt lengkap |

---

*UM10 selesai. Menunggu semakan dan prompt seterusnya.*
