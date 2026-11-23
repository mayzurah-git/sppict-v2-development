# Laporan Pelaksanaan PP4.3A – Fungsi Keputusan Mesyuarat JTICT

**Sistem:** SPPICT v2.1
**Tarikh:** 16/11/2026
**Cawangan:** `feature/ui-design` (commit `543829a`; **tiada commit dibuat**)
**Rujukan:** Prompt PP4.3A; Laporan Perancangan PP4.3; keputusan pemilik PP42B-01 dan PP42B-05

---

## 1. Ringkasan pelaksanaan

| Perkara | Status |
|---|---|
| Menu dan senarai Keputusan JTICT (3 bahagian) | **PASS** (ujian automatik) |
| Keputusan Lulus / Tidak Lulus / Kaji Semula melalui `ApplicationDecisionService::decide()` | **PASS** |
| Penguatkuasaan PP42B-01 (Pra JTICT Diterima) | **PASS** |
| Permohonan belum bersidang tidak boleh diputuskan | **PASS** |
| Kes ID 6 → Perlu Semakan Manual; ID 2/3 tidak tersenarai | **PASS** (ujian automatik + semakan baca sahaja data pembangunan) |
| Kawalan akses (7 profil pengguna) | **PASS** |
| Suite penuh SQLite | **PASS**: 528/528, 3,980 assertions, 1 *risky* sedia ada (di luar skop) |
| Pengesahan MySQL | **NOT TESTED** (tiada pangkalan data ujian berasingan) |
| UAT dan semakan visual | **NOT TESTED** |
| Migrasi baharu | **NOT APPLICABLE** (tiada) |

Urus Setia kini boleh:
1. melihat permohonan Dalam Proses JTICT di **Permohonan → Keputusan JTICT** (`/urus-setia/jtict`);
2. merekod keputusan Mesyuarat JTICT di halaman butiran permohonan selepas mesyuarat berlangsung.

Keputusan disimpan dalam `application_decisions` dan tidak boleh diubah atau dipadam.

---

## 2. Senarai fail baharu

| Fail | Tujuan |
|---|---|
| `app/Livewire/JtictList.php` | Senarai JTICT: Sedia Diputuskan, Menunggu Mesyuarat, Perlu Semakan Manual; carian; penapis mesyuarat |
| `resources/views/livewire/jtict-list.blade.php` | Paparan senarai (komponen dan gaya sedia ada) |
| `tests/Feature/JtictDecisionTest.php` | 14 ujian (ujian wajib 9–33) |
| `tests/Feature/PreJtictMeetingValidityTest.php` | 10 ujian (ujian wajib 1–8 + paparan) |
| `docs/reports/Laporan Pelaksanaan PP4.3A – Fungsi Keputusan Mesyuarat JTICT.md` | Laporan ini |

## 3. Senarai fail diubah

⚠ = fail yang sudah mengandungi perubahan belum di-commit sebelum PP4.3A. Diff disemak, perubahan sedia ada dikekalkan, dan hanya tambahan minimum dibuat.

| Fail | Perubahan |
|---|---|
| `app/Models/Meeting.php` | `isJtict()`, `hasTakenPlace()`, `preJtictAcceptanceBlocker()`, `isValidForPreJtictAcceptance()`. Tiada perubahan pada logik modul Mesyuarat. |
| `app/Models/Application.php` ⚠ | `preJtictAcceptanceBlocker()`, `jtictMeetingIssue()`, `jtictMeetingBlocker()`; skop `awaitingJtictDecision`, `scheduledForJtict`, `needsManualJtictReview`, `pendingJtict`, `jtictMeetingConsistent` |
| `app/Services/ApplicationDecisionService.php` | Permohonan dipadam ditolak; PP42B-01 dalam `validatePreJtict()`; `jtictMeetingReason()`; semakan mesyuarat penuh dalam `validateJtict()`; docblock dikemas kini |
| `app/Policies/ApplicationPolicy.php` ⚠ | `decideJtict()` diperketat; ability baharu `viewJtictQueue()`; `decidePreJtict()` menolak permohonan dipadam |
| `app/Livewire/ViewApplication.php` ⚠ | Modal JTICT (`openJtictModal`, `closeJtictModal`, `submitJtictDecision`); computed `preJtictAcceptanceBlocker`, `jtictPendingReason` |
| `resources/views/livewire/view-application.blade.php` ⚠ | Kad dan modal Keputusan Mesyuarat JTICT; kad maklumat "belum boleh direkodkan"; butang Pra JTICT Diterima dinyahdayakan bersama sebab |
| `app/Livewire/PreJtictList.php` ⚠ | *Eager loading* relation mesyuarat |
| `resources/views/livewire/pre-jtict-list.blade.php` ⚠ | Penanda "Mesyuarat tidak sah untuk Diterima" dan nota panduan |
| `routes/web.php` ⚠ | Route `/urus-setia/jtict` (`jtict.index`) |
| `resources/views/livewire/layout/navigation.blade.php` ⚠ | Item menu sedia ada "Keputusan JTICT" kini menuju ke `jtict.index` (sebelum ini penapis papan pemuka). Keadaan aktif merangkumi `jtict.*`. 2 baris diubah. |
| `resources/views/dashboard/urus-setia.blade.php` ⚠ | Pautan kad "Keputusan JTICT" ke `jtict.index` (1 baris). Kiraan kad tidak berubah. |
| `tests/Feature/ApplicationDecisionTest.php` ⚠, `tests/Feature/PreJtictDecisionTest.php` ⚠, `tests/Feature/UrusSetiaDashboardTest.php` ⚠ | Kemas kini ujian lama berjustifikasi (Seksyen 8.3) |

**Tidak diubah:**
- migrasi, `.env`, konfigurasi, SMTP, queue, PP2 (`ApplicationReviewService`, `ApplicationSubmissionService`);
- modul Mesyuarat (borang, senarai);
- medan legacy `jtict_decision` / `jtict_remarks`.

---

## 4. Perincian fungsi yang dibangunkan

### 4.1 Model Meeting

- `hasTakenPlace()`: `meeting_date <= hari ini`.
- `isJtict()`: `meetingName.accepts_applications`, sama dengan skop sedia ada `jtict()`.
- `preJtictAcceptanceBlocker()` / `isValidForPreJtictAcceptance()`: tidak dipadam, jenis JTICT, `is_active = true` dan `meeting_date >= hari ini`. Kaedah ini memulangkan sebab dalam Bahasa Melayu jika tidak sah.

### 4.2 Model Application – tiga kategori senarai JTICT

| Skop | Syarat |
|---|---|
| `awaitingJtictDecision()` (Sedia Diputuskan) | `IN_JTICT`, tiada keputusan JTICT, Pra JTICT terkini `ACCEPTED`, mesyuarat wujud / tidak dipadam / jenis JTICT, `meeting_date ≥ tarikh Diterima`, `meeting_date ≤ hari ini` |
| `scheduledForJtict()` (Menunggu Mesyuarat) | Sama, tetapi `meeting_date > hari ini` |
| `needsManualJtictReview()` (Perlu Semakan Manual) | `IN_JTICT`, tiada keputusan JTICT, dan **tidak** memenuhi syarat keselarasan, contohnya tiada mesyuarat, mesyuarat dipadam, bukan JTICT, mesyuarat lebih awal daripada tarikh Diterima, atau tiada Pra JTICT Diterima |

- Syarat ditulis dalam SQL (`whereHas` + subquery berkorelasi) supaya senarai tidak menghasilkan query N+1. Relation mesyuarat dan keputusan di-*eager-load*.
- `jtictMeetingIssue()` memberi sebab bagi Perlu Semakan Manual. `jtictMeetingBlocker()` turut merangkumi "mesyuarat belum berlangsung".
- `is_active` **tidak** disyaratkan bagi keputusan JTICT.

### 4.3 Senarai `JtictList` (`/urus-setia/jtict`)

- Tiga bahagian dengan bilangan; carian No. Rujukan / Nama Projek; penapis Mesyuarat JTICT.
- Hanya "Sedia Diputuskan" mempunyai pautan **Semak & Rekod Keputusan**. Bahagian lain mempunyai pautan **Papar** sahaja.
- "Menunggu Mesyuarat" memaparkan tarikh mesyuarat. "Perlu Semakan Manual" memaparkan sebab.
- Tiada tindakan pukal.

### 4.4 UI keputusan dalam `ViewApplication`

- **Kad "Keputusan Mesyuarat JTICT"** (`@can('decideJtict')`):
  - nama dan tarikh mesyuarat;
  - butang Lulus / Tidak Lulus / Kaji Semula;
  - peringatan bahawa keputusan hanya direkodkan selepas mesyuarat benar-benar bersidang.
- **Modal:**
  - pilihan keputusan JTICT sahaja;
  - Tarikh Keputusan wajib, tanpa nilai lalai, dengan `min` = tarikh mesyuarat dan `max` = hari ini;
  - Ulasan wajib (10–5,000 aksara);
  - butang **Simpan Keputusan** dan **Batal**.
- **Selepas berjaya:** status `CLOSED`; label `Selesai – {Lulus | Tidak Lulus | Kaji Semula} (JTICT)` (logik label sedia ada); mesej kejayaan.
- **Urus Setia bagi permohonan IN_JTICT yang belum sedia:** kad maklumat "Keputusan Mesyuarat JTICT belum boleh direkodkan" bersama sebab.
- **Perlindungan:**
  - tiada fungsi edit atau padam;
  - keputusan JTICT tidak boleh melalui modal Pra JTICT dan sebaliknya (HTTP 422);
  - semakan status sebelum `authorize` menghalang rekod pendua apabila butang diklik berganda.

---

## 5. Penguatkuasaan PP42B-01

| Lapisan | Pelaksanaan |
|---|---|
| Service (sumber kebenaran) | `validatePreJtict()` menolak **Diterima** dengan `ValidationException` (`meetingId`) jika `Application::preJtictAcceptanceBlocker()` tidak null. Mesej menyatakan sebab dan menyarankan Ditolak dengan Pindaan / Ditolak. |
| Modal Pra JTICT | Pilihan Diterima dinyahdayakan, dengan sebab dipaparkan. Payload terus disekat oleh service dan ralat dipaparkan pada medan keputusan. |
| Kad Pra JTICT | Butang Diterima dinyahdayakan (`disabled`) bersama kotak amaran yang menyatakan sebab dan laluan Pindaan. |
| Senarai Pra JTICT | Penanda "Mesyuarat tidak sah untuk Diterima: {sebab}" dan nota panduan |
| Ditolak dengan Pindaan / Ditolak | Tidak terikat syarat mesyuarat. Kedua-duanya berfungsi seperti sedia ada. Pindaan mengekalkan `jtict_meeting_id`, dan pemohon memilih mesyuarat yang dibuka semasa hantar semula (mekanisme PP2, tidak diubah). |
| Rekod terdahulu | Tidak diubah. Sekatan hanya untuk keputusan baharu. |

---

## 6. Pengesahan aliran Pra JTICT → Mesyuarat JTICT → Keputusan JTICT

1. **Pra JTICT Diterima** (mesyuarat Aktif, `meeting_date ≥ hari ini`) → `IN_JTICT`. Permohonan masuk **Menunggu Mesyuarat**.
2. Apabila `meeting_date ≤ hari ini`, permohonan berpindah secara automatik ke **Sedia Diputuskan** (pertanyaan berdasarkan tarikh; tiada perubahan data).
3. **Keputusan JTICT** → `CLOSED` dan rekod `stage = JTICT` dalam `application_decisions`.
4. **Tindakan susulan:**
   - tiada rekod Projek dicipta (disahkan oleh ujian);
   - tiada salinan permohonan;
   - tiada e-mel.

**Data pembangunan** (semakan **baca sahaja** menggunakan skop baharu; pengawal `DB::listen` menolak sebarang pernyataan bukan SELECT):

| ID | Kategori | Sebab |
|---|---|---|
| 6 | Perlu Semakan Manual | Tarikh mesyuarat (23/01/2026) lebih awal daripada tarikh keputusan Pra JTICT Diterima (04/11/2026) |
| 7, 8, 9 | Menunggu Mesyuarat | Mesyuarat 2 (23/11/2026) belum berlangsung |
| 2, 3 | Tidak tersenarai | SUBMITTED tanpa mesyuarat (tiada Pra JTICT) |

Nota: semakan ini menjalankan SQL skop baharu pada MySQL 8.0.46 secara baca sahaja dan memberikan hasil yang dijangka. Ia ialah **pemerhatian keserasian sintaks sahaja**, bukan pengesahan MySQL. Pengesahan MySQL tetap **NOT TESTED**.

---

## 7. Kawalan akses dan keselamatan

| Lapisan | Kawalan |
|---|---|
| Route | `role:pentadbir_urus_setia` + `can:viewJtictQueue` + kumpulan `auth`, `verified`, `active`, `password.changed` |
| Livewire | `Gate::authorize('viewJtictQueue')` dalam `mount()` dan setiap `render()`; `authorize('decideJtict')` semasa buka modal dan sebelum hantar |
| Polisi | `canRecordDecision()` (aktif, tiada kata laluan sementara, Urus Setia, **bukan** superadmin/pengurusan walaupun peranan berganda) + permohonan tidak dipadam + `IN_JTICT` + Pra JTICT terkini ACCEPTED + tiada keputusan JTICT + mesyuarat sedia |
| Service | Dalam transaksi: `lockForUpdate` (termasuk rekod dipadam, supaya penolakan jelas); pengguna dimuat semula; sebab mesyuarat dipulangkan sebagai ralat validasi hanya kepada pengguna berperanan layak; polisi penuh disemak semula; validasi tarikh dan ulasan |

| Profil | Senarai | Rekod keputusan |
|---|---|---|
| Urus Setia aktif | PASS (200) | PASS |
| Urus Setia tidak aktif | Disekat (dilog keluar → log masuk) | Disekat |
| Superadmin | Disekat (403) | Disekat |
| Pengurusan | Disekat (403) | Disekat |
| Pengguna Biasa | Disekat (403) | Disekat |
| Urus Setia + Superadmin | Disekat (403) | Disekat |
| Urus Setia + Pengurusan | Disekat (403) | Disekat |

---

## 8. Senarai ujian dan keputusan sebenar

Semua ujian menggunakan **SQLite `:memory:`** mengikut `phpunit.xml`. Ini disahkan pada masa larian melalui probe: `default=sqlite database=:memory: driver=sqlite`.

### 8.1 Ujian baharu

| # | Ujian wajib | Kaedah ujian | Keputusan |
|---|---|---|---|
| 1 | Diterima – mesyuarat aktif dan akan datang | `PreJtictMeetingValidityTest::test_accepted_is_allowed_for_active_upcoming_meeting` | PASS |
| 2 | Diterima pada hari mesyuarat | `test_accepted_is_allowed_on_the_meeting_day` | PASS |
| 3 | Ditolak – mesyuarat tidak aktif | `test_accepted_is_refused_when_meeting_is_inactive` | PASS |
| 4 | Ditolak – mesyuarat telah berlalu | `test_accepted_is_refused_when_meeting_has_passed` | PASS |
| 5 | Ditolak – bukan JTICT | `test_accepted_is_refused_when_meeting_is_not_jtict` | PASS |
| 6 | Ditolak – mesyuarat dipadam | `test_accepted_is_refused_when_meeting_is_deleted` | PASS |
| 7 | Pindaan dibenarkan walaupun mesyuarat tidak sah | `test_amendment_can_still_be_recorded_when_meeting_is_invalid` | PASS |
| 8 | Ditolak dibenarkan walaupun mesyuarat tidak sah | `test_rejection_can_still_be_recorded_when_meeting_is_invalid` | PASS |
| — | Butang dinyahdayakan + sebab; payload disekat; penanda senarai | `test_accept_button_is_disabled_with_reason_and_payload_is_refused`, `test_valid_meeting_shows_enabled_accept_button` | PASS |
| 9, 32, 33 | Lulus melalui UI; label; sejarah Pra JTICT dan medan legacy tidak berubah | `JtictDecisionTest::test_approved_decision_is_recorded_after_meeting_through_ui` | PASS |
| 10, 11 | Tidak Lulus, Kaji Semula; tiada Projek atau salinan dicipta | `test_not_approved_and_review_decisions_are_recorded` | PASS |
| 12 | Sebelum mesyuarat / sebelum tarikh mesyuarat | `test_decision_cannot_be_recorded_before_meeting_or_before_meeting_date` | PASS |
| 13 | Tarikh masa depan | `test_decision_date_cannot_be_in_the_future` | PASS |
| 14 | Mesyuarat lebih awal daripada Diterima (kes ID 6) → Perlu Semakan Manual; data tidak diubah | `test_meeting_earlier_than_pre_jtict_acceptance_is_refused_and_needs_manual_review` | PASS |
| 15 | Bukan mesyuarat JTICT | `test_non_jtict_meeting_is_refused` | PASS |
| 16 | `is_active` tidak disyaratkan selepas mesyuarat | `test_inactive_meeting_does_not_block_decision_after_meeting` | PASS |
| 17, 18 | Ulasan 10–5,000 (termasuk tepat 10 dan 5,000) | `test_remarks_are_required_between_10_and_5000_characters` | PASS |
| 19 | Keputusan JTICT kedua ditolak (termasuk jika status dipulihkan secara tidak sah) | `test_second_jtict_decision_is_refused` | PASS |
| 20 | Permohonan dipadam | `test_deleted_application_cannot_be_decided` | PASS |
| 21–27 | Kawalan akses (7 profil) | `test_only_active_urus_setia_can_view_list_and_record_decisions` | PASS |
| — | Silang peringkat (JTICT ↔ Pra JTICT) | `test_jtict_decision_cannot_be_recorded_through_pre_jtict_modal_or_vice_versa` | PASS |
| 28–31 | Tiga bahagian; penapis mesyuarat; tiada tindakan bagi Semakan Manual; ID 2/3 tidak tersenarai | `test_list_separates_ready_scheduled_and_manual_review` | PASS |
| — | Pautan papan pemuka / menu | `test_menu_and_dashboard_link_to_jtict_list` | PASS |

Jumlah: `PreJtictMeetingValidityTest` 10/10 (44 assertions); `JtictDecisionTest` 14/14 (129 assertions).

### 8.2 Ujian berkaitan dan suite penuh

| Larian | Keputusan |
|---|---|
| `ApplicationDecisionTest` (PP4.1B) | PASS 24/24 |
| `PreJtictDecisionTest` (PP4.2) | PASS 15/15 |
| `UrusSetiaDashboardTest` | PASS 7/7 |
| Suite penuh (larian 1) | PASS 528/528, 3,980 assertions, 1 *risky* |
| Suite penuh (larian 2, log JUnit) | PASS 528/528, 1 *risky* |

**Ujian *risky* (dilaporkan berasingan, bukan kegagalan):** `UserRoleManagementTest::test_urus_setia_cannot_open_superadmin_self_or_pending_users` tidak membuat assertion. Ia di luar skop dan sudah wujud sebelum PP4.3A.

**Kegagalan semasa pembangunan (telah dibetulkan):**
- Larian pertama: halaman papan pemuka Urus Setia memberi ralat 500 (`Undefined array key "ring"`). Puncanya ialah komen `//` yang saya sisipkan di tengah baris tatasusunan dalam `urus-setia.blade.php`, menjadikan baki baris itu komen. Komen dibuang dan fail dipulihkan kepada kandungan asal, ditambah perubahan pautan sahaja.
- Selepas itu, 11 ujian gagal seperti yang diramalkan dalam laporan perancangan (Seksyen 8.3).

### 8.3 Kemas kini ujian lama (berjustifikasi)

Semua assertion asal dikekalkan. Tiada assertion keselamatan dibuang.

| Ujian | Sebab percanggahan | Perubahan |
|---|---|---|
| `ApplicationDecisionTest`: `acceptedApplication()` (digunakan oleh 5 ujian JTICT) | Merekod Pra JTICT Diterima selepas mesyuarat dianjak ke masa lalu, dan ini kini disekat oleh PP42B-01 | Penerimaan direkodkan pada tarikh mesyuarat (`travelTo`), kemudian keputusan JTICT selepasnya |
| `test_each_decision_sets_expected_status` | Sama | Keputusan Pra JTICT direkodkan sebelum mesyuarat dianjak ke masa lalu (susunan sahaja) |
| `test_remarks_are_required_according_to_owner_decision` | Diterima dengan mesyuarat lepas | Mesyuarat dikembalikan ke tarikh akan datang sebelum semakan "ulasan Diterima tidak wajib" |
| `test_decisions_do_not_touch_legacy_fields_other_records_email_or_copies` | Sama | PP2 Lengkap + Diterima pada tarikh mesyuarat (`travelTo`); semua semakan medan legacy dan e-mel dikekalkan |
| `PreJtictDecisionTest::test_closed_status_label_distinguishes_decision_stage` | Sama | `travelTo` tarikh mesyuarat bagi Lengkap + Diterima |
| `ApplicationDecisionTest::test_only_pre_jtict_decision_route_and_ui_are_exposed` → `test_only_pre_jtict_and_jtict_decision_routes_and_ui_are_exposed` | Pengawal PP4.1B/PP4.2 menyekat sebarang UI JTICT, yang kini ialah fungsi yang diluluskan | Membenarkan `/urus-setia/jtict`. `DecisionStage::Jtict`, `decideJtict` dan penggunaan service masih **hanya** dibenarkan dalam `ViewApplication.php`. Route lain dengan kata kunci keputusan masih disekat. |
| `PreJtictDecisionTest::test_no_edit_or_delete_and_no_jtict_decision_through_pp42` | Senarai putih kaedah awam `ViewApplication` | Ditambah `preJtictAcceptanceBlocker` (paparan) dan `submitJtictDecision` (rekod JTICT). Semakan "tiada edit/padam" dan "JTICT tidak melalui modal Pra JTICT" dikekalkan. |
| `UrusSetiaDashboardTest::test_action_cards_count_only_work_awaiting_urus_setia_and_link_to_lists` | Prompt mewajibkan kad "Keputusan JTICT" dipautkan ke senarai baharu | Pautan dijangka ditukar kepada `route('jtict.index')`; kiraan kad tidak berubah |

---

## 9. Keputusan `npm run build`

- **Dijalankan:** kelas Tailwind baharu (contohnya `border-indigo-200`, `hover:bg-indigo-900`, `border-indigo-700`, `disabled:hover:bg-green-800`) belum ada dalam binaan sebelumnya.
- **Keputusan: PASS.** `vite build` siap dalam 2.07 s tanpa ralat. CSS baharu `app-CLNU8pwf.css` (81.91 kB). Semua kelas di atas disahkan wujud.
- **Nota:**
  - `public/build` diabaikan oleh Git;
  - binaan merangkumi perubahan `resources/css/app.css` pemilik yang belum di-commit (keadaan working tree semasa).

## 10. Status migrasi

**NOT APPLICABLE.** Tiada migrasi baharu. Jumlah fail migrasi kekal 45, dan jadual `migrations` di DB pembangunan kekal 45 / batch 24. Struktur `application_decisions` tidak diubah.

## 11. Pengesahan pangkalan data pembangunan tidak diubah

Semakan **baca sahaja** (pengawal SELECT/SHOW) dibandingkan dengan keadaan semasa audit PP4.3 (16/11/2026, sebelum PP4.3A):

| Perkara | Sebelum (audit PP4.3) | Selepas PP4.3A |
|---|---|---|
| applications / application_decisions / application_reviews | 8 / 5 / 12 | 8 / 5 / 12 |
| projects | 0 | 0 |
| `audits` id maks | 395 | 395 |
| Migrasi | 45 / batch 24 | 45 / batch 24 |
| Status ID 6–9 | IN_JTICT | IN_JTICT (tidak berubah) |

Tiada UAT dijalankan pada `sppictv2_db`. Tiada SQL pembetulan data dijalankan.

## 12. Pengesahan perubahan Git sebelum dan selepas

| Perkara | Sebelum PP4.3A | Selepas PP4.3A |
|---|---|---|
| Cawangan / commit | `feature/ui-design` / `543829a` | Sama (tiada commit, stash, reset atau checkout) |
| Entri `git status` | 57 (43 diubah, 14 baharu) | 63 (45 diubah, 18 baharu) |
| Fail *tracked* baharu diubah | — | `app/Models/Meeting.php`, `app/Services/ApplicationDecisionService.php` |
| Fail baharu | — | `JtictList.php`, `jtict-list.blade.php`, `JtictDecisionTest.php`, `PreJtictMeetingValidityTest.php` (+ laporan ini) |
| Fail pemilik hilang atau ditulis ganti | — | Tiada (semakan senarai: semua 43 fail asal masih diubah; tiada yang dipulihkan) |

## 13. Isu yang masih belum selesai

1. **Pengesahan MySQL: NOT TESTED.** Tiada pangkalan data ujian berasingan (PP42B-02). Ini termasuk kunci baris `lockForUpdate`, subquery berkorelasi `DATE(...)` dalam skop, dan perbandingan tarikh.
2. **UAT dan semakan visual: NOT TESTED.** Senario UAT-43-01 hingga UAT-43-10 dalam laporan perancangan belum dilaksanakan dalam persekitaran ujian. Paparan modal dan senarai pada tablet/telefon belum disemak.
3. **Permohonan ID 6** kekal dalam Perlu Semakan Manual. Penyelesaian memerlukan proses rasmi berasingan (O4, di luar PP4.3A).
4. **Label ID 6** dalam halaman butiran masih "Diterima – Menunggu Mesyuarat JTICT", kerana label global tidak diubah mengikut arahan. Sebab sebenar dipaparkan dalam senarai JTICT dan kad maklumat halaman butiran (Urus Setia sahaja).
5. **Pengesahan "mesyuarat bersidang"** berdasarkan tarikh mesyuarat sahaja. Tiada medan pengesahan mesyuarat selesai, mengikut arahan; peringatan dipaparkan kepada Urus Setia.
6. **Sejarah keputusan JTICT** belum dipaparkan sebagai bahagian khusus dalam halaman butiran. Label status menunjukkan keputusan. Paparan sejarah dan notis kepada pemohon ialah skop PP4.3B.
7. **Garis dasar belum di-commit** (O12). Working tree kini 63 entri, jadi commit oleh pemilik disyorkan sebelum PP4.3B.
8. **Ujian *risky*** `UserRoleManagementTest` (sedia ada, di luar skop).

## 14. Cadangan langkah seterusnya (PP4.3B)

1. Pemilik menyemak dan commit kerja PP4.2–PP4.3A sebagai garis dasar.
2. Laksanakan UAT-43-01 hingga UAT-43-10 dalam persekitaran ujian berasingan, dan semakan visual (desktop, tablet, telefon).
3. **PP4.3B:**
   - sejarah keputusan Pra JTICT dan JTICT dalam halaman butiran (keputusan O9: satu kronologi atau dua bahagian);
   - notis keputusan JTICT kepada pemohon dan pegawai (Lulus / Tidak Lulus / Kaji Semula, dengan arahan penyalinan selepas PP4.3C);
   - nama perekod hanya kepada Urus Setia;
   - semakan lajur "Keputusan" dan kad "Permohonan Projek Lulus" sedia ada;
   - tiada penciptaan Projek (O6).
4. Selari: sediakan pangkalan data ujian MySQL untuk pengesahan PP4.2–PP4.3.
