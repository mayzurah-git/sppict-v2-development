# Laporan Pelaksanaan PP4.3C – Fungsi Salin Permohonan

**Tarikh:** 17/11/2026 · **Cawangan:** `feature/ui-design` · **Commit semasa:** `ab95aad` (tiada commit dibuat)
**Asas dasar:** keputusan pemilik 17/11/2026: O7, O8 dan O10

## 1. Status skop

| Skop | Status | Ringkasan |
|---|---|---|
| PP43C-01 – Tindakan Salin Permohonan | **PASS** | Kad "Keputusan JTICT: Kaji Semula" dalam halaman butiran dengan tiga keadaan: boleh disalin (butang + pengesahan), telah mempunyai salinan (pautan jika dibenarkan), atau tiada kebenaran |
| PP43C-02 – Maklumat disalin | **PASS** | F1, F2 (termasuk Rujukan Kelulusan = `outcome_code`, kos dan SST) dan F3 (rekod baharu). Dokumen, keputusan, semakan, mesyuarat dan medan legacy tidak disalin. |
| PP43C-03 – Penciptaan permohonan baharu | **PASS** | Draf baharu (ID, UUID dan No. Rujukan `SPPICT/{tahun}/DRAFT-nnnn` baharu); pendaftar = pengguna semasa; agensi asal; `copied_from_application_id`; pengguna dibawa ke borang sedia ada |
| PP43C-04 – Hubungan asal dan salinan | **PASS** | Asal: "telah mempunyai salinan (No. Rujukan)". Salinan: "disalin daripada (No. Rujukan)" dalam halaman butiran dan borang. Pautan dipaparkan hanya jika pengguna dibenarkan melihat. |
| PP43C-05 – Perlindungan rekod asal | **PASS** | Ujian membandingkan permohonan, perincian, dokumen, keputusan, semakan dan pegawai sebelum dan selepas salinan; semuanya sama |
| Pengesahan MySQL / UAT / semakan visual | **NOT TESTED** | Ujian SQLite sahaja |

## 2. Fail ditambah / diubah

| Fail | Perubahan |
|---|---|
| `app/Services/ApplicationCopyService.php` | **Baharu.** Salinan dalam satu transaksi dengan `lockForUpdate` pada permohonan asal; polisi `copy` disemak semula dalam transaksi. |
| `app/Policies/ApplicationPolicy.php` | Ability baharu `copy()` (O7, O8, Kaji Semula) |
| `app/Services/ApplicationSubmissionService.php` | `nextReferenceNumber()` ditukar daripada `private` kepada `public` (logik dan format tidak berubah) supaya salinan menggunakan penjana nombor rujukan yang sama |
| `app/Livewire/ViewApplication.php` | `copyApplication()`; computed `isReviewOutcome`, `applicationCopy`, `copySource` |
| `resources/views/livewire/view-application.blade.php` | Kad Salin Permohonan dan notis "disalin daripada" |
| `app/Livewire/ApplicationForm.php`, `resources/views/livewire/application-form.blade.php` | Notis Draf salinan: semak F1–F3, pilih semula pilihan kosong, muat naik semula dokumen, lantik pegawai |
| `tests/Feature/ApplicationCopyTest.php` | **Baharu.** 13 ujian, 114 assertions |

`npm run build` dijalankan kerana kelas `hover:bg-amber-800` belum ada dalam binaan. Binaan berjaya (`app-BumLclHM.css`); `public/build` diabaikan oleh Git.

## 3. Aliran fungsi

1. Permohonan CLOSED dengan keputusan JTICT terkini **Kaji Semula** memaparkan kad Salin Permohonan.
2. Pengguna layak menekan **Salin Permohonan** dan mengesahkan dialog. Pengguna layak ialah:
   - Pegawai Utama atau Kedua aktif dalam agensi permohonan; atau
   - pendaftar asal yang masih dalam agensi permohonan.
3. `ApplicationCopyService::copy()` menjalankan langkah berikut dalam transaksi:
   - kunci permohonan asal;
   - semak semula polisi `copy` (status akaun, peranan dan agensi terkini; salinan sedia ada termasuk yang dipadam);
   - cipta Draf baharu;
   - salin F1–F3; master data tidak aktif dikosongkan supaya dipilih semula;
   - salin pegawai aktif yang masih layak, dengan tarikh mula ditetapkan semasa dihantar.
4. Pengguna dibawa ke borang Draf salinan. Proses selepas itu mengikut aliran sedia ada (hantar → PP2 → Pra JTICT → JTICT).

**Polisi `copy()`:**
- `create()` (akaun aktif, peranan pencipta, bukan superadmin/pengurusan);
- permohonan tidak dipadam;
- status CLOSED dan keputusan JTICT terkini Kaji Semula;
- tiada salinan (`withTrashed`);
- agensi pengguna = agensi permohonan;
- pengguna ialah pendaftar asal atau pegawai aktif.

Urus Setia yang mendaftar bagi pihak agensi lain ditolak oleh semakan agensi.

## 4. Keputusan ujian (SQLite `:memory:`, disahkan melalui probe)

| # | Senario | Keputusan |
|---|---|---|
| 1–3 | Pegawai Utama, Pegawai Kedua, pendaftar asal (agensi sama) boleh menyalin | PASS |
| 4 | Pendaftar yang bertukar agensi ditolak | PASS |
| 5 | Urus Setia bagi pihak agensi ditolak (pegawai agensi sasaran masih boleh) | PASS |
| 6 | Pengguna tidak aktif ditolak | PASS |
| 7 | Pengguna agensi lain ditolak (dan tidak boleh melihat) | PASS |
| 8 | Rakan seagensi dan pegawai berperanan pengurusan: tiada butang, tindakan Livewire 403, service ditolak | PASS |
| 9–12 | Kaji Semula sahaja; Lulus, Tidak Lulus, IN_JTICT dan Pra JTICT Ditolak ditolak | PASS |
| 13–15 | Salinan pertama berjaya; salinan kedua ditolak; salinan yang dipadam (soft delete) tetap menghalang | PASS |
| 16 | Permintaan berulang dengan halaman lama dan klik berganda: satu salinan sahaja (simulasi berurutan) | PASS |
| — | Kegagalan semasa menyalin → transaksi dibatalkan, tiada rekod separa | PASS |
| 17–26 | ID/UUID/rujukan baharu; `copied_from`; pendaftar = penyalin; F1–F3 sama; F3 rekod baharu; tiada dokumen, keputusan, semakan atau mesyuarat; asal tidak berubah; status Draf dan boleh disunting dalam borang; tiada Projek | PASS |
| — | Master data tidak aktif dikosongkan; pegawai tidak aktif tidak disalin | PASS |
| — | Paparan: butang, "telah mempunyai salinan" + pautan, "disalin daripada", tiada kad bagi keputusan Lulus | PASS |

| Larian | Keputusan |
|---|---|
| `ApplicationCopyTest` | **PASS 13/13** (114 assertions) |
| Suite penuh | **PASS 551/551**, 4,178 assertions, 0 gagal; 1 *risky* sedia ada (`UserRoleManagementTest`, di luar skop) |

Semasa pembangunan, 3 ujian gagal kerana ralat sintaks Blade: `@if` yang melekat pada perkataan tidak dikenali sebagai arahan. Ralat itu dibetulkan dalam paparan, dan ujian tidak diubah.

## 5. Had satu salinan (O8)

- Dikuatkuasakan dalam polisi (`copies()->withTrashed()->exists()`) dan disemak semula dalam transaksi selepas `lockForUpdate` pada permohonan asal.
- Disahkan oleh ujian:
  - salinan kedua ditolak;
  - salinan yang telah dipadam (soft delete) tetap menghalang salinan baharu;
  - permintaan berulang daripada halaman lama ditolak.
- **Had pengesahan:** permintaan yang benar-benar serentak bergantung pada kunci baris MySQL (`SELECT … FOR UPDATE`). SQLite tidak menyokongnya, jadi senario ini **NOT TESTED** pada MySQL.

## 6. Migrasi

**Tiada migrasi baharu.** Jumlah fail migrasi kekal 45; DB pembangunan 45 / batch 24. Tiada indeks unik ditambah, mengikut O8. Semua medan yang mungkin dikosongkan memang nullable; `item_category` (NOT NULL) mengekalkan *snapshot* teks asal.

## 7. Data pembangunan tidak berubah

Semakan baca sahaja: applications 8, application_reviews 12, application_decisions 5, projects 0, `audits` id maks 397, dan hash `application_decisions` `c8943c9a…`. Semuanya sama seperti selepas PP4.3B. Tiada UAT pada `sppictv2_db`.

## 8. Status Git

Commit semasa: `ab95aad`. `git status --short` termasuk perubahan PP4.3B yang belum di-commit:

```
 M app/Livewire/ApplicationForm.php
 M app/Livewire/ViewApplication.php
 M app/Policies/ApplicationPolicy.php
 M app/Services/ApplicationSubmissionService.php
 M docs/Laporan Perancangan PP4.3 – Keputusan Mesyuarat JTICT.md      (catatan keputusan O7/O8/O10)
 M resources/views/livewire/application-form.blade.php
 M resources/views/livewire/view-application.blade.php                 (PP4.3B + PP4.3C)
 M tests/Feature/PreJtictDecisionTest.php                              (PP4.3B)
?? app/Services/ApplicationCopyService.php
?? docs/reports/Laporan Pelaksanaan PP4.3B – ….md
?? docs/reports/Laporan Pelaksanaan PP4.3C – Fungsi Salin Permohonan.md
?? tests/Feature/ApplicationCopyTest.php
?? tests/Feature/DecisionHistoryDisplayTest.php                        (PP4.3B)
```

## 9. Belum diuji

- **MySQL: NOT TESTED.** Ini termasuk kunci baris bagi permintaan serentak sebenar. Tiada pangkalan data ujian berasingan.
- **UAT: NOT TESTED.** Aliran penuh salinan → lengkapkan → hantar → PP2 → Pra JTICT → JTICT dengan pengguna sebenar dalam persekitaran ujian belum dibuat.
- **Semakan visual: NOT TESTED.** Kad salin dan notis belum disemak pada desktop, tablet dan telefon.
- **Data pembangunan:** tiada permohonan dengan keputusan JTICT Kaji Semula, jadi fungsi ini belum boleh dilihat dengan data semasa.
