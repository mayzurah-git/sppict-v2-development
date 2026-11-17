# Laporan Perancangan PP4.3 – Keputusan Mesyuarat JTICT

**Sistem:** SPPICT v2.1
**Tarikh:** 16/11/2026
**Cawangan:** `feature/ui-design` (commit terakhir `543829a`; tiada commit atau perubahan kod dibuat)
**Jenis fasa:** Audit dan perancangan sahaja
**Rujukan:** Laporan PP4.1B, PP4.2, PP4.2A, PP4.2B; keputusan pemilik bagi PP42B-01 dan PP42B-05 (Pilihan A)

---

## 1. Ringkasan eksekutif

- **Asas sedia ada:** struktur keputusan JTICT sudah wujud sejak PP4.1B, iaitu enum, jadual `application_decisions`, polisi `decideJtict` dan `ApplicationDecisionService::validateJtict()`. Ujian peringkat service juga sudah ada. Yang **belum wujud** ialah route, Livewire, UI, paparan pemohon, fungsi Salin Permohonan dan e-mel keputusan.
- **Polisi dan service belum memenuhi spesifikasi PP4.3:**
  - tiada semakan bahawa mesyuarat telah berlangsung dalam polisi;
  - tiada semakan "mesyuarat selepas tarikh Pra JTICT Diterima";
  - PP42B-01 (sekat Pra JTICT Diterima bagi mesyuarat tidak aktif atau telah berlalu) **belum dilaksanakan**.
- **Tiada proses rasmi untuk menukar Mesyuarat JTICT** bagi permohonan yang sudah dihantar. `jtict_meeting_id` hanya ditetapkan semasa penghantaran atau penghantaran semula (`ApplicationSubmissionService::submit()`). Ini menentukan pengendalian permohonan ID 6 (IN_JTICT, mesyuarat telah berlalu) dan ID 2/3 (tiada mesyuarat). Lihat Seksyen 6 dan 12.
- **Fungsi Salin Permohonan belum wujud.** Hanya lajur `copied_from_application_id` dan relation model yang ada. Ia boleh dibina **tanpa migrasi**, kerana semua medan master data boleh NULL.
- **Garis dasar ujian (SQLite):** suite penuh 504/504 lulus, 1 *risky* sedia ada. Pelaksanaan PP42B-01 akan memerlukan **8 ujian sedia ada** disusun semula. Ujian ini menetapkan mesyuarat ke masa lalu sebelum merekod Pra JTICT Diterima, jadi ia bercanggah dengan dasar baharu. Dua ujian pengawal PP4.1B/PP4.2 dan dua ujian "tiada e-mel" juga perlu dikemas kini apabila UI JTICT dan e-mel ditambah (Seksyen 11).
- **Cadangan urutan:** PP4.3A (termasuk penguatkuasaan PP42B-01) → PP4.3B → PP4.3D → PP4.3C, tertakluk kepada **12 keputusan pemilik** (Seksyen 12). Keputusan O1–O4 diperlukan sebelum PP4.3A.

---

## 2. Status sebenar implementasi semasa

Sumber: kod semasa dalam working tree, bukan laporan terdahulu.

| Komponen | Status | Bukti kod |
|---|---|---|
| Enum keputusan JTICT (`APPROVED`, `NOT_APPROVED`, `REVIEW`) | **Wujud** | `app/Enums/ApplicationDecision.php`: `stage()` = JTICT, `targetStatus()` = CLOSED, `requiresRemarks()` = true |
| `DecisionStage::Jtict` → status wajib `IN_JTICT` | **Wujud** | `app/Enums/DecisionStage.php::requiredStatus()` |
| Peralihan `IN_JTICT → CLOSED` | **Wujud** | `ApplicationStatus::allowedTransitions()` |
| Service keputusan JTICT | **Wujud (separa)** | `ApplicationDecisionService::decide()` + `validateJtict()`: mesyuarat wujud, tidak dipadam, jenis JTICT; tarikh ≥ tarikh mesyuarat; tarikh ≤ hari ini; ulasan 10–5000 aksara; transaksi + `lockForUpdate` |
| Polisi `decideJtict` | **Wujud (separa)** | `ApplicationPolicy::decideJtict()`: Urus Setia aktif (bukan superadmin/pengurusan), status IN_JTICT, Pra JTICT terkini ACCEPTED. **Tiada** semakan mesyuarat. |
| Route / Livewire / UI keputusan JTICT | **Tiada** | Tiada route JTICT dalam `routes/web.php`. Ujian `test_only_pre_jtict_decision_route_and_ui_are_exposed` menyekat `decideJtict` dan `DecisionStage::Jtict` dalam Livewire/Controller. |
| Label "Selesai – {keputusan} (JTICT)" | **Wujud** | `Application::statusDisplayLabel()` |
| Lajur "Keputusan" senarai pemohon / kad "Permohonan Projek Lulus" | **Wujud** (kerja pemilik, belum di-commit) | `application-list.blade.php` (JTICT diutamakan), `dashboard/pengguna.blade.php` (`latestJtictDecision` = Approved) |
| Sejarah keputusan dalam halaman butiran | **Pra JTICT sahaja** | `ViewApplication::preJtictDecisions()` (penapis `stage = PRE_JTICT`) |
| PP42B-01 (sekat Pra JTICT Diterima bagi mesyuarat tidak sah) | **Belum dilaksanakan** | `validatePreJtict()` tidak menyemak `is_active` atau `meeting_date` |
| Fungsi Salin Permohonan | **Tiada** | Hanya lajur, relation `copiedFromApplication()` / `copies()` dan ujian struktur |
| Penciptaan rekod Projek selepas Lulus | **Tiada** | Tiada `Project::create` dalam `app/`. Jadual `projects` kosong. |
| E-mel keputusan Pra JTICT / JTICT | **Tiada** | `app/Notifications`: hanya `ApplicationMarkedIncomplete` (PP2), `MeetingAnnouncement`, `ResponsibleOfficerChanged` dan notifikasi akaun |

---

## 3. Pengesahan struktur jadual dan model

Struktur disahkan dalam PP4.2B (MySQL 8.0.46, `SHOW CREATE TABLE`). Kod model semasa disemak semula.

| Item | Keadaan | Kesesuaian PP4.3 |
|---|---|---|
| `application_decisions` | UUID unik, `stage`, `decision`, `from_status`, `to_status`, `remarks`, `decision_date` (date), `meeting_id` (NULL), `decided_by`, `created_at` sahaja; FK NO ACTION | Mencukupi untuk JTICT; **tiada migrasi diperlukan** |
| Model `ApplicationDecision` | `UPDATED_AT = null`; `updating` / `deleting` melontar `LogicException`; semakan pasangan peringkat–keputusan semasa `creating` | Sejarah JTICT tidak boleh diubah, seperti Pra JTICT |
| Model `ApplicationDecision` – audit | **Tidak** melaksanakan `Auditable` (owen-it) | Rekod tidak boleh diubah, jadi rekod itu sendiri ialah jejak audit. Perubahan status `applications` diaudit (model `Application` ialah `Auditable`). |
| `applications.jtict_decision`, `jtict_remarks` | Dikekalkan (legacy, `PENDING` × 8) | PP4.3 **tidak** akan menulis ke medan ini |
| `applications.copied_from_application_id` | NULL, FK NO ACTION, indeks biasa (bukan unik), tiada dalam `$fillable` | Sesuai untuk Salin (ditetapkan melalui `forceFill`). Syarat "satu salinan" perlu dikawal dalam kod, atau indeks unik melalui migrasi tambahan (perlu kelulusan, O8). |
| Master data dalam permohonan | `application_category` (kod), `procurement_type_id`, `procurement_method_id`, `application_details.project_category_id`: semuanya **nullable** | Salin boleh mengosongkan master data tidak aktif tanpa perubahan struktur |
| `meetings` | `meeting_date` (date), `meeting_time`, `is_active` (boolean), `submission_opens_at`, `submission_closes_at`, `deleted_at`; jenis JTICT melalui `meeting_names.accepts_applications` | Lihat Seksyen 6 |

---

## 4. Pengesahan status dan aliran keputusan

### 4.1 Aliran semasa (kod)

```
DRAFT/NEEDS_AMENDMENT/PRE_JTICT_AMENDMENT → SUBMITTED → NEEDS_AMENDMENT | COMPLETE
COMPLETE → IN_JTICT (Pra JTICT Diterima) | PRE_JTICT_AMENDMENT (Pindaan) | CLOSED (Ditolak)
IN_JTICT → CLOSED (JTICT Lulus / Tidak Lulus / Kaji Semula)
CLOSED → (tiada)
```

### 4.2 Spesifikasi PP4.3 berbanding kod semasa

| # | Syarat spesifikasi | Kod semasa | Jurang / tindakan PP4.3A |
|---|---|---|---|
| 1 | Status `IN_JTICT` | Polisi + service (`requiredStatus`) | — |
| 2 | Pra JTICT terkini `ACCEPTED` | Polisi (`latestPreJtictDecision`); service melalui polisi | — |
| 3 | Mesyuarat JTICT sah | Service: wujud, tidak dipadam, `jtict()` | Definisi "sah" perlu disahkan (O3). Tambah semakan dalam **polisi** supaya butang tidak dipaparkan. |
| 4 | Mesyuarat telah berlangsung | **Tersirat** dalam service: tarikh keputusan ≥ `meeting_date` dan ≤ hari ini, jadi `meeting_date` ≤ hari ini | Jadikan eksplisit dalam polisi dan senarai (`Meeting::hasTakenPlace()`) |
| 5 | Belum ada keputusan JTICT muktamad | Tersirat: selepas keputusan, status menjadi CLOSED | Tambah semakan eksplisit `latestJtictDecision === null` (pertahanan berlapis) |
| 6 | Permohonan bukan soft-deleted | `Application::lockForUpdate()->findOrFail()` mengecualikan rekod dipadam tetapi melontar `ModelNotFoundException` (HTTP 404) | Tukar kepada penolakan yang jelas (AuthorizationException) dan tambah ujian |
| 7 | Ulasan wajib, 10–5000 aksara | `requiresRemarks()` + `REMARKS_MIN` / `REMARKS_MAX` | — |
| 8 | Tarikh ≤ hari ini | `before_or_equal:today` | — |
| 9 | Tarikh ≥ tarikh mesyuarat | `validateJtict()` | — |
| 10 | Melalui `decide()`, sejarah dalam `application_decisions` | Ya | — |
| Cadangan 11 | Tarikh mesyuarat ≥ tarikh Pra JTICT Diterima terkini (mesyuarat tidak boleh berlaku sebelum permohonan diterima) | **Tiada** | Tambah dalam service dan polisi. Ini menghalang kes seperti ID 6 (O4). |

### 4.3 PP42B-01 – sekatan Pra JTICT Diterima (keputusan pemilik: Pilihan A)

**Belum dilaksanakan.** Cadangan pelaksanaan dalam PP4.3A (langkah pertama):
- Keputusan **Diterima** hanya dibenarkan jika Mesyuarat JTICT permohonan memenuhi semua syarat berikut:
  - wujud dan tidak dipadam;
  - jenis JTICT;
  - `is_active = true`;
  - `meeting_date ≥ hari ini` (O1: sama ada hari mesyuarat itu sendiri dibenarkan).
- Keputusan **Ditolak dengan Pindaan** dan **Ditolak** kekal dibenarkan, kerana keputusan ini tidak bergantung pada mesyuarat. Pindaan juga ialah laluan rasmi untuk mendapat mesyuarat baharu (Seksyen 6.3, O2).
- Lokasi semakan:
  - `ApplicationDecisionService::validatePreJtict()` (khusus Diterima, kunci ralat `meetingId`);
  - modal Pra JTICT (butang Diterima dinyahdayakan dengan penjelasan);
  - `PreJtictList` (petunjuk "Mesyuarat tidak sah").

---

## 5. Pengesahan kawalan akses

| Lapisan | Pra JTICT (PP4.2, semasa) | JTICT (cadangan PP4.3A) |
|---|---|---|
| Route | `role:pentadbir_urus_setia` + `can:viewPreJtictQueue` | Route baharu `/urus-setia/jtict` dengan `role:pentadbir_urus_setia` + `can:viewJtictQueue` (ability baharu) |
| Livewire | `authorize('decidePreJtict')` semasa buka modal; semak semula status sebelum `authorize` semasa hantar | Corak sama: `openJtictModal` / `submitJtictDecision` |
| Polisi | `canRecordDecision()`: `canUseSystem()` (aktif + tiada kata laluan sementara) + Urus Setia + **bukan** superadmin/pengurusan (termasuk peranan berganda) | `decideJtict()` + syarat mesyuarat (Seksyen 4.2) |
| Service | Pengguna dimuat semula (`User::findOrFail`) dan polisi disemak semula dalam transaksi | Sama (sudah wujud) |
| Akses paparan | `view`: pemohon, Urus Setia, agensi sama, pegawai aktif | Tidak berubah |

- **Superadmin** disekat daripada semua route permohonan (`not-role:superadmin`) dan daripada keputusan (polisi).
- **Pengurusan** dan **pengguna_biasa** tidak dapat membuat keputusan pada mana-mana lapisan.
- Ujian sedia ada meliputi superadmin, pengurusan, pengguna_biasa, Urus Setia + superadmin dan Urus Setia tidak aktif. Gabungan **Urus Setia + pengurusan** belum diuji secara khusus. Cadangan: tambah ujian ini dalam PP4.3A.

**Percanggahan dokumentasi:** `docs/AGENTS.md` (matriks RBAC) memberi superadmin "Urus Penuh" bagi Keputusan. Kod mengikut keputusan PP4, iaitu superadmin tidak dibenarkan. Kod diutamakan, dan AGENTS.md perlu dikemas kini (PP42B-09).

---

## 6. Pengesahan hubungan Mesyuarat JTICT

### 6.1 Medan dan logik sebenar (`app/Models/Meeting.php`, `app/Livewire/Meetings/MeetingForm.php`)

| Konsep | Kod sebenar |
|---|---|
| Jenis JTICT | Skop `jtict()`: `meetingName.accepts_applications = true` |
| Status aktif | `is_active` (boolean) **ditetapkan secara manual** oleh Urus Setia dalam borang mesyuarat. Tiada penyahaktifan automatik selepas tarikh mesyuarat. |
| Tempoh terima permohonan | `openForSubmission()`: aktif dan hari ini dalam `submission_opens_at`..`submission_closes_at` |
| Satu mesyuarat dibuka | `ensureNoOverlappingOpenMeeting()`: tempoh permohonan JTICT aktif tidak boleh bertindih |
| "Telah berlangsung" | **Tiada** kaedah. Hanya `meeting_date` (date) dan `meeting_time`. |
| Pemadaman | `MeetingList::delete()` menolak mesyuarat yang mempunyai permohonan (`hasLinkedApplications()`) |
| Penetapan mesyuarat kepada permohonan | **Hanya** dalam `ApplicationSubmissionService::submit()` (baris 94), semasa penghantaran pertama atau penghantaran semula pindaan. Tiada fungsi lain menukar `jtict_meeting_id`. |

**Implikasi bagi definisi "mesyuarat sah" (O3):** `is_active` ialah bendera tempoh penerimaan permohonan, bukan bukti mesyuarat diadakan. Jika keputusan JTICT mensyaratkan `is_active = true`, keputusan tidak dapat direkodkan sebaik sahaja Urus Setia menyahaktifkan mesyuarat selepas ia berlangsung.

Cadangan definisi:
- **Pra JTICT Diterima:** aktif + belum berlalu (PP42B-01).
- **JTICT:**
  - wujud, tidak dipadam dan jenis JTICT;
  - `meeting_date` ≤ hari ini (telah berlangsung);
  - `meeting_date` ≥ tarikh Pra JTICT Diterima;
  - **tanpa** syarat `is_active`.

### 6.2 Anomali data mesyuarat (pemerhatian, baca sahaja)

Mesyuarat 1 (JTICTNS) mempunyai `submission_opens_at = 05/10/2026` tetapi `submission_closes_at = 09/01/2026` dan `meeting_date = 23/01/2026`.
- Punca yang mungkin: mesyuarat lama itu diaktifkan semula pada 05/10. `MeetingForm` menetapkan `submission_opens_at = hari ini` apabila status ditukar kepada Aktif, dan tiada pengesahan buka ≤ tutup bagi rekod sedia ada.
- Ini di luar skop PP4.3 (modul Mesyuarat), tetapi menjelaskan bagaimana permohonan ID 6 dikaitkan dengan mesyuarat yang telah berlalu.
- Dilaporkan untuk keputusan pemilik (O11).

### 6.3 Kes khusus

**Permohonan ID 6:**
- Status IN_JTICT, Mesyuarat 1 (tidak aktif, 23/01/2026), Pra JTICT ACCEPTED bertarikh 04/11/2026, tiada keputusan JTICT.
- Rekod sejarah **dikekalkan**; tiada perubahan atau pembetulan automatik.
- Di bawah peraturan cadangan (syarat 11), keputusan JTICT **disekat**, kerana mesyuarat 23/01 berlaku sebelum permohonan diterima pada 04/11.
- **Tiada proses rasmi sedia ada** yang boleh menukar mesyuarat bagi permohonan IN_JTICT. Laluan Pindaan hanya wujud dari status COMPLETE.
- Cadangan: dalam PP4.3A, permohonan ini dipaparkan dalam bahagian **"Perlu Semakan Manual"** senarai JTICT bersama sebab, dan label paparannya ditukar daripada "Menunggu Mesyuarat JTICT" kepada "Mesyuarat JTICT tidak sah – Perlu Semakan Manual". Penyelesaian muktamad memerlukan keputusan pemilik (O4).

**Permohonan ID 2 dan 3:**
- Status SUBMITTED, tiada Mesyuarat JTICT (data lama). Kedua-duanya kekal di luar senarai keputusan, dan polisi Pra JTICT mensyaratkan mesyuarat.
- **Proses rasmi sedia ada BOLEH menetapkan mesyuarat**, melalui laluan PP2 **Tidak Lengkap → pemohon hantar semula**. Bukti kod:
  - `Application::targetJtictMeeting()` mengembalikan `Meeting::currentJtict()` apabila `jtict_meeting_id` kosong;
  - `requiresMeetingSelection()` hanya untuk permohonan yang sudah mempunyai mesyuarat;
  - `submit()` kemudian menetapkan `jtict_meeting_id`.
- Syarat: ada Mesyuarat JTICT yang sedang dibuka. Pada 16/11/2026 **tiada**, kerana tempoh Mesyuarat 2 tutup 06/11. Penghantaran semula disimpan tetapi kekal Tidak Lengkap sehingga mesyuarat seterusnya dibuka.
- **Risiko:** jika Urus Setia menetapkan ID 2/3 **Lengkap**, permohonan menjadi COMPLETE tanpa mesyuarat. Polisi Pra JTICT menyekat semua keputusan, dan status COMPLETE tiada laluan lain, jadi permohonan **tersangkut kekal** dalam Perlu Semakan Manual. Cadangan prosedur dan kawalan: O5.

---

## 7. Pengesahan fungsi Salin Permohonan

**Status: TIDAK WUJUD.** Tiada service, Livewire, route, polisi atau butang. Yang ada hanyalah lajur dan relation daripada PP4.1B.

Penemuan kod yang mempengaruhi reka bentuk:
1. **Master data tidak aktif:** borang membenarkan nilai master data **sedia ada** walaupun tidak aktif (`Forms/ApplicationForm::masterRule($keep)` dan `ApplicationForm::masterOptions($keep)`). Jika salinan mengekalkan ID tidak aktif, borang akan menerimanya, dan ini bercanggah dengan spesifikasi "perlu dipilih semula". Service Salin mesti **mengosongkan** rujukan master data tidak aktif atau dipadam. Semua medan berkaitan nullable, jadi tiada migrasi diperlukan.
2. **Nombor rujukan:** `nextReferenceNumber()` ialah kaedah *private* dalam `ApplicationSubmissionService`. Fungsi Salin memerlukannya (salinan bermula sebagai Draf `SPPICT/{tahun}/DRAFT-nnnn`). Kaedah ini perlu dijadikan perkhidmatan bersama tanpa mengubah tingkah laku.
3. **Pegawai:** `User::eligibleOfficersFor($agencyId)` (aktif, agensi sama, bukan superadmin) boleh digunakan untuk menapis pegawai aktif yang layak disalin. `started_at` dibiarkan NULL dan ditetapkan semasa dihantar, seperti draf biasa.
4. **Hak pindaan (keputusan 05/11/2026):** salinan ialah Draf baharu. Pendaftar salinan (pengguna yang menyalin) boleh menyuntingnya semasa Draf.
5. **Satu salinan:** tiada indeks unik. Perlu `lockForUpdate` pada permohonan asal dan semakan `copies()->exists()` dalam transaksi. Indeks unik pilihan memerlukan migrasi tambahan dan kelulusan (O8).

---

## 8. Pelan PP4.3A hingga PP4.3D

### PP4.3A – Backend dan antara muka keputusan JTICT (termasuk PP42B-01)

**Kebergantungan:** keputusan O1–O4; garis dasar working tree di-commit oleh pemilik (disyorkan).

**Skop:**
1. **Model `Meeting`:** kaedah baharu `hasTakenPlace()` (`meeting_date ≤ hari ini`) dan `isValidForPreJtictAcceptance()` (aktif, jenis JTICT, tidak dipadam, `meeting_date ≥ hari ini`).
2. **PP42B-01:** `validatePreJtict()` menolak **Diterima** jika mesyuarat tidak sah. Modal Pra JTICT menyahdayakan butang Diterima dengan penjelasan, dan `PreJtictList` menanda "Mesyuarat tidak sah".
3. **Service JTICT:**
   - syarat 11 (mesyuarat ≥ tarikh Pra JTICT Diterima);
   - semakan eksplisit tiada keputusan JTICT terdahulu;
   - permohonan dipadam ditolak dengan `AuthorizationException`, bukan 404.
4. **Polisi:** `decideJtict()` ditambah syarat mesyuarat (O3); ability baharu `viewJtictQueue()`.
5. **Model `Application`:** skop `awaitingJtictDecision()` (IN_JTICT, Diterima, mesyuarat sah dan telah berlangsung), `scheduledForJtict()` (mesyuarat belum berlangsung) dan `needsManualJtictReview()` (mesyuarat tiada, dipadam atau sebelum tarikh Diterima, contohnya ID 6). `statusDisplayLabel()` bagi IN_JTICT dibezakan: menunggu mesyuarat / sedia diputuskan / perlu semakan manual.
6. **Livewire `JtictList`** (`/urus-setia/jtict`), dengan tiga bahagian: Sedia Diputuskan, Menunggu Mesyuarat dan Perlu Semakan Manual. Penapis mengikut mesyuarat; tiada tindakan pukal.
7. **`ViewApplication`:** kad "Keputusan Mesyuarat JTICT" (butang Lulus / Tidak Lulus / Kaji Semula) dan modal (tarikh wajib tanpa nilai lalai, `min` = tarikh mesyuarat, `max` = hari ini, ulasan wajib). Pengendalian ralat dan pengesanan rekod pendua mengikut corak Pra JTICT.
8. **Menu "JTICT"** dan pautan kad "Keputusan JTICT" di papan pemuka Urus Setia ke senarai baharu (kad sedia ada mengira IN_JTICT).

**Fail dijangka:**
- `app/Services/ApplicationDecisionService.php`
- `app/Policies/ApplicationPolicy.php`
- `app/Models/Meeting.php`
- `app/Models/Application.php`
- `app/Livewire/JtictList.php` (baharu)
- `app/Livewire/ViewApplication.php`
- `resources/views/livewire/jtict-list.blade.php` (baharu)
- `resources/views/livewire/view-application.blade.php`
- `resources/views/livewire/pre-jtict-list.blade.php`
- `routes/web.php`
- `resources/views/livewire/layout/navigation.blade.php` ⚠ fail pemilik
- `resources/views/dashboard/urus-setia.blade.php` ⚠ fail pemilik

**Ujian:**
- `tests/Feature/JtictDecisionTest.php` (baharu): Lulus, Tidak Lulus, Kaji Semula, ulasan, tarikh, mesyuarat belum berlangsung, mesyuarat sebelum Diterima, mesyuarat dipadam, permohonan dipadam, keputusan berganda atau serentak, kawalan akses (termasuk Urus Setia + pengurusan), senarai dan bahagian manual, label;
- `tests/Feature/PreJtictMeetingValidityTest.php` (baharu, PP42B-01);
- kemas kini berjustifikasi ujian sedia ada (Seksyen 11).

**Risiko:**
- fixture 8 ujian perlu disusun semula (R1);
- 2 ujian pengawal PP4.1B/PP4.2 perlu dikemas kini (R2);
- fail pemilik yang belum di-commit akan disentuh, jadi tambahan hendaklah minimum;
- tiada pengesahan MySQL bagi `lockForUpdate` (PP42B-02).

### PP4.3B – Paparan keputusan dan sejarah kepada pemohon

**Kebergantungan:** PP4.3A.

**Skop:**
1. Sejarah keputusan dalam halaman butiran merangkumi **Pra JTICT dan JTICT**, sama ada sebagai kronologi tunggal atau dua bahagian (O9). Nama perekod dipaparkan kepada Urus Setia sahaja.
2. Notis keputusan JTICT kepada pemohon dan pegawai, dengan warna mengikut keputusan:
   - Lulus;
   - Tidak Lulus;
   - Kaji Semula, dengan arahan penyalinan (pautan Salin selepas PP4.3C).
3. Semakan kerja pemilik yang sedia ada:
   - lajur "Keputusan" dalam senarai pemohon;
   - kad "Permohonan Projek Lulus" di papan pemuka pemohon.

   Sahkan semuanya selaras dengan rekod `application_decisions`, tanpa mengubah struktur papan pemuka.
4. Tiada penciptaan rekod Projek (O6).

**Fail dijangka:**
- `app/Livewire/ViewApplication.php`
- `resources/views/livewire/view-application.blade.php`
- semakan `resources/views/livewire/application-list.blade.php` ⚠ dan `resources/views/dashboard/pengguna.blade.php`

**Ujian:** `tests/Feature/JtictDecisionDisplayTest.php` (baharu), meliputi paparan pemohon, pegawai dan Urus Setia; nama perekod disembunyikan; tiada edit atau padam; label CLOSED mengikut peringkat.

**Risiko:** rendah, terutamanya paparan. Fail pemilik terlibat.

### PP4.3C – Fungsi Salin Permohonan

**Kebergantungan:** PP4.3A (keputusan Kaji Semula wujud); keputusan O7, O8 dan O10.

**Skop:**
1. **`ApplicationCopyService::copy(Application $source, User $actor): Application`** dalam satu transaksi:
   - `lockForUpdate` permohonan asal;
   - semak kelayakan;
   - cipta permohonan Draf baharu dengan UUID, ID dan nombor rujukan Draf baharu;
   - `applicant_id` = pengguna yang menyalin (O7) dan `agency_id` = agensi asal;
   - `copied_from_application_id` melalui `forceFill`;
   - salin Fasa 1–2 (tajuk, kategori, objektif, skop, perolehan, kos, tempoh, kod hasil) dan Fasa 3 (`application_details`);
   - kosongkan master data tidak aktif atau dipadam;
   - **jangan** salin dokumen, mesyuarat, ulasan semakan, keputusan atau `jtict_decision`;
   - salin pegawai aktif yang layak (`eligibleOfficersFor`), dengan `started_at` NULL.
2. **Polisi `copy()`:**
   - permohonan CLOSED;
   - keputusan JTICT terkini Kaji Semula;
   - pengguna `canUseSystem()`;
   - pengguna dalam agensi permohonan;
   - pengguna ialah pendaftar **atau** pegawai aktif;
   - belum ada salinan (O8);
   - bukan superadmin/pengurusan.
3. **Pengekstrakan penjana nombor rujukan** daripada `ApplicationSubmissionService` ke kelas bersama, tanpa mengubah format.
4. **UI:**
   - butang "Salin Permohonan" dengan pengesahan pada halaman butiran;
   - pautan dua hala "Disalin daripada …" / "Salinan: …";
   - selepas menyalin, pengguna dibawa ke borang Draf salinan. Borang memaparkan amaran untuk master data yang perlu dipilih semula dan dokumen yang perlu dimuat naik semula.

**Fail dijangka:**
- `app/Services/ApplicationCopyService.php` (baharu)
- `app/Support/ReferenceNumber.php` (baharu, atau nama setara)
- `app/Services/ApplicationSubmissionService.php` ⚠ fail mengandungi perubahan pemilik
- `app/Policies/ApplicationPolicy.php`
- `app/Livewire/ViewApplication.php`
- `resources/views/livewire/view-application.blade.php`
- `resources/views/livewire/application-form.blade.php` (amaran salinan) ⚠

**Ujian:** `tests/Feature/ApplicationCopyTest.php` (baharu), meliputi:
- hanya Kaji Semula layak;
- ID, UUID dan rujukan baharu;
- permohonan asal tidak berubah (hash medan);
- hubungan `copied_from`;
- Fasa 1–3 disalin, dokumen tidak disalin;
- pegawai layak sahaja disalin;
- master data tidak aktif dikosongkan;
- satu salinan (termasuk cubaan serentak);
- kawalan akses;
- borang memaksa pemilihan semula.

**Risiko:**
- menyentuh `ApplicationSubmissionService`, iaitu kod PP1/PP3 dan fail pemilik;
- semakan "satu salinan" tanpa indeks unik bergantung pada kunci baris, yang belum disahkan pada MySQL.

### PP4.3D – Notifikasi e-mel bagi enam jenis keputusan

**Kebergantungan:** PP4.3A (keputusan JTICT); PP4.3C untuk pautan Salin dalam e-mel Kaji Semula (pilihan); keputusan O9.

**Skop:**
1. **`ApplicationDecisionRecorded`** (satu kelas, kandungan mengikut keputusan):
   - `ShouldQueue` dan `afterCommit()`, mengikut corak `ApplicationMarkedIncomplete`;
   - subjek, contohnya `[SPPICT] Keputusan Pra JTICT – Diterima: {rujukan}`.
2. **Enam jenis:**
   - Pra JTICT: Diterima, Ditolak dengan Pindaan (dengan ulasan dan arahan pindaan), Ditolak;
   - JTICT: Lulus, Tidak Lulus, Kaji Semula (dengan arahan Salin).
3. **Penghantaran:** dipanggil selepas `decide()` berjaya, di luar transaksi. Kegagalan dilog dan tidak menjejaskan keputusan.
4. **Penerima:** mengikut `ApplicationReviewService::incompleteRecipients()` (pemohon + Pegawai Utama, nyahduplikasi e-mel, langkau alamat tidak sah). Penerima tambahan memerlukan keputusan (O9).
5. **Tiada perubahan** pada SMTP, queue atau Mailtrap.

**Fail dijangka:**
- `app/Notifications/ApplicationDecisionRecorded.php` (baharu)
- `app/Services/ApplicationDecisionService.php`, atau pemanggil Livewire (keputusan reka bentuk: service disyorkan, supaya semua laluan menghantar e-mel)
- ekstrak penerima bersama daripada `ApplicationReviewService` ⚠ (PP2; perubahan minimum, atau salin logik dengan rujukan)

**Ujian:**
- `tests/Feature/DecisionNotificationTest.php` (baharu), menggunakan `Notification::fake()` untuk enam jenis: penerima, nyahduplikasi, alamat tidak sah, tiada e-mel apabila keputusan gagal, kegagalan e-mel tidak membatalkan keputusan;
- ujian Mailtrap oleh pemilik (manual).

**Risiko:**
- `QUEUE_CONNECTION=database` memerlukan *queue worker* berjalan;
- pengujian SMTP sebenar memerlukan persekitaran yang diluluskan.

---

## 9. Senarai fail yang dijangka terlibat

| Fail | 4.3A | 4.3B | 4.3C | 4.3D | Catatan |
|---|---|---|---|---|---|
| `app/Services/ApplicationDecisionService.php` | ✔ | | | ✔ | Kod PP4 |
| `app/Policies/ApplicationPolicy.php` | ✔ | | ✔ | | ⚠ mengandungi perubahan belum di-commit (PP4.2, peraturan 05/11) |
| `app/Models/Meeting.php` | ✔ | | | | Tambahan kaedah sahaja; logik modul Mesyuarat tidak diubah |
| `app/Models/Application.php` | ✔ | ✔ | | | ⚠ belum di-commit |
| `app/Livewire/JtictList.php` + view | ✔ | | | | Baharu |
| `app/Livewire/ViewApplication.php` + view | ✔ | ✔ | ✔ | | ⚠ belum di-commit |
| `app/Livewire/PreJtictList.php` + view | ✔ | | | | PP42B-01 |
| `routes/web.php` | ✔ | | | | ⚠ belum di-commit |
| `resources/views/livewire/layout/navigation.blade.php` | ✔ | | | | ⚠ fail pemilik; satu baris menu |
| `resources/views/dashboard/urus-setia.blade.php` | ✔ | | | | ⚠ fail pemilik; pautan kad |
| `resources/views/livewire/application-list.blade.php` | | ✔ | | | ⚠ fail pemilik; semakan sahaja jika boleh |
| `resources/views/dashboard/pengguna.blade.php` | | ✔ | | | Semakan |
| `app/Services/ApplicationCopyService.php` | | | ✔ | | Baharu |
| `app/Support/ReferenceNumber.php` (cadangan nama) | | | ✔ | | Baharu |
| `app/Services/ApplicationSubmissionService.php` | | | ✔ | | ⚠ fail pemilik; pengekstrakan nombor rujukan sahaja |
| `resources/views/livewire/application-form.blade.php` | | | ✔ | | ⚠ belum di-commit; amaran salinan |
| `app/Notifications/ApplicationDecisionRecorded.php` | | | | ✔ | Baharu |
| `app/Services/ApplicationReviewService.php` | | | | (✔) | PP2; hanya jika penerima diekstrak (perlu kelulusan) |
| Ujian baharu / dikemas kini | ✔ | ✔ | ✔ | ✔ | Seksyen 11 |
| Migrasi | — | — | (pilihan) | — | Indeks unik `copied_from_application_id` hanya dengan kelulusan (O8) |

---

## 10. Senarai ujian penerimaan (UAT, dalam persekitaran ujian berasingan)

| ID | Subfasa | Senario | Hasil dijangka |
|---|---|---|---|
| UAT-43-01 | A (PP42B-01) | Pra JTICT Diterima bagi permohonan dengan mesyuarat tidak aktif atau telah berlalu | Butang Diterima dinyahdayakan; cubaan backend ditolak; Pindaan dan Ditolak masih boleh |
| UAT-43-02 | A | Senarai JTICT memaparkan tiga bahagian | IN_JTICT dibahagikan mengikut keadaan mesyuarat |
| UAT-43-03 | A | Rekod Lulus selepas mesyuarat berlangsung | CLOSED; label "Selesai – Lulus (JTICT)"; sejarah direkod |
| UAT-43-04 | A | Rekod Tidak Lulus / Kaji Semula | CLOSED; label mengikut keputusan |
| UAT-43-05 | A | Ulasan kosong atau < 10 aksara | Ditolak |
| UAT-43-06 | A | Tarikh sebelum tarikh mesyuarat, atau tarikh masa depan | Ditolak |
| UAT-43-07 | A | Mesyuarat belum berlangsung | Tiada butang keputusan; berada dalam "Menunggu Mesyuarat" |
| UAT-43-08 | A | Kes seperti ID 6 (mesyuarat sebelum tarikh Diterima) | Dalam "Perlu Semakan Manual"; tiada butang; backend menolak |
| UAT-43-09 | A | Superadmin, pengurusan, pengguna_biasa, Urus Setia tidak aktif, Urus Setia + pengurusan | Tiada akses kepada senarai atau keputusan |
| UAT-43-10 | A | Dua Urus Setia memutuskan serentak | Satu rekod sahaja; pengguna kedua menerima mesej |
| UAT-43-11 | B | Pemohon dan pegawai melihat keputusan JTICT | Keputusan, tarikh dan ulasan dipaparkan; nama perekod disembunyikan |
| UAT-43-12 | B | Kad "Permohonan Projek Lulus" | Bertambah bagi Lulus sahaja |
| UAT-43-13 | C | Salin permohonan Kaji Semula | Draf baharu (rujukan Draf baharu); permohonan asal tidak berubah |
| UAT-43-14 | C | Salin permohonan Lulus / Tidak Lulus | Butang tiada; backend menolak |
| UAT-43-15 | C | Salin kali kedua | Ditolak |
| UAT-43-16 | C | Salinan dengan master data tidak aktif | Medan kosong dan wajib dipilih semula |
| UAT-43-17 | C | Dokumen dan pegawai | Dokumen tidak disalin; hanya pegawai aktif yang layak disalin |
| UAT-43-18 | C | Pengguna agensi lain atau pegawai yang telah diganti cuba menyalin | Ditolak |
| UAT-43-19 | D | Enam jenis e-mel (Mailtrap) | Subjek, kandungan dan pautan betul; penerima mengikut O9 |
| UAT-43-20 | D | Queue gagal | Keputusan kekal; kegagalan dilog |

---

## 11. Risiko dan kebergantungan

| # | Risiko / kebergantungan | Kesan | Mitigasi |
|---|---|---|---|
| R1 | **PP42B-01 bercanggah dengan fixture ujian sedia ada.** Lapan ujian menetapkan mesyuarat ke masa lalu sebelum Pra JTICT Diterima. Tujuh daripadanya dalam `ApplicationDecisionTest`: `test_jtict_decision_requires_in_jtict_and_accepted_pre_jtict_decision`, `test_each_decision_sets_expected_status`, `test_remarks_are_required_according_to_owner_decision`, `test_decision_date_cannot_be_in_the_future`, `test_jtict_date_not_before_meeting_date_and_meeting_must_match`, `test_jtict_decision_refused_when_meeting_is_deleted` dan `test_decisions_do_not_touch_legacy_fields_other_records_email_or_copies`. Yang kelapan ialah `PreJtictDecisionTest::test_closed_status_label_distinguishes_decision_stage`. | Ujian ini akan gagal selepas PP42B-01 | Susun semula fixture: terima semasa mesyuarat akan datang, kemudian `travelTo` selepas tarikh mesyuarat. Assertion asal dikekalkan dan justifikasi dilaporkan. Ini bukan untuk "melepaskan" ujian; dasar ujian itu sendiri telah berubah. |
| R2 | **Dua ujian pengawal menyekat UI JTICT:** `ApplicationDecisionTest::test_only_pre_jtict_decision_route_and_ui_are_exposed` (tiada `decideJtict` / `DecisionStage::Jtict` dalam Livewire) dan `PreJtictDecisionTest::test_no_edit_or_delete_and_no_jtict_decision_through_pp42` (senarai putih kaedah awam `ViewApplication`) | Gagal apabila UI JTICT ditambah | Kemas kini kepada pengawal baharu: senarai putih kaedah ditambah kaedah JTICT; tiada kaedah edit/padam; keputusan JTICT tidak boleh melalui borang Pra JTICT dan sebaliknya. Justifikasi dilaporkan. |
| R2b | **Dua ujian menegaskan tiada e-mel keputusan:** `ApplicationDecisionTest::test_decisions_do_not_touch_legacy_fields_other_records_email_or_copies` dan `PreJtictDecisionTest::test_decision_does_not_touch_legacy_fields_other_applications_or_send_email` | Gagal dalam PP4.3D | Tukar kepada assertion e-mel yang dijangka (penerima dan jenis), sambil mengekalkan semakan medan legacy dan rekod lain |
| R3 | Working tree besar belum di-commit (56 entri, termasuk kerja pemilik) | Sukar memisahkan perubahan PP4.3; risiko konflik | Pemilik commit garis dasar sebelum PP4.3A (disyorkan) |
| R4 | MySQL belum diuji (PP42B-02) | `lockForUpdate`, semakan satu salinan dan jenis tarikh belum dibuktikan | Sediakan DB ujian MySQL sebelum produksi |
| R5 | Tiada proses rasmi tukar mesyuarat untuk IN_JTICT | ID 6 dan kes serupa tersangkut | Keputusan O4 |
| R6 | COMPLETE tanpa mesyuarat ialah jalan buntu | ID 2/3 tersangkut jika ditetapkan Lengkap | Keputusan O5 |
| R7 | `is_active` diurus secara manual | Definisi "sah" yang salah boleh menyekat keputusan JTICT | Keputusan O3 |
| R8 | Salin menyentuh `ApplicationSubmissionService` (PP1/PP3) | Regresi nombor rujukan | Pengekstrakan tanpa ubah format + ujian regresi rujukan |
| R9 | E-mel memerlukan queue worker | E-mel tidak dihantar jika worker tidak berjalan | Dokumentasikan; ujian Mailtrap oleh pemilik |
| R10 | Kegagalan rawak ujian `.docx` (PP42B-03) | Hasil suite tidak stabil | Pantau; bukan dalam skop PP4.3 |

---

## 12. Perkara yang memerlukan keputusan pemilik sistem

| ID | Perkara | Cadangan | Diperlukan sebelum |
|---|---|---|---|
| **O1** | Adakah Pra JTICT Diterima dibenarkan **pada hari mesyuarat itu sendiri**? | Dibenarkan (`meeting_date ≥ hari ini`) | PP4.3A |
| **O2** | Bagi permohonan COMPLETE yang mesyuaratnya tidak sah, satu-satunya "proses rasmi" sedia ada untuk mendapat mesyuarat baharu ialah **Ditolak dengan Pindaan**. Pemohon kemudian menghantar semula dan memilih mesyuarat yang dibuka. Adakah laluan ini diterima? | Terima, dengan teks panduan dalam modal | PP4.3A |
| **O3** | Definisi "mesyuarat sah" bagi **JTICT**: adakah `is_active` diwajibkan? | **Tidak**: wujud, tidak dipadam, jenis JTICT, telah berlangsung dan ≥ tarikh Pra JTICT Diterima | PP4.3A |
| **O4** | **Permohonan ID 6:** tiada proses rasmi untuk menukar mesyuarat bagi IN_JTICT. Pilihan: (a) bina fungsi rasmi "Tukar Mesyuarat JTICT" (berasingan, bukan PP4.3); (b) pembetulan data sekali sahaja yang diluluskan dan diaudit; (c) kekal dalam Perlu Semakan Manual sehingga diputuskan. Adakah syarat 11 (mesyuarat ≥ tarikh Diterima) diluluskan? | (c) dalam PP4.3A, dan keputusan (a) atau (b) kemudian; luluskan syarat 11 | PP4.3A |
| **O5** | **Permohonan ID 2/3:** sahkan prosedur Urus Setia, iaitu PP2 **Tidak Lengkap**, bukan Lengkap, supaya penghantaran semula menetapkan mesyuarat. Adakah PP2 perlu disekat daripada menetapkan Lengkap jika tiada mesyuarat? (Ini perubahan PP2.) | Prosedur bertulis + sekatan PP2 kecil (dengan kelulusan) | Sebelum ID 2/3 disemak |
| **O6** | Adakah keputusan **Lulus** mencipta rekod Projek (`projects`)? | Tidak dalam PP4.3 (modul Kemajuan Projek berasingan) | PP4.3B |
| **O7** | Siapa **pendaftar** salinan, dan adakah Urus Setia yang mendaftar bagi pihak agensi (berlainan agensi) boleh menyalin? | `applicant_id` = penyalin; Urus Setia bagi pihak agensi **tidak** (spesifikasi: dalam agensi) | PP4.3C |
| **O8** | "Satu salinan sahaja": jika salinan Draf dipadam (soft-delete), adakah salinan baharu dibenarkan? Adakah indeks unik (migrasi tambahan) diluluskan? | Tidak dibenarkan (kira `withTrashed`); kawalan kod tanpa migrasi | PP4.3C |
| **O9** | Penerima e-mel keputusan: pemohon dan Pegawai Utama sahaja (corak PP2), atau termasuk Pegawai Kedua dan Urus Setia? Sejarah keputusan dalam satu kronologi atau dua bahagian? | Pemohon + Pegawai Utama; satu kronologi | PP4.3B / D |
| **O10** | Adakah had masa untuk menyalin permohonan Kaji Semula? | Tiada had | PP4.3C |
| **O11** | Anomali Mesyuarat 1 (tarikh buka > tarikh tutup): adakah pengesahan modul Mesyuarat perlu diperbaiki? | Fasa berasingan (bukan PP4.3) | — |
| **O12** | Commit garis dasar working tree sebelum PP4.3A | Disyorkan | PP4.3A |

---

## 13. Cadangan urutan pembangunan

1. **Prasyarat:**
   - pemilik membuat keputusan O1–O4 dan O12;
   - DB ujian MySQL (PP42B-02) disyorkan secara selari.
2. **PP4.3A:**
   1. PP42B-01 (sekatan Pra JTICT Diterima) + ujian + penyusunan semula fixture R1;
   2. backend JTICT (polisi, service dan skop);
   3. senarai JTICT dan UI keputusan;
   4. ujian penuh SQLite.
3. **PP4.3B:** paparan keputusan dan sejarah kepada pemohon (bergantung pada 4.3A).
4. **PP4.3D:** e-mel enam jenis keputusan. Boleh dimulakan selepas 4.3A, selari dengan 4.3B. Pautan Salin dalam e-mel Kaji Semula ditambah selepas 4.3C.
5. **PP4.3C:** Salin Permohonan (bergantung pada 4.3A dan keputusan O7, O8, O10). Ia diletakkan terakhir kerana menyentuh kod PP1/PP3 (`ApplicationSubmissionService`) dan memerlukan paling banyak keputusan dasar.
6. **Selepas setiap subfasa:** laporan pelaksanaan, suite penuh SQLite, dan pengesahan MySQL serta UAT oleh pemilik dalam persekitaran ujian.

---

## Lampiran – Kawalan keselamatan fasa ini

| Kawalan | Status |
|---|---|
| Tiada perubahan kod, migrasi, konfigurasi atau Git | Dipatuhi. Hash `git diff` sebelum audit `b72a03c2…6140`; tiada fail diubah. Satu-satunya fail baharu ialah laporan ini. |
| Pangkalan data baca sahaja | Dipatuhi. Skrip menggunakan pengawal `SELECT`/`SHOW` sahaja. |
| Ujian tidak menyentuh `sppictv2_db` | Dipatuhi. Probe masa larian: `sqlite` / `:memory:`. Suite penuh 504/504 lulus. |
| Tiada UAT pada `sppictv2_db` | Dipatuhi |
