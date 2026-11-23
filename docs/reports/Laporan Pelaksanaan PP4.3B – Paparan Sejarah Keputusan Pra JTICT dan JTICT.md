# Laporan Pelaksanaan PP4.3B – Paparan Sejarah Keputusan Pra JTICT dan JTICT

**Tarikh:** 17/11/2026 · **Cawangan:** `feature/ui-design` · **Commit semasa:** `ab95aad` (tiada commit dibuat)

## 1. Status pelaksanaan

| Skop | Status | Ringkasan |
|---|---|---|
| PP43B-01 – Sejarah Keputusan Permohonan | **PASS** | Satu bahagian bersatu (Pra JTICT + Mesyuarat JTICT) daripada `application_decisions`, kronologi terdahulu → terkini, menyokong berbilang rekod |
| PP43B-02 – Paparan mengikut jenis pengguna | **PASS** | Pemohon/pegawai melihat "Urus Setia"; Urus Setia melihat nama perekod dan masa rekod; pengguna tanpa kebenaran `view` disekat (kawalan sedia ada) |
| PP43B-03 – Ketekalan status | **PASS** | Disemak; mekanisme `statusDisplayLabel()` (PP4.2A) sudah dipakai dalam senarai, butiran dan papan pemuka. Tiada mekanisme baharu dibina. |
| Pengesahan MySQL / UAT / semakan visual | **NOT TESTED** | Ujian SQLite sahaja |

## 2. Fail ditambah / diubah

| Fail | Perubahan |
|---|---|
| `app/Livewire/ViewApplication.php` | Computed `decisionHistory()`: semua rekod `application_decisions` dengan `decidedBy` dan `meeting` (nama/bil./tahun) di-*eager-load*, disusun `decision_date` ↑, `id` ↑. Cache dikosongkan selepas keputusan direkodkan. |
| `resources/views/livewire/view-application.blade.php` | Bahagian "Sejarah Keputusan Pra JTICT" diganti dengan **"Sejarah Keputusan Permohonan"** |
| `tests/Feature/DecisionHistoryDisplayTest.php` | **Baharu**: 5 ujian |
| `tests/Feature/PreJtictDecisionTest.php` | Kemas kini berjustifikasi: tajuk bahagian ditukar mengikut spesifikasi (3 assertion), dan `decisionHistory` ditambah ke senarai putih kaedah awam (paparan baca sahaja). Semakan nama perekod, kebenaran dan "tiada edit/padam" dikekalkan. |

Tiada perubahan pada service, polisi, aliran keputusan, migrasi, konfigurasi, SMTP atau queue. Tiada `npm run build` diperlukan, kerana semua kelas sudah wujud dalam binaan PP4.3A (kelas `gap-y-0.5` digantikan dengan `gap-y-1` yang sedia ada).

## 3. Ringkasan perubahan paparan

Setiap rekod memaparkan:
- lencana **Peringkat** (Pra JTICT / Mesyuarat JTICT);
- **Keputusan** dengan warna mengikut keputusan;
- **Tarikh keputusan**;
- **Mesyuarat**: nama dan tarikh, jika `meeting_id` ada;
- **Ulasan**, atau "Tiada ulasan.";
- **Direkodkan oleh**.

Rekod terakhir ditanda **Terkini**.

| Maklumat | Pemohon / Pegawai / pengguna lain dengan `view` | Urus Setia |
|---|---|---|
| Peringkat, keputusan, tarikh, mesyuarat, ulasan | Ya | Ya |
| Direkodkan oleh | "Urus Setia" | Nama pegawai |
| Masa rekod (`created_at`) | Tidak dipaparkan | Ya |

**Perubahan tingkah laku:**
- PP4.2 memaparkan "Direkodkan {tarikh/masa}" kepada semua pengguna. Mengikut PP43B-02, masa rekod kini hanya untuk Urus Setia.
- Susunan kini kronologi (terdahulu dahulu), sedangkan PP4.2 memaparkan terkini dahulu.

## 4. Keputusan ujian (SQLite `:memory:`, disahkan melalui probe)

| # | Ujian yang diminta | Ujian | Keputusan |
|---|---|---|---|
| 1–5, 7 | Sejarah Pra JTICT & JTICT; kronologi; tarikh & ulasan; mesyuarat; nama & masa rekod untuk Urus Setia; tiada pendua (3 rekod = 3 paparan, satu "Terkini") | `test_urus_setia_sees_both_stages_chronologically_with_meeting_and_recorder` | PASS |
| 6 | Pemohon & pegawai tidak melihat nama perekod / masa rekod | `test_applicant_and_officer_see_history_without_recorder_identity` | PASS |
| 8 | Pengguna agensi lain & superadmin disekat (Livewire + HTTP 403) | `test_users_without_view_permission_cannot_see_history` | PASS |
| 9 | Rekod keputusan & permohonan tidak berubah selepas dipaparkan; medan legacy tidak digunakan; tiada tindakan edit/padam | `test_viewing_history_does_not_change_decision_records` | PASS |
| 10 | Label "Selesai – Kaji Semula (JTICT)" / "Selesai – Lulus (JTICT)" dalam butiran, senarai pemohon (+ lajur Keputusan), papan pemuka pemohon, senarai & papan pemuka Urus Setia | `test_status_labels_show_actual_jtict_decision_in_lists_detail_and_dashboards` | PASS |

| Larian | Keputusan |
|---|---|
| `DecisionHistoryDisplayTest` | PASS 5/5 (56 assertions) |
| `PreJtictDecisionTest`, `JtictDecisionTest`, `PreJtictDisplayTest` | PASS 15/15, 14/14, 8/8 |
| Suite penuh | **PASS 538/538**, 4,064 assertions; 1 *risky* sedia ada (`UserRoleManagementTest::test_urus_setia_cannot_open_superadmin_self_or_pending_users`, di luar skop) |

Nota: semasa pembangunan, 2 assertion ujian baharu gagal kerana teks "Mesyuarat:" dan nama mesyuarat dipisahkan oleh tag `<dt>`/`<dd>`. Assertion ditukar kepada `assertSeeInOrder` (isu ujian, bukan isu kod).

## 5. Pengesahan rekod keputusan tidak diubah

Pangkalan data pembangunan disemak secara **baca sahaja**:

| Perkara | Selepas PP4.3A | Selepas PP4.3B |
|---|---|---|
| applications / application_reviews / application_decisions | 8 / 12 / 5 | 8 / 12 / 5 |
| Hash kandungan `application_decisions` | `c8943c9a…` | `c8943c9a…` (sama) |
| Migrasi | 45 / batch 24 | 45 / batch 24 |
| `audits` id maks | 395 | 397 |

Rekod audit 396 dan 397 dicipta pada 16/11/2026 (15:59 dan 16:05) oleh pengguna ID 2 melalui aplikasi:
- kemas kini Mesyuarat 2;
- kemas kini profil pengguna.

Kedua-duanya aktiviti pemilik sistem sebelum PP4.3B, bukan daripada kerja ini.

## 6. `git status --short` dan commit semasa

```
 M app/Livewire/ViewApplication.php
 M resources/views/livewire/view-application.blade.php
 M tests/Feature/PreJtictDecisionTest.php
?? tests/Feature/DecisionHistoryDisplayTest.php
?? docs/reports/Laporan Pelaksanaan PP4.3B – Paparan Sejarah Keputusan Pra JTICT dan JTICT.md
```

Commit semasa: `ab95aad` "Modul Keputusan-jtict dan UI ikut peranan". Working tree bersih sebelum PP4.3B.

## 7. Belum diuji / perkara terbuka

- **MySQL: NOT TESTED.** Tiada pangkalan data ujian berasingan.
- **UAT dan semakan visual: NOT TESTED.** Paparan sejarah belum disemak pada desktop, tablet dan telefon.
- Ujian kronologi menggunakan penghantaran semula yang disimulasikan (`forceFill` status). Aliran borang sebenar diuji dalam `PreJtictDecisionTest`.
- **Sedia untuk PP4.3C (Salin Permohonan).** Keputusan O7, O8 dan O10 daripada laporan perancangan masih diperlukan.
