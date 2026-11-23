# Laporan Pelaksanaan PP4.2A – Penambahbaikan dan Pengesahan Fungsi Keputusan Pra JTICT

**Tarikh:** 05/11/2026
**Rujukan:** Laporan Pelaksanaan PP4.2 (04/11/2026), Prompt PP4.2A
**Cawangan:** `feature/ui-design` (tiada commit dibuat)

## 1. Ringkasan

| Penambahbaikan | Status |
|---|---|
| A – Aset CSS (`npm run build`) | Selesai; binaan berjaya tanpa ralat |
| B – Tanda penghantaran semula selepas Pindaan Pra JTICT | Selesai; diuji |
| C – Penyelarasan label status | Selesai; diuji |
| Polisi permohonan tanpa mesyuarat | Tidak diubah; dilaporkan di Seksyen 6 |
| Ujian | SQLite: 504/504 lulus (1 *risky* sedia ada). MySQL: **tidak diuji** |

## 2. Semakan keselamatan sebelum perubahan

- `git status` dijalankan sebelum sebarang perubahan. Working tree mengandungi banyak perubahan pemilik sistem yang belum di-commit. Antaranya ialah `resources/css/app.css`, `config/purifier.php`, `app/Support/RichText.php`, komponen borang (`resources/views/components/form/*`), paparan fasa borang, `app/Livewire/Forms/ApplicationForm.php` dan `ApplicationSubmissionService.php`. Fail-fail ini tidak disentuh.
- Dua fail yang diubah dalam PP4.2A turut mengandungi perubahan pemilik yang belum di-commit: `app/Livewire/ApplicationList.php` dan `resources/views/livewire/application-list.blade.php`. Diff disemak dahulu; perubahan PP4.2A hanyalah tambahan pada baris yang tidak bertindih, dan perubahan pemilik dikekalkan.
- Tiada operasi Git, migrasi, seeder atau perubahan `.env`, SMTP atau queue dilakukan. Struktur jadual PP4.1B tidak diubah.

## 3. Fail yang diubah

| Fail | Perubahan |
|---|---|
| `app/Livewire/ApplicationList.php` | `was_returned` disokong selepas `PRE_JTICT_AMENDMENT`; subquery `last_resubmitted_from`; eager-load keputusan bagi senarai Urus Setia |
| `resources/views/livewire/application-list.blade.php` | Lencana status guna `statusDisplayLabel()`; teks tanda ikut jenis pindaan |
| `resources/views/components/application-status-badge.blade.php` | Label terperinci (prop `label`) boleh berbalut supaya tidak melimpah |
| `resources/views/dashboard/pengguna.blade.php` | Kad "Perlu Tindakan" guna `statusDisplayLabel()` |
| `app/Models/Application.php` | `statusDisplayLabel()`: Lengkap dibezakan mengikut kelayakan keputusan |
| `tests/Feature/PreJtictDisplayTest.php` | **Baharu** – 8 ujian PP4.2A |
| `public/build/*` | Aset dibina semula (diabaikan oleh Git) |

## 4. Perubahan yang dibuat

### 4.1 Penambahbaikan A – Aset CSS

- Laluan `content` Tailwind (`./resources/views/**/*.blade.php`) sudah meliputi semua paparan PP4.2. Tiada perubahan konfigurasi diperlukan.
- `npm run build` berjaya, menghasilkan `app-BS7dg-7U.css` (81.30 kB). Satu-satunya mesej pada stderr ialah notis masa plugin Vite, bukan ralat.
- Pengesahan kelas dalam CSS binaan:

| Kelas (senarai prompt) | Dalam binaan | Catatan |
|---|---|---|
| `border-orange-500` | Ya | Notis Pindaan Pra JTICT, halaman butiran |
| `min-w-[900px]` | Ya | Jadual senarai Pra JTICT |
| `bg-cyan-50`, `border-cyan-200`, `text-cyan-900` | Tidak | **Tidak lagi digunakan dalam kod.** Kad Pra JTICT di papan pemuka Urus Setia telah diubah oleh pemilik sistem kepada `bg-cyan-100`, `text-cyan-800` dan `hover:border-cyan-700`. Kelas semasa ini disahkan ada dalam binaan. |

- Nota: binaan turut merangkumi perubahan `resources/css/app.css` pemilik yang belum di-commit, kerana Vite membina keadaan working tree semasa.

### 4.2 Penambahbaikan B – Tanda penghantaran semula (PP2)

**Masalah:** `was_returned` hanya menyemak rekod `to_status = NEEDS_AMENDMENT`. Permohonan yang dihantar semula selepas Pindaan Pra JTICT tidak pernah melalui Tidak Lengkap, jadi ia tidak ditanda.

**Penyelesaian:** hanya rekod `application_reviews` sedia ada digunakan. Tiada rekod baharu ditambah, dan `ApplicationReviewService` tidak diubah.

- `was_returned` = pernah `to_status = NEEDS_AMENDMENT` (pengesanan PP2 sedia ada dikekalkan) **atau** ada rekod "Dihantar Semula" `PRE_JTICT_AMENDMENT → SUBMITTED`. Syarat OR dibungkus dalam kumpulan supaya tidak terlepas daripada syarat `application_id`.
- `last_resubmitted_from` ialah `from_status` bagi rekod penghantaran semula terkini. Nilai ini menentukan teks yang dipaparkan:
  - "Dihantar semula selepas pindaan" (biru) jika rekod terkini datang daripada Tidak Lengkap PP2;
  - "Dihantar semula selepas Pindaan Pra JTICT" (oren) jika rekod terkini datang daripada Pindaan Pra JTICT.
- Tanda kekal dipaparkan hanya apabila status semasa ialah **Dihantar**. Permohonan yang masih Tidak Lengkap atau Pindaan Pra JTICT (belum dihantar semula) tidak ditanda.
- Data pembangunan (semakan baca sahaja): permohonan ID 7 dihantar semula selepas Pindaan Pra JTICT tanpa rekod Tidak Lengkap. Logik lama tidak menandakannya; logik baharu menandakannya.

### 4.3 Penambahbaikan C – Label status

Audit paparan:

| Paparan | Sebelum | Selepas PP4.2A |
|---|---|---|
| Senarai permohonan pemohon (`ApplicationList`) | Label asas | `statusDisplayLabel()` (keputusan sudah di-eager-load) |
| Dashboard pemohon – senarai | Label asas | Sama dengan senarai di atas (komponen yang sama) |
| Dashboard pemohon – kad "Perlu Tindakan" | Label asas | `statusDisplayLabel()` |
| Senarai Urus Setia (`ApplicationList`) | Label asas | `statusDisplayLabel()` (eager-load ditambah) |
| Dashboard Urus Setia | Kad bilangan + `ApplicationList` | Kad bilangan kekal (label asas, sepadan dengan penapis); senarai ikut di atas |
| Halaman butiran | `statusDisplayLabel()` (PP4.2) | Tidak berubah |
| Senarai Pra JTICT | `statusDisplayLabel()` (PP4.2) | Tidak berubah |

Peraturan `statusDisplayLabel()` selepas PP4.2A:

| Status | Label |
|---|---|
| `COMPLETE`, ada Mesyuarat JTICT dan tiada keputusan Pra JTICT muktamad | Lengkap – Menunggu Keputusan Pra JTICT |
| `COMPLETE`, tanpa mesyuarat atau dengan keputusan muktamad (data luar biasa) | Lengkap (label asas) – **baharu dalam PP4.2A** |
| `IN_JTICT` dengan keputusan Pra JTICT terkini Diterima | Diterima – Menunggu Mesyuarat JTICT |
| `IN_JTICT` tanpa rekod keputusan | Label asas (tiada keputusan direka) |
| `PRE_JTICT_AMENDMENT` | Ditolak dengan Pindaan – Menunggu Tindakan Pemohon |
| `CLOSED` dengan keputusan JTICT | Selesai – {keputusan} (JTICT) |
| `CLOSED` dengan keputusan Pra JTICT Ditolak | Selesai – Ditolak (Pra JTICT) |
| `CLOSED` tanpa rekod keputusan | Label asas (tidak dianggap Ditolak Pra JTICT) |
| Status lain | Label asas (tiada pertanyaan keputusan dibuat) |

- Label asas enum (`ApplicationStatus::label()`) **tidak diubah**. Penapis status, kad bilangan papan pemuka dan modul lain kekal menggunakannya.
- Lencana yang menerima label terperinci kini boleh berbalut (`whitespace-normal`). Ini menghalang jadual Urus Setia, yang direka muat tanpa tatal, daripada melimpah pada skrin kecil. Lencana tanpa label terperinci tidak berubah. Struktur papan pemuka tidak diubah.

## 5. Ujian

### 5.1 Ujian baharu – `tests/Feature/PreJtictDisplayTest.php`

Penghantaran semula dalam ujian ini menggunakan aliran sebenar: borang permohonan oleh Pegawai Utama aktif, `ApplicationReviewService` dan `ApplicationDecisionService`.

| Ujian | Keperluan prompt (Seksyen 7) |
|---|---|
| `test_resubmission_after_pp2_incomplete_is_flagged` | Tanda selepas `NEEDS_AMENDMENT` |
| `test_resubmission_after_pre_jtict_amendment_is_flagged` | Tanda selepas `PRE_JTICT_AMENDMENT` (dan bukti tiada rekod Tidak Lengkap) |
| `test_latest_resubmission_determines_flag_text` | Dua kitaran: teks ikut penghantaran semula terkini |
| `test_applications_not_yet_resubmitted_are_not_flagged` | Penghantaran pertama / Tidak Lengkap / Pindaan Pra JTICT belum dihantar semula tidak ditanda; paparan tidak menambah rekod sejarah |
| `test_complete_label_distinguishes_awaiting_decision_from_plain_complete` | Lengkap menunggu vs Lengkap biasa (tanpa mesyuarat) vs Lengkap semula selepas pindaan |
| `test_in_jtict_and_pre_jtict_amendment_labels_follow_decision_records` | Label ikut rekod keputusan dalam senarai, dashboard pemohon dan senarai Urus Setia |
| `test_closed_without_decision_is_not_shown_as_pre_jtict_rejection` | CLOSED tanpa keputusan bukan Ditolak Pra JTICT; tiada keputusan JTICT direka |
| `test_base_labels_for_filters_are_unchanged` | Label asas penapis tidak berubah |

### 5.2 Keputusan – SQLite (in-memory)

| Fail ujian | Keputusan |
|---|---|
| PreJtictDisplayTest (baharu) | 8/8 lulus (49 assertions) |
| PreJtictDecisionTest (PP4.2) | 15/15 lulus |
| ApplicationDecisionTest (PP4.1B) | 24/24 lulus |
| ApplicationReviewTest (PP2) | 18/18 lulus |
| OnBehalfApplicationTest (PP3) | 16/16 lulus |
| ApplicationOfficerTest | 18/18 lulus |
| PenggunaDashboardTest | 6/6 lulus |
| UrusSetiaDashboardTest | 7/7 lulus |
| **Suite penuh** | **504/504 lulus**, 3873 assertions, 1 *risky* (sedia ada sebelum PP4.2) |

Nota kestabilan:
- Pada satu larian suite penuh, dua ujian gagal di luar skop PP4.2A:
  - `ApplicationDocumentTest::test_proposal_paper_accepts_pdf_and_docx`, berkaitan muat naik fail;
  - `ApplicationFormDesignTest::test_legacy_plain_text_is_converted_for_editor_and_display`, ujian baharu pemilik untuk RichText.
- Kedua-duanya lulus apabila dijalankan semula. `ApplicationDocumentTest` lulus 12/12 dalam 4 larian berturut-turut, dan suite penuh seterusnya lulus 504/504.
- Fail `RichText.php` pemilik sedang disunting semasa larian itu. Kegagalan muat naik fail pula berlaku secara rawak (*intermittent*) dan perlu dipantau.

### 5.3 Keputusan – MySQL

**Tidak diuji.** Semua ujian automatik menggunakan SQLite in-memory. Subquery `last_resubmitted_from` dan `withExists` adalah SQL standard. Walau bagaimanapun, tingkah lakunya pada MySQL belum disahkan dengan ujian.

## 6. Polisi permohonan tanpa mesyuarat

Polisi tidak diubah. Kaedah pengenalpastian dan kawalan:

- **Dikenal pasti:** `Application::scopeNeedsManualPreJtictReview()` memilih permohonan COMPLETE yang memenuhi salah satu syarat berikut:
  - tiada `jtict_meeting_id`;
  - tiada rekod semakan PP2 "Lengkap";
  - sudah ada keputusan Pra JTICT muktamad.
- **Dipaparkan:** bahagian "Perlu Semakan Manual" dalam senarai Pra JTICT. Bahagian ini dikekalkan.
- **Dikawal:**
  - `ApplicationPolicy::decidePreJtict()` mensyaratkan `jtict_meeting_id` tidak kosong, jadi butang dan modal keputusan tidak tersedia;
  - `ApplicationDecisionService` turut menolak permohonan tanpa mesyuarat di backend.
- **Tiada pemprosesan automatik.** Mulai PP4.2A, label permohonan ini ialah "Lengkap", bukan "Menunggu Keputusan Pra JTICT".
- Data pembangunan: permohonan ID 2 dan 3 berstatus Dihantar tanpa mesyuarat (data lama). Jika ditetapkan Lengkap, kedua-duanya akan masuk bahagian Perlu Semakan Manual, bukan senarai menunggu keputusan.

## 7. Integriti data

Semakan pangkalan data pembangunan (MySQL `sppictv2_db`) dibuat secara **baca sahaja**:

| Perkara | Nilai |
|---|---|
| Migrasi | 45, batch maksimum 24 (tidak berubah) |
| applications | 8 (DRAFT 2, SUBMITTED 4, IN_JTICT 2) |
| application_reviews | 10 |
| application_decisions | 3 |
| `jtict_decision` lama | PENDING 8 (tidak berubah) |
| jobs / failed_jobs | 0 / 0 |

Bilangan rekod berbeza berbanding laporan PP4.2 (contohnya keputusan 0 → 3, semakan 9 → 10). Perbezaan ini datang daripada penggunaan aplikasi oleh pemilik sistem (`updated_at` terkini 05/11/2026 15:55), bukan daripada PP4.2A. Ujian PP4.2A menggunakan SQLite in-memory sahaja, dan tiada skrip yang menulis ke pangkalan data pembangunan.

## 8. Isu yang belum selesai

1. **MySQL dan UAT belum dilaksanakan.** UAT tidak dijalankan menggunakan rekod pembangunan sebenar, mengikut arahan.
2. **Kegagalan rawak ujian muat naik dokumen** (`ApplicationDocumentTest`) perlu dipantau. Ujian ini lulus dalam larian ulangan.
3. **Kelas cyan dalam senarai prompt** tidak lagi digunakan selepas reka bentuk kad diubah oleh pemilik. Tiada tindakan diperlukan melainkan kad itu dikembalikan kepada reka bentuk asal.
4. **Penyemakan visual** lencana berbalut pada skrin kecil belum dibuat dalam pelayar.
5. **Prosedur bagi permohonan Perlu Semakan Manual** (tanpa mesyuarat) masih belum ditetapkan oleh pemilik sistem. Isu ini dibawa daripada PP4.2.
6. **Perubahan lain dalam working tree:** peraturan "hanya Pegawai Utama/Kedua aktif boleh meminda selepas dihantar" telah dilaksanakan berasingan sebelum PP4.2A, atas arahan pemilik bertarikh 05/11/2026. Keputusan ujian di atas merangkumi perubahan tersebut. Perubahan ini belum didokumenkan dalam laporan rasmi.
