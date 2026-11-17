# Laporan Pelaksanaan PP4.2 – Fungsi Keputusan Pra JTICT

**Sistem:** SPPICT v2.1, Modul Permohonan Projek (PP)
**Tarikh:** 04/11/2026
**Status:** Berjaya dengan catatan (rujuk Seksyen 10). UAT oleh pengguna sebenar belum dijalankan.

---

## 1. Ringkasan

PP4.2 menyediakan fungsi untuk Urus Setia (`pentadbir_urus_setia`) merekod keputusan Pra JTICT ke atas permohonan yang telah disahkan Lengkap melalui semakan PP2. Tiga keputusan disediakan: **Diterima**, **Ditolak dengan Pindaan** dan **Ditolak**.

Pembangunan ini menggunakan struktur PP4.1B tanpa sebarang perubahan, iaitu:

- jadual `application_decisions`;
- service `ApplicationDecisionService::decide()`;
- enum `ApplicationDecision` dan `DecisionStage`;
- polisi `decidePreJtict()`.

Tiada migration baharu. Keputusan JTICT tidak dibangunkan.

## 2. Audit sebelum pembangunan (Peringkat 1)

| Perkara | Dapatan |
|---|---|
| Migration PP4.1B | Disahkan telah dijalankan (batch 24): `application_decisions` dan `copied_from_application_id`. Tiada migration dicipta atau diubah. |
| Kod PP4.1B | Telah di-commit dalam `543829a`. Service, enum, model dan polisi digunakan seperti sedia ada. |
| Corak PP2 | Semakan kelengkapan dibuat dalam modal di halaman butiran (`ViewApplication`). PP4.2 mengikut corak yang sama. |
| Data pembangunan | 3 permohonan COMPLETE (0003, 0004, 0006). Kesemuanya mempunyai Mesyuarat JTICT dan rekod PP2 Lengkap. `application_decisions` kosong. |
| Perubahan pengguna yang belum di-commit | Terdapat kerja UI anda yang belum di-commit, antaranya lajur Keputusan dalam `ApplicationList`, warna oren bagi status Pindaan Pra JTICT, papan pemuka pengguna dan navigasi. Fail-fail ini tidak ditulis semula. Hanya suntingan tambahan kecil dibuat pada tiga fail (Seksyen 3). |

### Percanggahan dan cara ia dikendalikan

1. **Label status (Seksyen 11 spesifikasi).** Menukar `ApplicationStatus::label()` akan mengubah penapis status, kad papan pemuka, ujian PP1 dan paparan modul lain. Label asas ("Lengkap", "Dalam Proses JTICT" dan sebagainya) dikekalkan. Label terperinci disediakan melalui `Application::statusDisplayLabel()` dan digunakan di halaman butiran dan senarai Pra JTICT.
2. **"Tarikh Permohonan".** Jadual `applications` tiada lajur `submitted_at`. Nilai `created_at` digunakan, sama dengan "Dihantar pada" di halaman butiran. Tarikh PP2 Lengkap terakhir turut dipaparkan.
3. **Permohonan lama tanpa Mesyuarat JTICT.** Polisi `decidePreJtict()` kini menolak keputusan bagi permohonan tanpa `jtict_meeting_id`. Rekod sebegini disenaraikan berasingan di bawah "Perlu Semakan Manual" dan tidak diproses secara automatik. Ini mengubah tingkah laku PP4.1B, yang sebelum ini membenarkan keputusan Pra JTICT tanpa mesyuarat. Tiada data pembangunan semasa yang terjejas.

## 3. Senarai fail

### Fail baharu

| Fail | Tujuan |
|---|---|
| `app/Livewire/PreJtictList.php` | Senarai permohonan menunggu keputusan Pra JTICT dan senarai Perlu Semakan Manual. |
| `resources/views/livewire/pre-jtict-list.blade.php` | Paparan senarai. |
| `tests/Feature/PreJtictDecisionTest.php` | 15 ujian PP4.2. |
| `docs/Laporan Pelaksanaan PP4.2 - Fungsi Keputusan Pra JTICT.md` | Laporan ini. |

### Fail diubah (tambahan sasaran sahaja)

| Fail | Perubahan |
|---|---|
| `app/Policies/ApplicationPolicy.php` | `viewPreJtictQueue()` baharu. `decidePreJtict()` kini mensyaratkan `jtict_meeting_id` wujud. |
| `app/Models/Application.php` | Skop `awaitingPreJtictDecision()` dan `needsManualPreJtictReview()`, relation `latestCompleteReview()`, dan `statusDisplayLabel()`. Kod `scopeForApplicant()` anda dikekalkan. |
| `app/Models/ApplicationDecision.php` | Skop `finalPreJtict()` (Diterima / Ditolak). |
| `app/Livewire/ViewApplication.php` | Modal keputusan Pra JTICT, sejarah keputusan, dan ulasan Pindaan Pra JTICT untuk pemohon. |
| `resources/views/livewire/view-application.blade.php` | Kad tindakan Pra JTICT, notis pindaan, bahagian Sejarah Keputusan Pra JTICT, modal keputusan dan lencana status terperinci. |
| `app/Livewire/ApplicationForm.php` dan `resources/views/livewire/application-form.blade.php` | Ulasan Pra JTICT dipaparkan kepada pemohon semasa meminda. Logik PP2 `isAmendment` tidak diubah. |
| `resources/views/livewire/application-form/phase-5-review-submit.blade.php` | Mesej "penghantaran semula ke mesyuarat asal" turut dipaparkan bagi Pindaan Pra JTICT. |
| `resources/views/components/application-status-badge.blade.php` | Prop pilihan `label`. Lalai tidak berubah. Baris warna oren anda dikekalkan. |
| `resources/views/dashboard/urus-setia.blade.php` | Kad "Menunggu keputusan Pra JTICT" (bilangan dan pautan) dalam bahagian Perlu Tindakan. |
| `resources/views/livewire/layout/navigation.blade.php` | Satu baris menu "Pra JTICT" untuk Urus Setia. Perubahan anda yang belum di-commit dikekalkan. |
| `routes/web.php` | Route `GET /urus-setia/pra-jtict` (`pre-jtict.index`). |
| `tests/Feature/ApplicationDecisionTest.php` | Ujian PP4.1B `test_no_decision_route_or_ui_is_exposed` diganti dengan `test_only_pre_jtict_decision_route_and_ui_are_exposed`. Justifikasi: tujuan asal ujian ialah "tiada UI sebelum PP4.2". PP4.2 memang membuka UI Pra JTICT, jadi ujian kini memastikan keputusan **JTICT** masih tidak didedahkan (tiada route; tiada `DecisionStage::Jtict` atau `decideJtict` dalam Livewire/Controller) dan hanya `ViewApplication` menggunakan service keputusan. |

## 4. Fungsi yang dibangunkan

1. **Senarai menunggu keputusan** di `/urus-setia/pra-jtict` (menu "Pra JTICT").
   - **Kriteria:**
     - status `COMPLETE`;
     - mempunyai Mesyuarat JTICT;
     - mempunyai rekod PP2 Lengkap dalam `application_reviews`;
     - tiada keputusan Pra JTICT muktamad (Diterima / Ditolak) dalam `application_decisions`;
     - tidak dipadam (soft delete).
   - **Ditolak dengan Pindaan bukan keputusan muktamad.** Selepas dipinda dan disahkan Lengkap semula, permohonan itu muncul semula dengan tanda "Dihantar semula selepas Pindaan Pra JTICT".
   - **Lajur:** No. Rujukan, Nama Projek, Jabatan/Agensi, Tarikh Permohonan (dan tarikh Lengkap), Pemohon, Status, Mesyuarat JTICT, dan tindakan "Semak & Putuskan".
   - **Bahagian "Perlu Semakan Manual"** untuk permohonan Lengkap tanpa mesyuarat, tanpa rekod PP2 atau dengan keputusan muktamad yang tidak konsisten. Keputusan tidak boleh dibuat untuk permohonan ini.
2. **Paparan butiran** (halaman sedia ada `application.view`).
   - Baca sahaja.
   - Memaparkan Fasa 1 hingga 5, dokumen (mekanisme muat turun sedia ada), sejarah semakan PP2 dan Mesyuarat JTICT.
3. **Borang keputusan** (modal) dengan tiga medan:
   - Keputusan (radio);
   - Tarikh Keputusan, input tarikh tanpa nilai lalai, dengan petunjuk `min`/`max`;
   - Ulasan, ditanda wajib apabila Ditolak dengan Pindaan dipilih.
4. **Sejarah Keputusan Pra JTICT**, terkini dahulu (tarikh keputusan, kemudian ID).
   - Memaparkan keputusan, tarikh keputusan, ulasan dan tarikh rekod.
   - Nama perekod dipaparkan kepada Urus Setia sahaja (K5). Pemohon melihat "Urus Setia".
   - Tiada fungsi edit atau padam.
5. **Paparan kepada pemohon.**
   - Notis Ditolak dengan Pindaan bersama ulasan dan butang "Kemas Kini & Hantar Semula", dipaparkan di halaman butiran dan dalam borang permohonan.
   - Penghantaran semula menggunakan aliran PP2 sedia ada (rekod "Dihantar Semula" dalam `application_reviews`, dengan peraturan mesyuarat PP2).
6. **Label status terperinci:**
   - Lengkap – Menunggu Keputusan Pra JTICT
   - Diterima – Menunggu Mesyuarat JTICT (hanya jika rekod Diterima wujud)
   - Ditolak dengan Pindaan – Menunggu Tindakan Pemohon
   - Selesai – Ditolak (Pra JTICT) / Selesai – {keputusan} (JTICT) / Selesai (tanpa rekod keputusan, tiada andaian)
7. **Papan pemuka Urus Setia:** kad "Menunggu keputusan Pra JTICT" bersama bilangan dan pautan.

## 5. Kawalan akses

| Lapisan | Kawalan |
|---|---|
| Route senarai | `role:pentadbir_urus_setia` dan `can:viewPreJtictQueue`. Polisi ini menolak Superadmin, Pengurusan dan akaun tidak aktif, termasuk pengguna yang memegang dua peranan. |
| Komponen senarai | `Gate::authorize('viewPreJtictQueue')` dalam `mount()` dan pada setiap `render()`. |
| Halaman butiran | `ApplicationPolicy::view()` sedia ada (tidak diubah). Superadmin disekat oleh route sedia ada. |
| Buka dan hantar borang | `authorize('decidePreJtict')` dalam `openPreJtictModal()` dan `submitPreJtictDecision()`. |
| Service (backend) | `ApplicationDecisionService::decide()` menyemak semula polisi, status dan peralihan di dalam transaksi, ke atas rekod yang dikunci (`lockForUpdate`) dan pengguna yang dibaca semula daripada DB. |

## 6. Integrasi `ApplicationDecisionService`

- Komponen hanya memanggil `decide($application, DecisionStage::PreJtict, $decision, $remarks, $date, Auth::user())`. Tiada logik keputusan diduplikasi.
- **Validasi di service:**
  - keputusan mesti milik peringkat Pra JTICT;
  - ulasan wajib bagi Ditolak dengan Pindaan; jika diisi, minimum 10 dan maksimum 5000 aksara (had sedia ada PP4.1B, sama dengan PP2);
  - tarikh wajib, `Y-m-d`, tidak melebihi hari ini, dan tidak lebih awal daripada tarikh PP2 Lengkap terakhir.
- **Ralat service dipetakan ke medan borang:**
  - `ValidationException` kepada `preJtictDecision`, `preJtictRemarks` atau `preJtictDate`;
  - `AuthorizationException` kepada mesej ralat.
- **Transaksi:**
  - rekod `application_decisions` dan `applications.status` dalam satu `DB::transaction`;
  - kegagalan membatalkan kedua-duanya (diuji).
- **Tidak disentuh:** `jtict_meeting_id`, `jtict_decision`, `jtict_remarks` dan `application_reviews`.
- **Tiada e-mel dihantar.** Penerima yang dipersetujui (pendaftar dan Pegawai Utama aktif) boleh ditambah kemudian tanpa mengubah struktur sejarah.

## 7. Keputusan ujian (SQLite in-memory sahaja)

| Ujian | Keputusan |
|---|---|
| `PreJtictDecisionTest` (baharu) dan `ApplicationDecisionTest` (PP4.1B) | 39 lulus, 474 assertion |
| Suite penuh | **486 lulus, 0 gagal**, 3720 assertion, 1 *risky*. Ujian *risky* ini sudah wujud sebelum PP4.2. Kod keluar 1 disebabkan oleh ujian *risky* tersebut, bukan kegagalan. |

Liputan Seksyen 12 spesifikasi:

- **A. Diterima:** status IN_JTICT, rekod PRE_JTICT/ACCEPTED, mesyuarat tidak berubah, ulasan kosong diterima.
- **B. Ditolak dengan Pindaan:**
  - ulasan kosong ditolak;
  - status PRE_JTICT_AMENDMENT;
  - ID, UUID dan nombor rujukan kekal; tiada permohonan baharu dicipta;
  - kitaran penuh melalui UI: pemohon melihat ulasan, meminda dan menghantar semula;
  - "Dihantar Semula" direkod dalam `application_reviews`;
  - PP2 Lengkap semula, muncul semula dalam senarai, kemudian Diterima;
  - kedua-dua keputusan kekal dalam sejarah.
- **C. Ditolak:** status CLOSED; tidak boleh dikemas kini atau dihantar semula; kekal dalam senarai permohonan agensi.
- **D. Tarikh:**
  - tarikh masa hadapan ditolak;
  - tarikh sebelum PP2 Lengkap ditolak;
  - tarikh kosong ditolak;
  - tiada tarikh lalai;
  - tarikh sah diterima.
- **E. Akses:**
  - pengguna_biasa, pengurusan, superadmin, dwi-peranan (Urus Setia + Superadmin) dan Urus Setia tidak aktif ditolak melalui service terus, payload Livewire dan route senarai;
  - pengguna dari agensi lain tidak boleh melihat butiran atau sejarah.
- **F. Integriti:**
  - status tidak layak ditolak;
  - dua keputusan serentak menghasilkan satu rekod sahaja;
  - kegagalan simpan menyebabkan rollback;
  - tiada tindakan edit atau padam;
  - keputusan JTICT (APPROVED, NOT_APPROVED, REVIEW) ditolak (422);
  - permohonan lain, `jtict_decision`, `jtict_remarks` dan `application_reviews` tidak disentuh;
  - tiada e-mel atau notifikasi dihantar.

**MySQL belum diuji.** Kunci baris `lockForUpdate` dan tingkah laku transaksi pada MySQL tidak dibuktikan oleh ujian SQLite. Ujian serentak hanya mensimulasikan dua borang yang dibuka serentak (semakan status di pelayan), bukan dua sambungan DB selari.

## 8. Status integriti data (semakan baca sahaja sebelum dan selepas)

| Perkara | Sebelum | Selepas |
|---|---|---|
| migrations / batch maksimum | 45 / 24 | 45 / 24 |
| applications / application_reviews / application_decisions | 8 / 9 / 0 | 8 / 9 / 0 |
| meetings / users / application_documents | 2 / 13 / 13 | 2 / 13 / 13 |
| status | COMPLETE 3, DRAFT 2, SUBMITTED 3 | sama |
| `jtict_decision` | PENDING 8 | sama |
| `applications.updated_at` maksimum / audits id maksimum | 2026-11-03 13:54:34 / 362 | sama |

Tiada keputusan direkodkan dalam DB pembangunan. Semua ujian menggunakan SQLite in-memory.

## 9. Pengesahan

- Tiada migration baharu dicipta atau dijalankan. Migration PP4.1B tidak diubah.
- Tiada `migrate:fresh`, `refresh`, `rollback`, `db:wipe` atau seeder dijalankan.
- Tiada perubahan pada `.env`, SMTP, queue atau Mailtrap.
- Tiada operasi Git (commit, push, reset, checkout, stash atau clean).
- Tiada perubahan pada modul Mesyuarat, polisi conflict of interest atau kod semakan PP2 (`ApplicationReviewService`, `review()`).
- Tiada fungsi Salin Permohonan, keputusan JTICT, e-mel keputusan atau pembetulan/pemadaman keputusan.

## 10. Isu dan risiko yang belum selesai

1. **Aset CSS perlu dibina semula.** Beberapa kelas Tailwind baharu belum ada dalam `public/build`, iaitu `border-orange-500`, `bg-cyan-50`, `border-cyan-200`, `text-cyan-900` dan `min-w-[900px]`. Jalankan `npm run build` (atau `npm run dev`) apabila sesuai. Fungsi tidak terjejas; hanya warna atau tepi tertentu yang tidak dipaparkan sehingga aset dibina semula. `npm run build` tidak dijalankan kerana ia menjana semula fail yang turut digunakan oleh kerja UI anda.
2. **Tanda "Dihantar semula selepas pindaan" dalam senarai PP2 Urus Setia** (`ApplicationList`) hanya mengenal Tidak Lengkap. Permohonan yang dihantar semula selepas Pindaan Pra JTICT tidak ditanda dalam senarai semakan PP2. Fail ini mengandungi perubahan anda yang belum di-commit, jadi ia tidak diubah. Cadangan: tambah syarat `orWhere('from_status', 'PRE_JTICT_AMENDMENT')` pada `was_returned`, tertakluk kelulusan.
3. **Label status terperinci** hanya digunakan di halaman butiran dan senarai Pra JTICT. Senarai permohonan pemohon dan papan pemuka masih menggunakan label asas, tetapi lajur "Keputusan" (kerja UI anda) sudah membezakan peringkat keputusan.
4. **Ulasan pilihan** (Diterima / Ditolak): jika diisi, ia mesti sekurang-kurangnya 10 aksara. Had ini datang daripada PP4.1B dan sama dengan PP2; ia tidak diubah.
5. **Permohonan lama tanpa Mesyuarat JTICT:** tiada dalam data semasa. Prosedur pengendalian rasmi perlu ditetapkan oleh pemilik sistem.
6. **UAT dan MySQL:** belum diuji oleh pengguna sebenar dan belum diuji pada MySQL.
