# Laporan Pelaksanaan Fasa PP3: Permohonan bagi Pihak Agensi

**Sistem:** SPPICT v2.1 (Laravel 13, Livewire 3, MySQL)
**Tarikh:** 29 Oktober 2026
**Skop:** Urus Setia memilih agensi sasaran dan mengemukakan permohonan projek bagi pihak agensi tersebut (isu K2, Laporan PP0)
**Status:** Pelaksanaan dan ujian automatik **selesai**. UAT manual telah dilaksanakan pada 30/10/2026 (Seksyen 11). Langkah 9 dan 12 gagal, dibetulkan (Seksyen 14), dan **lulus UAT semula** pada hari yang sama. Lihat `Laporan UAT Fasa PP3 - Permohonan bagi Pihak Agensi.md`.

---

## 1. Ringkasan Pelaksanaan

1. Urus Setia yang membuka **Permohonan Baharu** kini mesti **memilih dan mengesahkan Jabatan/Agensi sasaran** sebelum borang lima fasa dipaparkan. Senarai pilihan mengandungi agensi aktif sahaja.
2. Permohonan disimpan dengan `agency_id` = **agensi sasaran** dan `applicant_id` = **Urus Setia yang mendaftar**. Kedua-duanya medan sedia ada.
3. **Pegawai Bertanggungjawab** dipilih daripada pegawai agensi sasaran sahaja, dan ini disahkan di pelayan.
4. Agensi **dikunci** selepas rekod disimpan buat kali pertama. Sebelum itu, Urus Setia boleh menukarnya; pilihan pegawai akan dikosongkan.
5. **Peraturan Draf dan penghantaran tidak berubah.** Draf boleh disimpan tanpa mesyuarat aktif. Penghantaran memerlukan Mesyuarat JTICT aktif, dan status selepas penghantaran ialah `SUBMITTED`. Permohonan kemudian masuk ke senarai semakan Urus Setia sedia ada.
6. Senarai Urus Setia mendapat pilihan **"Draf Saya"**, yang hanya memaparkan draf milik Urus Setia itu sendiri, supaya draf bagi pihak agensi boleh dibuka semula. Kod agensi juga dipaparkan dalam senarai.
7. Halaman butiran memaparkan **Jabatan/Agensi** dan **Didaftarkan oleh** secara berasingan.
8. **Pengguna biasa tidak terjejas.** Agensi diambil daripada akaun pengguna, tiada pemilih agensi dipaparkan, dan manipulasi payload ditolak.
9. **Tiada migration**, tiada perubahan pada `application_reviews`, tiada perubahan `.env` dan tiada operasi Git.
10. **Ujian automatik:** **427 lulus, 0 gagal, 2 risky**. Baseline PP2 ialah 413 / 0 / 2; ujian bertambah sebanyak 14, iaitu ujian PP3 baharu.

## 2. Pengesahan Prasyarat (SMTP dan UAT PP2)

| Prasyarat | Status | Bukti |
|---|---|---|
| Ujian SMTP sebenar | ✅ Disahkan pengguna | `Laporan Pengujian SMTP dan Notifikasi Fasa PP2 - Mailtrap.md`; `failed_jobs` = 0 |
| UAT PP2 | ✅ Disahkan pengguna | `Laporan UAT Fasa PP2 - Semakan Kelengkapan Permohonan.md`. Kitaran Tidak Lengkap → hantar semula → Lengkap disahkan dalam DB (4 rekod `application_reviews`, rantaian sejarah konsisten) |
| Tiada regresi PP2 | ✅ | Semua ujian PP2 lulus. Satu ujian dikemas kini mengikut skop PP3 (Seksyen 10.3). Kitaran PP2 turut diuji pada permohonan bagi pihak agensi |

Prasyarat dipenuhi sebelum perubahan kod dibuat. `.env`, kelayakan SMTP dan konfigurasi queue tidak disentuh.

## 3. Keadaan Asal

Keadaan ini disahkan semula daripada kod semasa, bukan hanya berdasarkan laporan PP0.

| Perkara | Keadaan sebelum PP3 |
|---|---|
| Cipta permohonan | `ApplicationSubmissionService::resolveApplication()` menetapkan `agency_id` = agensi akaun pengguna (`applicantAgencyId()`) dan `applicant_id` = pengguna log masuk |
| Pilihan agensi | Tiada. Urus Setia memohon di bawah agensinya sendiri |
| Calon pegawai | `ApplicationForm::officerCandidates()` menggunakan agensi pengguna log masuk untuk rekod baharu |
| Polisi `create` (UM7A) | `pengguna_biasa` dan `pentadbir_urus_setia`, kecuali jika turut memegang `superadmin` atau `pengurusan` |
| Polisi `update` | `applicant_id` sahaja, dan hanya semasa Draf atau Tidak Lengkap |
| Polisi `view` | Pemohon, Urus Setia, pengguna agensi yang sama, atau pegawai aktif |
| Senarai Urus Setia | Satu status pada satu masa. Draf tidak dipaparkan langsung (PP2) |
| Struktur agensi | Jadual `agencies` (`id`, `code`, `name`, `category`, `is_active`, soft delete). `applications.agency_id` dan `users.agency_id` ialah foreign key. Skop `Agency::active()` = `is_active` true; soft-deleted dikecualikan |

## 4. Pemilihan dan Pengesahan Agensi Sasaran

**Aliran Urus Setia (permohonan baharu):**

1. Panel **"Permohonan bagi Pihak Agensi"** dipaparkan. Borang lima fasa, butang Seterusnya dan butang Simpan Draf disembunyikan.
2. Pilihan agensi ialah `Agency::active()` (aktif dan tidak dipadam), disusun mengikut nama.
3. Butang **Sahkan Agensi & Teruskan** memanggil `confirmAgency()`:
   - `authorize('createOnBehalf')`;
   - disekat jika rekod sudah wujud;
   - `targetAgencyId` disahkan sebagai `required|integer|exists:agencies,id` dengan syarat `is_active = 1` dan `deleted_at IS NULL`.
4. Agensi yang disahkan disimpan dalam `confirmedAgencyId`, iaitu harta Livewire **`#[Locked]`** yang tidak boleh diubah dari pelayar.
5. Banner **"Permohonan bagi pihak: <Nama> (<Kod>)"** dipaparkan sepanjang lima fasa. Butang **Tukar Agensi** tersedia sehingga rekod disimpan.

**Pertahanan berlapis di pelayan:**

| Lapisan | Kawalan |
|---|---|
| Komponen | `ensureAgencyConfirmed()` dipanggil dalam `nextStep`, `saveDraft` dan `submitApplication`. Tanpa agensi yang disahkan, ralat `targetAgencyId` dipulangkan dan tiada rekod dicipta |
| Servis | `resolveApplication()`: jika pengguna `can('createOnBehalf')`, agensi sasaran **disahkan semula** (`targetAgencyId()`: wujud, aktif, tidak dipadam). Jika agensi dinyahaktifkan selepas disahkan, penyimpanan ditolak |
| Servis | Pengguna tanpa kebenaran `createOnBehalf` yang menghantar agensi sasaran akan menerima `AuthorizationException` |
| Servis | Rekod sedia ada dengan agensi sasaran dalam payload akan menerima `AuthorizationException`. Agensi tidak boleh diubah selepas didaftarkan |

## 5. Semantik Pemohon, Pendaftar dan Agensi Sasaran

Kegunaan `applicant_id` diperiksa dalam polisi, paparan, notifikasi, audit dan aliran kerja. Medan ini **sentiasa** bermaksud pengguna yang mendaftar rekod dan bertanggungjawab ke atasnya:

| Kegunaan sedia ada | Makna |
|---|---|
| `resolveApplication()` | Sentiasa ditetapkan kepada pengguna log masuk semasa rekod dicipta |
| `ApplicationPolicy::update` | Hanya `applicant_id` boleh meminda (Draf / Tidak Lengkap) |
| `ApplicationReviewService` (PP2) | Penerima e-mel "Pemohon" |
| `ApplicationList` (pengguna biasa) | Rekod "milik saya" |
| `ApplicationReview::actorRoleLabel` | Penghantaran semula oleh pendaftar |

**Keputusan reka bentuk:** permohonan bagi pihak agensi menggunakan semantik yang sama. **Tiada medan baharu dan tiada perubahan makna.**

| Konsep | Medan | Nilai bagi permohonan Urus Setia |
|---|---|---|
| Pengguna log masuk | (sesi) | Urus Setia |
| Pendaftar / penghantar rekod | `applications.applicant_id` | Urus Setia |
| Agensi sasaran | `applications.agency_id` (FK `agencies`) | Agensi yang dipilih |
| Pegawai Utama / Kedua | `application_officers` (aktif) | Pegawai agensi sasaran |
| Pelaku setiap tindakan | `audits.user_id` (owen-it), `application_reviews.user_id` | Pengguna sebenar yang bertindak |

Kesan semantik ini:

- Urus Setia pendaftar ialah pihak yang meminda dan menghantar semula apabila permohonan Tidak Lengkap. Ini mengikut polisi `update` sedia ada.
- E-mel Tidak Lengkap dihantar kepada Urus Setia pendaftar dan Pegawai Utama agensi sasaran.
- Rekod lama tidak diubah. Ia kekal dengan `agency_id` dan `applicant_id` asal, termasuk draf Urus Setia yang dicipta sebelum PP3 di bawah agensinya sendiri.

## 6. Fail Ditambah atau Diubah

| Fail | Jenis | Perubahan dan sebab |
|---|---|---|
| `app/Policies/ApplicationPolicy.php` | Diubah | Tambah `createOnBehalf()` = `create()` dan peranan `pentadbir_urus_setia`. Polisi lain, termasuk `review`, tidak diubah |
| `app/Services/ApplicationSubmissionService.php` | Diubah | Parameter `?int $targetAgencyId` pada `submit()` dan `saveDraft()`. `resolveApplication()` memilih antara agensi sasaran dan agensi akaun; `targetAgencyId()` baharu; `applicantAgencyId()` menolak agensi sasaran daripada pengguna tanpa kebenaran; agensi rekod sedia ada dikunci |
| `app/Livewire/ApplicationForm.php` | Diubah | `targetAgencyId`, `confirmedAgencyId` (`#[Locked]`), `isOnBehalf`, `agencyOptions`, `applicationAgency`, `confirmAgency()`, `changeAgency()` dan `ensureAgencyConfirmed()`. Calon pegawai diambil daripada agensi permohonan. Pegawai Utama lalai tidak ditetapkan bagi Urus Setia |
| `app/Livewire/ApplicationList.php` | Diubah | Pilihan Draf untuk Urus Setia (draf sendiri sahaja); eager load `agency` |
| `resources/views/livewire/application-form.blade.php` | Diubah | Panel pemilihan agensi, banner agensi dan butang Tukar Agensi |
| `resources/views/livewire/application-list.blade.php` | Diubah | Label "Draf Saya" dan kod agensi di bawah Nama Projek (paparan Urus Setia) |
| `resources/views/livewire/view-application.blade.php` | Diubah | Baris "Jabatan/Agensi" dan "Didaftarkan oleh" |
| `tests/Feature/OnBehalfApplicationTest.php` | **Baharu** | 14 ujian PP3 |
| `tests/Feature/ApplicationReviewTest.php` | Diubah | 1 ujian dikemas kini (Seksyen 10.3) |
| `tests/Feature/PolicyDecisionsTest.php` | Diubah | 1 ujian dikemas kini (Seksyen 10.3) |

Aset CSS dibina semula (`npm run build`). Kelas baharu disahkan wujud dalam fail CSS manifest. Folder `public/build` diabaikan oleh Git.

## 7. Migration dan Pemeliharaan Data

- **Tiada migration dibuat atau dijalankan.** `migrate:status --pending` mengesahkan tiada migration tertunggak.
- Struktur sedia ada (`agency_id` FK dan `applicant_id` FK) sudah mencukupi.
- **Data pembangunan tidak berubah.** Semakan baca sahaja selepas pelaksanaan:

  | Perkara | Nilai |
  |---|---|
  | Permohonan | 5 (DRAFT 1, SUBMITTED 2, COMPLETE 2), sama seperti selepas UAT PP2 |
  | `jobs` / `failed_jobs` | 0 / 0 |

- Tiada seeder atau arahan pemusnahan data dijalankan, dan tiada rekod lama ditetapkan semula.

## 8. Kawalan Akses dan Pengesahan Pelayan

| Peranan | Fungsi bagi pihak agensi | Kawalan |
|---|---|---|
| `pentadbir_urus_setia` | ✅ Dibenarkan | `createOnBehalf`; agensi disahkan dalam komponen dan servis |
| `pengguna_biasa` | ❌ | `confirmAgency()` → 403; `confirmedAgencyId` dikunci; `targetAgencyId` dalam payload diabaikan oleh komponen dan ditolak oleh servis |
| `pengurusan`, `superadmin` | ❌ | `create` sudah menolak peranan ini, termasuk Urus Setia yang turut memegangnya |

Polisi lain tidak diubah:

- `view` dan `update` kekal. Pengguna agensi sasaran boleh melihat permohonan (agensi sama), tetapi hanya Urus Setia pendaftar boleh meminda. Pengguna agensi lain menerima 403.
- **Polisi semakan PP2 dan konflik kepentingan tidak diubah** (Seksyen 12).
- Status tidak boleh ditetapkan melalui payload. Peralihan masih melalui pengawal enum PP1 dalam servis.

## 9. Perubahan Senarai dan Paparan

| Paparan | Perubahan |
|---|---|
| Borang permohonan (Urus Setia, baharu) | Panel pemilihan agensi, kemudian banner "Permohonan bagi pihak:" dan butang Tukar Agensi |
| Borang permohonan (rekod sedia ada / pengguna biasa) | Banner "Jabatan/Agensi Permohonan:" (baca sahaja) |
| Fasa 5 | Calon pegawai daripada agensi permohonan |
| Senarai Urus Setia | Pilihan "Draf Saya" (draf sendiri sahaja) dan kod agensi di bawah Nama Projek |
| Senarai pengguna biasa | Tiada perubahan (logik penapisan, skop dan paparan kekal) |
| Butiran permohonan | "Jabatan/Agensi: Nama (Kod)" dan "Didaftarkan oleh: Nama" |

Nota: nama pendaftar dipaparkan kepada semua yang dibenarkan melihat permohonan. Pegawai agensi sasaran perlu tahu siapa yang mendaftar kerana hanya pendaftar boleh meminda.

## 10. Ujian Automatik

### 10.1 Keputusan

| | Baseline PP2 | Selepas PP3 |
|---|---|---|
| Lulus | 413 | **427** |
| Gagal | 0 | **0** |
| Risky | 2 | **2** |

Bilangan ujian bertambah sebanyak 14, iaitu `OnBehalfApplicationTest` yang baharu. Dua ujian risky yang sama sejak baseline berada dalam Modul Pengurusan Pengguna (tiada assertion) dan tidak berkaitan dengan PP3:

- `SuperadminUserManagementTest::test_sensitive_fields_and_locked_properties_cannot_be_set`
- `UserRoleManagementTest::test_urus_setia_cannot_open_superadmin_self_or_pending_users`

Ujian dijalankan pada SQLite `:memory:`. Ini tidak membuktikan keserasian MySQL, tetapi tiada migration terlibat dalam PP3.

### 10.2 Ujian baharu (`OnBehalfApplicationTest`)

| Ujian | Keperluan prompt |
|---|---|
| `test_urus_setia_must_choose_target_agency_before_filling_form` | 11.1: agensi wajib; tindakan tanpa agensi disekat walaupun UI dipintas |
| `test_only_active_agencies_are_offered_and_accepted` | 11.1: agensi aktif boleh dipilih; agensi tidak aktif, dipadam, ID rekaan atau bukan nombor ditolak |
| `test_confirmed_agency_cannot_be_set_directly_from_browser` | 9: manipulasi harta terkunci |
| `test_draft_is_saved_under_target_agency_without_open_meeting` | 11.1 dan 11.3: Draf tanpa mesyuarat, agensi disimpan, pendaftar betul, audit `user_id` betul |
| `test_officer_candidates_come_from_target_agency_only` | 11.3: pegawai agensi lain ditolak di pelayan |
| `test_changing_agency_before_save_resets_officers` | 6.1: tukar agensi sebelum disimpan |
| `test_submission_requires_open_meeting_and_enters_review_list` | 11.1: tanpa mesyuarat, kekal Draf; dengan mesyuarat, `SUBMITTED`, mesyuarat betul dan masuk senarai semakan |
| `test_urus_setia_drafts_list_shows_only_own_drafts` | 8: "Draf Saya" tanpa mendedahkan draf orang lain; draf dibuka semula dengan agensi betul |
| `test_agency_of_saved_application_cannot_be_changed` | 9: agensi dikunci (komponen dan servis) |
| `test_regular_user_has_no_agency_picker_and_uses_own_agency` | 11.2: agensi sendiri; payload agensi lain diabaikan |
| `test_regular_user_cannot_use_on_behalf_function` | 11.2: 403, harta terkunci, servis menolak |
| `test_only_urus_setia_may_create_on_behalf` | 11.4: peranan tanpa kebenaran |
| `test_target_agency_users_can_view_but_other_agencies_cannot` | 11.4: akses agensi; polisi kemas kini dikekalkan |
| `test_pp2_incomplete_cycle_works_for_on_behalf_application` | 11.3: Tidak Lengkap, e-mel, pindaan, hantar semula, sejarah, pegawai; polisi konflik kepentingan tidak berubah |

### 10.3 Ujian sedia ada yang dikemas kini

Kedua-dua kemas kini ini mencerminkan **keperluan baharu yang diluluskan**, bukan semata-mata untuk meluluskan ujian:

| Ujian | Jangkaan lama | Sebab berubah | Jangkaan baharu |
|---|---|---|---|
| `PolicyDecisionsTest::test_urus_setia_can_save_a_draft_application` (UM7A) | Urus Setia menyimpan draf terus di bawah agensinya sendiri | Matlamat PP3 #2: Urus Setia **mesti** memilih agensi sasaran sebelum mengisi | Tanpa agensi, ralat dan tiada rekod. Selepas memilih agensi, draf disimpan dengan `applicant_id` dan `agency_id` yang betul. Niat UM7A (Urus Setia boleh mendaftar) dikekalkan |
| `ApplicationReviewTest::test_urus_setia_list_defaults_to_submitted_and_filters_by_status` (PP2) | Tiada pilihan Draf langsung untuk Urus Setia | Draf bagi pihak agensi perlu boleh dibuka semula (prompt Seksyen 12, langkah 5) | Pilihan "Draf Saya" wujud. **Draf pemohon lain masih tidak didedahkan**; nilai tidak sah masih kembali kepada Dihantar |

## 11. UAT Manual

**Belum dilaksanakan.** Pengesahan setakat ini hanya melalui ujian automatik. Senarai semak untuk dilaksanakan oleh penguji:

| # | Langkah | Jangkaan | Keputusan |
|---|---|---|---|
| 1 | Log masuk sebagai Urus Setia | Papan pemuka Urus Setia | ☐ |
| 2 | Klik **Permohonan Baharu** | Panel "Permohonan bagi Pihak Agensi"; borang belum dipaparkan | ☐ |
| 3 | Pilih agensi sasaran → **Sahkan Agensi & Teruskan** | Banner "Permohonan bagi pihak: …" | ☐ |
| 4 | Lalui Fasa 1–5 | Banner agensi kekal; Fasa 5 hanya menyenaraikan pegawai agensi sasaran | ☐ |
| 5 | **Simpan Draf**, kemudian buka semula melalui Senarai → "Draf Saya" → Kemaskini | Data kekal; banner "Jabatan/Agensi Permohonan" menunjukkan agensi sasaran; tiada butang Tukar Agensi | ☐ |
| 6 | Cuba hantar tanpa mesyuarat aktif | Butang Hantar dinyahaktifkan; jika dicuba, rekod kekal Draf | ☐ |
| 7 | Dengan Mesyuarat JTICT aktif, hantar permohonan | Berjaya; nombor rujukan rasmi diberikan | ☐ |
| 8 | Senarai Urus Setia (Dihantar) | Permohonan dipaparkan dengan kod agensi sasaran | ☐ |
| 9 | Buka butiran | "Jabatan/Agensi" = agensi sasaran; "Didaftarkan oleh" = Urus Setia; Pegawai Utama daripada agensi sasaran | ☐ |
| 10 | Log masuk sebagai pengguna biasa → Permohonan Baharu | Tiada pemilih agensi; banner menunjukkan agensi sendiri | ☐ |
| 11 | Pengguna biasa simpan draf | `agency_id` = agensi pengguna | ☐ |
| 12 | Urus Setia tandakan permohonan langkah 7 Tidak Lengkap, kemudian pendaftar meminda dan hantar semula | E-mel kepada Urus Setia pendaftar dan Pegawai Utama; sejarah dua rekod; agensi tidak berubah | ☐ |

Nota: UAT akan mencipta rekod permohonan dan sejarah semakan yang kekal dalam DB pembangunan. Gunakan tajuk ujian yang jelas.

**UAT Fasa PP3: Permohonan bagi Pihak Agensi**

- Tarikh: 2026-10-30
- Persekitaran: Pembangunan (sppictv2.1
- Agensi sasaran: Bahagian Dewan Undangan Negeri
- No. rujukan: SPPICT/2026/0006

| # | Langkah | Jangkaan | Keputusan | Catatan |
|---|---|---|---|---|
| 1 | Log masuk sebagai Urus Setia | Papan pemuka Urus Setia dipaparkan. | ☑ Lulus | Landing Page peranan Urus Setia terpapar (1 tangkapan skrin dalam artifact UAT) |
| 2 | Klik **Permohonan Baharu** | Panel “Permohonan bagi Pihak Agensi” dipaparkan; borang belum dipaparkan. | ☑ Lulus | Pilihan Jabatan / Agensi terpapar (1 tangkapan skrin dalam artifact UAT) |
| 3 | Pilih agensi sasaran → **Sahkan Agensi & Teruskan** | Banner “Permohonan bagi pihak: …” dipaparkan. | ☑ Lulus | Senarai Jabatan/Agensi terpapar dan butang Sahkan Agensi dan Teruskan juga berfungsi |
| 4 | Lalui Fasa 1–5 | Banner agensi kekal di setiap fasa; Fasa 5 hanya menyenaraikan pegawai agensi sasaran. | ☑ Lulus | Fasa 5 hanya memaparkan maklumat pegawai di bawah Jabatan/Agensi yang dipilih (1 tangkapan skrin dalam artifact UAT) |
| 5 | **Simpan Draf**, kemudian buka semula melalui Senarai → “Draf Saya” → Kemaskini | Data kekal; banner “Jabatan/Agensi Permohonan” menunjukkan agensi sasaran; tiada butang Tukar Agensi. | ☑ Lulus | Tiada Butang Tukar Agensi terpapar (1 tangkapan skrin dalam artifact UAT) |
| 6 | Cuba hantar tanpa mesyuarat aktif | Butang Hantar dinyahaktifkan; jika dicuba, rekod kekal Draf. | ☑ Lulus | Butang Hantar Permohonan disable (1 tangkapan skrin dalam artifact UAT) |
| 7 | Dengan Mesyuarat JTICT aktif, hantar permohonan | Berjaya; nombor rujukan rasmi diberikan. Rekodkan nombor ini dalam Maklumat sesi. | ☑ Lulus | Permohonan berjaya dihantar (1 tangkapan skrin dalam artifact UAT) |
| 8 | Buka Senarai Urus Setia (Dihantar) | Permohonan dipaparkan dengan kod agensi sasaran. | ☐ | Kod Agensi terpapar pada di bawah Nama Projek (1 tangkapan skrin dalam artifact UAT) |
| 9 | Buka butiran permohonan | “Jabatan/Agensi” = agensi sasaran; “Didaftarkan oleh” = Urus Setia; Pegawai Utama daripada agensi sasaran. | ☒ Gagal | Pegawai Utama daripada agensi sasaran tidak terpapar. Hanya nama Urus Setia terpapar (1 tangkapan skrin dalam artifact UAT) |
| 10 | Log masuk sebagai pengguna biasa → **Permohonan Baharu** | Tiada pemilih agensi; banner menunjukkan agensi pengguna sendiri. | ☑ Lulus | Lulus (1 tangkapan skrin dalam artifact UAT) |
| 11 | Pengguna biasa **Simpan Draf** | `agency_id` = agensi pengguna. | ☑ Lulus | lulus |
| 12 | Urus Setia tandakan permohonan langkah 7 **Tidak Lengkap**, kemudian pendaftar meminda dan hantar semula | E-mel kepada Urus Setia pendaftar dan Pegawai Utama; sejarah dua rekod; agensi tidak berubah. | ☒ Gagal | Pegawai Utama tidak menerima e-mel makluman bagi permohonan projek yang tidak lengkap yang didaftarkan oleh Urus Setia bagi pihak agensi Pegawai Utama. Pegawai utama juga tidak boleh mengemaskini permohonan projek tidak lengkap. Hanya Urus Setia yang boleh mengemaskini. Sewajarnya pegawai utama boleh juga mengemaskini maklumat permohonan projek tersebut. (1 tangkapan skrin dalam artifact UAT) |

## 12. Isu Tertangguh

1. **Konflik kepentingan (tertangguh mengikut keputusan dasar).** Urus Setia yang mendaftar permohonan bagi pihak agensi masih boleh menyemak permohonan itu sendiri, termasuk menandakannya Lengkap. Tingkah laku ini **tidak diubah** dalam PP3; ia direkodkan dan diuji sebagai tingkah laku semasa. Keputusan dasar diperlukan.
2. **Pihak yang meminda apabila Tidak Lengkap.** *Diselesaikan pada 30/10/2026 (lihat Seksyen 14).* Mengikut polisi `update` sedia ada, hanya Urus Setia pendaftar boleh meminda. Pegawai Utama agensi sasaran menerima e-mel yang meminta pembetulan, tetapi tidak boleh mengemas kini rekod. Teks e-mel dan polisi tidak diubah kerana kedua-duanya di luar skop PP3; ini perlu diputuskan, sama ada menukar teks e-mel atau memberi kebenaran pindaan kepada pegawai.
3. **Draf Urus Setia sebelum PP3** (jika ada) kekal di bawah agensi Urus Setia sendiri, kerana agensi dikunci. Jika agensi itu salah, draf baharu perlu dicipta. Tiada data diubah secara automatik.
4. **SPPICT/2026/0001 dan 0002** (data lama, Dihantar tanpa mesyuarat) masih tiada `jtict_meeting_id`. Ini dipindahkan daripada UAT PP2.
5. **Penanda "bagi pihak" tidak disimpan sebagai medan.** Paparan menunjukkan agensi dan pendaftar secara fakta. Laporan statistik yang perlu membezakan permohonan Urus Setia akan memerlukan keputusan medan atau kriteria pada masa hadapan.

## 13. Risiko dan Cadangan Langkah Seterusnya

**Risiko:**

- UI belum diuji dalam pelayar (Seksyen 11).
- Senarai agensi tidak mempunyai carian. Jika agensi terlalu banyak, dropdown boleh menjadi panjang.
- Isu 12.1 dan 12.2 memberi kesan kepada tadbir urus dan perlu keputusan sebelum produksi.

**Cadangan:**

1. Laksanakan UAT PP3 mengikut Seksyen 11.
2. Buat keputusan dasar bagi isu 12.1 (konflik kepentingan) dan 12.2 (siapa meminda dan teks e-mel).
3. Tentukan skop fasa seterusnya, contohnya keputusan Pra JTICT. **PP3 tidak memulakan fasa seterusnya.**

## 14. Tindakan Susulan UAT PP3 (30/10/2026)

### 14.1 Analisis kegagalan

Analisis dibuat terhadap DB pembangunan secara baca sahaja; tiada rekod diubah.

| Langkah | Dapatan | Punca |
|---|---|---|
| Maklumat sesi | SPPICT/2026/0006 berada di bawah agensi **UPBA** (Unit Pengurusan Bangunan dan Aset, Bahagian Khidmat Pengurusan), bukan BDUN. BDUN ialah agensi bagi SPPICT/2026/0005. | Maklumat sesi UAT perlu dibetulkan. |
| 9 | Data adalah betul. Pegawai Utama 0006 ialah SUNARTI BINTI KAMARUDDIN (UPBA), yang dilantik oleh Urus Setia. Namanya **dipaparkan** dalam kotak "Pegawai Bertanggungjawab" di bahagian bawah halaman. Bahagian atas halaman hanya memaparkan "Didaftarkan oleh". | Isu susun atur paparan, bukan data. |
| 12 (e-mel) | Dua job `ApplicationMarkedIncomplete` dicipta pada 14:04:14, satu untuk Urus Setia pendaftar dan satu untuk Pegawai Utama. Kedua-duanya **masih dalam baris gilir** (`jobs` = 2, `failed_jobs` = 0). | Pemproses baris gilir (`queue:work`) tidak berjalan semasa UAT. Penerima adalah betul, dan tiada pepijat dalam kod. |
| 12 (pindaan) | Polisi `update` hanya membenarkan `applicant_id`, iaitu Urus Setia pendaftar. | Polisi asal, iaitu isu tertangguh 12.2. |
| 12 (penghantaran semula) | 0006 masih Tidak Lengkap dengan satu rekod semakan sahaja. | Penghantaran semula belum dilaksanakan dalam UAT. |

### 14.2 Keputusan pengguna dan perubahan

Keputusan pengguna: **Pegawai Utama dan Pegawai Kedua** boleh meminda permohonan semasa **Draf dan Tidak Lengkap**, dan nama Pegawai Utama dipaparkan di bahagian atas halaman butiran.

| Fail | Perubahan |
|---|---|
| `app/Policies/ApplicationPolicy.php` | `update` membenarkan **pemohon (pendaftar)** atau **pegawai bertanggungjawab aktif (Utama/Kedua) yang masih dalam agensi permohonan**, hanya semasa Draf atau Tidak Lengkap. Syarat agensi selaras dengan `manageOfficers` dan `updateProgress`. Polisi `review`, `view` dan `createOnBehalf` tidak diubah. |
| `resources/views/livewire/view-application.blade.php` | Bahagian atas halaman kini memaparkan "Pegawai Utama: nama" di sebelah "Didaftarkan oleh". |
| `tests/Feature/OnBehalfApplicationTest.php` | Dua ujian baharu ditambah, dan satu ujian sedia ada diperluas dengan semakan paparan Pegawai Utama. |

Kesan perubahan polisi:

- Satu polisi kini mengawal borang, servis penyimpanan dan penghantaran, butang "Kemas Kini & Hantar Semula", butang Kemaskini dalam senarai, dan pengurusan dokumen.
- Perubahan ini terpakai kepada **semua** permohonan, termasuk yang didaftarkan oleh pengguna biasa dengan pegawai lain sebagai Pegawai Utama.
- `applicant_id` dan agensi **tidak berubah** apabila pegawai meminda.
- Penghantaran semula direkodkan dalam `application_reviews` atas nama pegawai yang menghantar. Struktur jadual tidak diubah.
- Rakan seagensi yang bukan pegawai, pegawai yang tugasannya telah ditamatkan, dan pegawai yang bertukar keluar agensi **tidak** boleh meminda.
- Teks e-mel "sila buat pembetulan" kepada Pegawai Utama kini selaras dengan kebenarannya.

### 14.3 Ujian automatik

- `OnBehalfApplicationTest`: **16 lulus**.
- Suite penuh: **439 lulus, 0 gagal, 1 risky**.
- Tiada ujian sedia ada perlu diubah untuk lulus. Ujian `test_only_owner_can_amend_incomplete_application` masih sah kerana rakan seagensi dalam ujian itu bukan pegawai bertanggungjawab.

### 14.4 UAT semula yang diperlukan

1. Hantar e-mel yang tertunggak dengan `php artisan queue:work --stop-when-empty`.
   - Semak `.env` terlebih dahulu. Jika tetapan mel bukan Mailtrap, e-mel akan sampai ke alamat sebenar.
   - Sahkan Urus Setia pendaftar dan Pegawai Utama 0006 menerima e-mel.
2. **Langkah 9:** buka butiran 0006 dan sahkan "Pegawai Utama: SUNARTI BINTI KAMARUDDIN" dipaparkan di bahagian atas.
3. **Langkah 12:**
   - Log masuk sebagai Pegawai Utama 0006 dan buka permohonan. Butang "Kemas Kini & Hantar Semula" sepatutnya dipaparkan.
   - Pinda permohonan dan hantar semula.
   - Sahkan status menjadi Dihantar, sejarah mengandungi dua rekod (rekod kedua atas nama Pegawai Utama), agensi kekal UPBA, dan "Didaftarkan oleh" kekal nama Urus Setia.
4. Tandakan langkah 8 dalam jadual UAT, kerana catatannya menunjukkan ia lulus.

---

*Tiada operasi Git, migration, seeder atau operasi pemusnahan data dilakukan. `.env` tidak diubah. Modul SST, CSV, Pra JTICT, JTICT dan salinan Kaji Semula tidak disentuh.*
