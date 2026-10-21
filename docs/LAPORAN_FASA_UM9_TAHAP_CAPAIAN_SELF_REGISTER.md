# LAPORAN FASA UM9 — PENETAPAN TAHAP CAPAIAN SEMASA PENGESAHAN SELF REGISTER

**Projek:** SPPICT v2
**Tarikh:** 2026-10-13
**Branch:** `feature/user-management`
**Rujukan:** `Prompt-UM9-Pengemaskinian-Tahap-Capaian-Self-Register.md`, laporan UM4, UM7A, UM8

---

## 1. Ringkasan Perubahan

**Status: BERJAYA**

Urus Setia kini memilih **Tahap Capaian** (Pengguna Biasa / Urus Setia / Pengurusan) semasa meluluskan permohonan Self Register.

- Peranan disahkan di server dan ditetapkan melalui Spatie dalam **transaksi yang sama** dengan perubahan status.
- Keputusan direkodkan dalam audit OwenIt sedia ada (custom event `approved`).
- `superadmin` tidak boleh dipilih atau diberikan.
- Self Register kekal `pengguna_biasa` + `pending`.

**Ujian:** 315 ujian: **315 lulus, 0 gagal** (12 ujian UM9 baharu). Tiada migrasi, tiada perubahan data pembangunan.

---

## 2. Aliran Proses

### Sebelum UM9

```
Self Register → pengguna_biasa + pending
        ↓
Urus Setia: Semak → Luluskan → status active (peranan kekal pengguna_biasa)
                  → Tolak    → status rejected
```

Urus Setia tidak boleh menetapkan peranan lain semasa kelulusan. Untuk menjadikan pemohon Urus Setia/Pengurusan, perlu mendaftar akaun berasingan.

### Selepas UM9

```
Self Register → pengguna_biasa + pending                       (tidak berubah)
        ↓
Urus Setia: Semak → pilih Tahap Capaian [Pengguna Biasa ▾]     (lalai: Pengguna Biasa)
                  │   Pengguna Biasa / Urus Setia / Pengurusan
                  │
                  ├─ Luluskan → dialog: "… dengan Tahap Capaian: <pilihan>?"
                  │     ↓ server: role whitelist + exists, status pending, bukan diri sendiri
                  │     ↓ DB::transaction + lockForUpdate:
                  │         status=active, approved_by, approved_at
                  │         syncRoles([pilihan])          ← Spatie
                  │         audit custom 'approved' (status & roles lama/baharu)
                  │     ↓ "Permohonan X telah diluluskan dengan Tahap Capaian Y. Akaun kini aktif."
                  │
                  └─ Tolak → status rejected; peranan TIDAK diubah; tiada akses (tidak berubah)
```

---

## 3. Fail Ditambah, Diubah, Dipadam

| Fail | Tindakan | Sebab |
|---|---|---|
| `app/Livewire/Users/RegistrationRequests.php` | Diubah | Property `approvalRole` (lalai `pengguna_biasa`); `APPROVAL_ROLES` = `RegisterUser::ASSIGNABLE_ROLES` (satu sumber whitelist); validasi server dalam `approve()`; `syncRoles()` dalam transaksi; `auditApproval()` (OwenIt `AuditCustom`); reset pilihan apabila butiran dibuka/ditutup; mesej BM |
| `resources/views/livewire/users/registration-requests.blade.php` | Diubah | Medan pilihan "Tahap Capaian" dalam modal butiran (hanya bagi `pending` & bukan diri sendiri); dialog pengesahan memaparkan pilihan; ralat validasi |
| `tests/Feature/SelfRegisterApprovalRoleTest.php` | **Ditambah** | 12 ujian UM9 |
| `docs/LAPORAN_FASA_UM9_TAHAP_CAPAIAN_SELF_REGISTER.md` | **Ditambah** | Laporan ini |

**Tidak diubah:** Self Register (`register.blade.php`), `RegisterUser`, `Admin\UserManagement`, model, middleware, route, migrasi, seeder, ujian sedia ada.

---

## 4. Cara Tahap Capaian Ditetapkan dan Disimpan

| Aspek | Pelaksanaan |
|---|---|
| Pilihan | `RegistrationRequests::APPROVAL_ROLES` = `RegisterUser::ASSIGNABLE_ROLES` → `pengguna_biasa` ("Pengguna Biasa (Pemohon Agensi)"), `pentadbir_urus_setia` ("Urus Setia"), `pengurusan` ("Pengurusan") |
| Lalai | `pengguna_biasa`; di-reset setiap kali butiran dibuka/ditutup dan selepas tindakan |
| Validasi server | `required`, `Rule::in(array_keys(APPROVAL_ROLES))`, `Rule::exists('roles','name')` |
| Sumber peranan | **Spatie `model_has_roles`** melalui `syncRoles([$role])` (menggantikan `pengguna_biasa` lalai). `users.role_id` legacy tidak disentuh (kekal NULL) |
| Atomik | `syncRoles` dalam `DB::transaction` yang sama dengan `status`/`approved_by`/`approved_at`, selepas `lockForUpdate()` dan semakan `pending` serta kelulusan kendiri. Kegagalan penetapan peranan membatalkan kelulusan (diuji) |
| Penolakan | Peranan tidak disentuh; akaun `rejected` tiada akses (EnsureUserIsActive / LoginForm) |
| Audit | Audit `updated` OwenIt sedia ada (status, approved_by, approved_at) **+** audit custom `approved`: `old_values = {status: pending, roles: [...]}`, `new_values = {status: active, roles: [pilihan], approved_by}`, `user_id` = Urus Setia. Tiada kata laluan/token |

Tiada migrasi diperlukan: struktur Spatie & medan kelulusan UM1 mencukupi.

---

## 5. Pengesahan Keselamatan dan Authorization

| Kawalan | Status |
|---|---|
| Hanya Urus Setia aktif tanpa kata laluan sementara | `ensureUrusSetia()` dalam `mount`, `showDetail`, `approve`, `decide` + middleware route `role:pentadbir_urus_setia` (persistent Livewire, UM7) |
| Pengguna biasa / Pengurusan / Superadmin / pending / kata laluan sementara | 403 (diuji) |
| Peranan ditarik semasa sesi | Tindakan `approve` ditolak melalui ujian komponen **dan** payload `/livewire/update` sebenar (diuji) |
| `superadmin` / nilai tidak sah dihantar manual | Ditolak oleh validasi server (komponen & payload HTTP sebenar; diuji) |
| Bukan `pending` | Tidak boleh diluluskan semula; peranan tidak berubah (diuji) |
| Kelulusan kendiri | Dikekalkan (diuji) |
| Persaingan | `lockForUpdate()` dikekalkan: dua Urus Setia meluluskan serentak, yang kedua mendapat "telah diproses" dan peranan pertama kekal (diuji) |
| UI | Pilihan hanya dipaparkan kepada Urus Setia (halaman dilindungi) bagi permohonan `pending` bukan milik sendiri; `superadmin` tiada dalam senarai |

---

## 6. Ujian dan Keputusan

### `Tests\Feature\SelfRegisterApprovalRoleTest`: 12 ujian, 12 lulus

| # (prompt) | Ujian |
|---|---|
| 1–2 | `test_self_register_defaults_to_pending_pengguna_biasa_without_access` |
| UI | `test_detail_offers_three_roles_defaulting_to_pengguna_biasa_and_confirm_shows_choice` |
| 3–6 | `test_urus_setia_approves_with_selected_role_and_authorization_follows_it` ×3 (Pengguna Biasa → boleh `/application/new`; Urus Setia → boleh `/urus-setia/pengguna/permohonan`; Pengurusan → ditolak `/application/new`); termasuk semakan audit |
| 7–8 | `test_superadmin_or_invalid_roles_are_rejected_on_server`, `test_manual_livewire_payload_cannot_grant_superadmin` |
| 9 | `test_users_without_urus_setia_permission_cannot_set_roles` (5 jenis pelaku + peranan ditarik semasa sesi, komponen & HTTP) |
| 10 | `test_already_processed_applications_cannot_be_approved_again` |
| 11 | `test_rejection_ignores_selected_role_and_grants_no_access` |
| 12 | `test_role_assignment_failure_rolls_back_approval` |
| 13 | `test_concurrency_and_self_approval_guards_remain` |

### Regresi

| Suite | Keputusan |
|---|---|
| `UrusSetiaUserManagementTest` (UM4) | 27/27 lulus (kelulusan lalai kekal `pengguna_biasa`) |
| **Full suite** | **315 ujian: 315 lulus, 0 gagal** (2,100 assertion) |

Tiada kegagalan baseline.

---

## 7. Pengesahan Tiada Migrasi atau Perubahan Data Pembangunan

- ✓ Tiada migrasi baharu; tiada migrasi dijalankan. Migrasi terakhir kekal `2026_10_08_000002_add_user_management_columns_to_users_table.php`.
- ✓ Tiada seeder dijalankan; tiada akses tulis ke `sppictv2_db` (ujian guna SQLite in-memory).
- ✓ Tiada `migrate:fresh/refresh`, `db:wipe`, rollback, `DROP`, `TRUNCATE` atau pemadaman data.
- ✓ Peranan pengguna sedia ada (termasuk Superadmin) tidak diubah.
- ✓ `.env` tidak diubah. Tiada fail rahsia disentuh.
- ✓ **Tiada commit, push, merge, reset, stash atau pertukaran branch.** `git diff --check` bersih.

---

## 8. Isu, Risiko dan Keputusan Pengguna

| # | Perkara | Catatan |
|---|---|---|
| 8.1 | **Peranan selepas kelulusan** | UM9 hanya menetapkan peranan semasa kelulusan. Menukar peranan pengguna aktif selepas itu masih melalui Superadmin (terhad kepada Urus Setia/Superadmin, UM5). Urus Setia tiada fungsi menukar peranan pengguna aktif. Perlukah? |
| 8.2 | **Kelulusan sebagai Urus Setia** | Memberikan kuasa pentadbiran penuh Urus Setia kepada pemohon Self Register. Pertimbangkan sama ada kelulusan sebagai Urus Setia perlu kelulusan kedua / Superadmin |
| 8.3 | Pemohon Self Register yang diluluskan tidak diwajibkan tukar kata laluan | Selaras dengan dasar sedia ada (mereka menetapkan kata laluan sendiri) |
| 8.4 | Tiada notifikasi kepada pemohon tentang keputusan & tahap capaian | Di luar skop (UM8 §12.4) |
| 8.5 | Audit perubahan peranan Spatie di luar aliran kelulusan (cth. Superadmin) | Belum diaudit secara khusus (UM8 §12.8); UM9 hanya mengaudit kelulusan |

---

## 9. Keputusan Pengguna (2026-10-15)

| # | Perkara (§8) | Keputusan | Kesan kepada kod |
|---|---|---|---|
| 8.2 | Kelulusan sebagai Urus Setia | **Tidak perlu kelulusan pihak kedua.** Urus Setia boleh terus meluluskan pemohon dengan Tahap Capaian Urus Setia | Tiada; pelaksanaan UM9 kekal |
| 8.1 | Menukar peranan selepas kelulusan | **Hanya Superadmin** boleh menukar/mengemaskini peranan pengguna, termasuk Urus Setia | Tiada; sudah dikuatkuasakan |

**Pengesahan keputusan 8.1 terhadap kod semasa.** Semua lokasi yang menetapkan peranan (`assignRole`/`syncRoles`):

| Lokasi | Pelaku | Skop |
|---|---|---|
| `Users\RegistrationRequests::decide()` | Urus Setia | Sekali sahaja, semasa meluluskan permohonan `pending` (bukan `pending` ditolak, diuji) |
| `Users\RegisterUser::save()` | Urus Setia | Akaun baharu sahaja |
| `Admin\UserManagement::updateUser()` | **Superadmin sahaja** (`role:superadmin` + `ensureSuperadmin()`) | Menukar peranan pengguna sedia ada |
| `Console\Commands\CreateSuperadmin` | Operator CLI | Akaun baharu sahaja |

Urus Setia tiada akses ke `/admin/users` (403, diuji dalam matriks akses UM8) dan tiada tindakan lain untuk mengubah peranan pengguna aktif.

**Catatan:** mengikut polisi UM5, Superadmin boleh menetapkan `pentadbir_urus_setia` atau `superadmin` sahaja. Peranan semasa lain dikekalkan tetapi tidak boleh diberikan. Superadmin **belum** boleh menurunkan Urus Setia kepada Pengguna Biasa atau Pengurusan. Jika ini diperlukan, whitelist `Admin\UserManagement::ASSIGNABLE_ROLES` perlu diluaskan (keputusan berasingan).

---

*UM9 selesai. Keputusan pengguna direkodkan.*
