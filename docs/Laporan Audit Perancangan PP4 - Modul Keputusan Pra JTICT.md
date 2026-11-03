# Laporan Audit Perancangan PP4: Modul Keputusan Pra JTICT

**Sistem:** SPPICT v2.1 (Laravel 13, Livewire 3, MySQL)
**Tarikh audit:** 2 November 2026
**Jenis dokumen:** Audit, analisis keperluan dan cadangan reka bentuk. **Tiada pembangunan dilakukan.**
**Rujukan:** PRD SPPICT v2, Laporan Audit PP0, Laporan Pelaksanaan PP1 hingga PP3, dan Laporan UAT PP2 dan PP3

Label dapatan dalam laporan ini:

| Label | Maksud |
|---|---|
| **[Disahkan]** | Dapatan berdasarkan kod, pangkalan data atau dokumentasi yang telah disemak |
| **[Cadangan]** | Saranan berdasarkan analisis |
| **[Belum Disahkan]** | Maklumat tidak mencukupi untuk membuat kesimpulan |
| **[Keputusan Pemilik Sistem]** | Memerlukan pengesahan anda sebelum pembangunan |

---

## 1. Ringkasan Eksekutif

### 1.1 Kedudukan semasa

1. **[Disahkan] Modul Pra JTICT belum wujud langsung.** Sistem tidak mempunyai jadual, medan, polisi, komponen, route atau notifikasi untuk keputusan Pra JTICT.
2. **[Disahkan] Asas aliran kerja sudah disediakan dalam PP1**, tetapi belum digunakan:
   - status `COMPLETE` (Lengkap) bermaksud "menunggu keputusan Pra JTICT";
   - status `IN_JTICT` (Dalam Proses JTICT) dan `CLOSED` (Selesai) sudah wujud;
   - peralihan `COMPLETE → IN_JTICT` dan `COMPLETE → CLOSED` sudah ditakrif dalam `app/Enums/ApplicationStatus.php`, tetapi tiada kod yang menggunakannya.
3. **[Disahkan] Corak sejarah yang terbukti boleh digunakan semula.** Jadual `application_reviews` (PP2) menyimpan sejarah semakan yang tidak boleh diubah atau dipadam. Corak yang sama sesuai untuk sejarah keputusan Pra JTICT.

### 1.2 Percanggahan yang mesti diselesaikan dahulu

**[Disahkan] Terdapat percanggahan dasar tentang kedudukan Pra JTICT:**

| Sumber | Kenyataan |
|---|---|
| Migration `2026_10_05_000005_drop_pra_jtict_meeting_id_from_applications_table.php` | *"Mesyuarat Pra-JTICT tidak lagi sebahagian daripada proses."* Lajur `pra_jtict_meeting_id` telah dibuang. |
| Meeting names (DB) | Hanya **JTICTNS** dan **JPICTNS** (tidak aktif) wujud. Tiada nama mesyuarat Pra JTICT. |
| PRD Seksyen 7.2 | Pra-JTICT ialah kategori mesyuarat: *"Saringan awal peringkat urus setia"*. |
| Laporan PP1 dan enum `ApplicationStatus` | Pra JTICT ialah peringkat keputusan antara Lengkap dan JTICT, dengan pilihan Diterima atau Ditolak. |
| Prompt PP4 | Pilihan keputusan Setuju, Tidak Setuju, Kaji Semula atau Batal. |

Keputusan terdahulu nampaknya ialah: **Pra JTICT bukan mesyuarat berasingan dalam sistem, tetapi masih satu peringkat keputusan.** Tafsiran ini perlu disahkan oleh anda (K1).

**[Disahkan] Pilihan keputusan tidak konsisten antara dokumen:**

| Sumber | Pilihan keputusan |
|---|---|
| PRD 7.2 (keputusan mesyuarat secara umum) | Lulus, Lulus Bersyarat, Kaji Semula, Tolak |
| PRD 7.4 (pengesahan keputusan) | Setuju, Tidak Setuju, Kaji Semula |
| Laporan PP1 (Pra JTICT) | Diterima, Ditolak |
| Prompt PP4 | Setuju, Tidak Setuju, Kaji Semula, Batal |

### 1.3 Cadangan utama

1. **[Cadangan]** Rekod keputusan Pra JTICT dalam **satu jadual sejarah baharu** `pre_jtict_decisions`.
   - Rekod tidak boleh diubah atau dipadam, mengikut corak `application_reviews`.
   - Hanya **satu migration tambahan** diperlukan.
   - Tiada lajur baharu pada `applications`, dan tiada perubahan pada `application_reviews` atau `jtict_decision`.
2. **[Cadangan]** **Setuju** → `IN_JTICT` dan **Tidak Setuju** → `CLOSED`. Kedua-dua peralihan ini sudah ditakrif dalam enum.
3. **[Keputusan Pemilik Sistem] Kaji Semula dan Batal belum boleh direka bentuk dengan muktamad.**
   - Maksud kedua-duanya belum jelas.
   - Kesannya terhadap status (Seksyen 7) memerlukan keputusan anda.
   - **Kaji Semula Pra JTICT tidak boleh dikelirukan dengan Kaji Semula JTICT**, yang mencetuskan fungsi salin permohonan.
4. **[Cadangan]** Modul Keputusan JTICT kekal di luar skop PP4. PP4 hanya memastikan permohonan yang layak ke JTICT ditanda dengan jelas (`IN_JTICT`).

### 1.4 Perkara terpenting yang perlu anda putuskan

Senarai penuh ada dalam Seksyen 13. Lima yang paling penting:

| # | Perkara |
|---|---|
| K1 | Adakah Pra JTICT satu **sesi mesyuarat yang direkodkan** dalam sistem, atau hanya **keputusan dengan tarikh**? |
| K2 | Senarai pilihan keputusan yang muktamad |
| K3 | Maksud dan kesan **Kaji Semula** Pra JTICT |
| K4 | Maksud **Batal**: keputusan Pra JTICT atau tindakan pentadbiran yang berasingan? |
| K6 | Peraturan ambang kos: adakah permohonan bawah RM 50,000 perlu melalui Pra JTICT? |

---

## 2. Skop dan Kaedah Audit

### 2.1 Skop

- Kod: model, enum, migration, policy, service, komponen Livewire, view, route, seeder dan ujian berkaitan permohonan dan mesyuarat.
- Pangkalan data pembangunan `sppictv2_db`, disemak dengan **SELECT sahaja**.
- Dokumentasi: `docs/PRD_SPPICT_v2.md`, `docs/AGENTS.md`, dan laporan PP0, PP1, PP2 dan PP3.

### 2.2 Kaedah

| Kaedah | Contoh |
|---|---|
| Bacaan kod | `ApplicationStatus`, `ApplicationPolicy`, `ApplicationReviewService`, `Meeting`, `MeetingForm`, `ApplicationList`, `ViewApplication`, `routes/web.php` |
| Carian kata kunci | `pra`, `pre_jtict`, `Kaji Semula`, `decision`, `permission` dalam `app`, `database`, `resources`, `routes`, `tests` dan `docs` |
| Pertanyaan DB baca sahaja | Taburan status dan keputusan, struktur lajur, mesyuarat, nama mesyuarat, peranan dan permission |

### 2.3 Kawalan yang dipatuhi

- Tiada perubahan kod atau `.env`.
- Tiada migration, seeder, `DELETE`, `DROP` atau `TRUNCATE`.
- Tiada operasi Git.

### 2.4 Batasan

1. **[Belum Disahkan]** Prosedur sebenar mesyuarat Pra JTICT di BTM tidak terdapat dalam dokumentasi projek, contohnya siapa yang hadir, adakah ia bersidang, dan adakah ia mempunyai bilangan atau minit.
2. Data pembangunan kecil: 8 permohonan dan 2 mesyuarat. Corak data produksi tidak dapat dinilai.
3. Ujian automatik menggunakan SQLite, jadi tidak membuktikan tingkah laku migration MySQL.

---

## 3. Dapatan Audit PP1 hingga PP3

| # | Dapatan | Label | Rujukan |
|---|---|---|---|
| D1 | Status proses ditakrif dalam satu enum dengan peta peralihan. Status disimpan sebagai VARCHAR(30), jadi DB tidak mengehadkan nilai; integriti bergantung pada enum. | Disahkan | `app/Enums/ApplicationStatus.php`; migration `2026_10_28_000001_change_status_to_string_on_applications_table.php` |
| D2 | Status `COMPLETE`, `IN_JTICT` dan `CLOSED` wujud. `COMPLETE → IN_JTICT` dan `COMPLETE → CLOSED` ditakrif tetapi **tidak digunakan** oleh mana-mana kod. | Disahkan | `ApplicationStatus::allowedTransitions()`; carian kod |
| D3 | Enum sengaja **tidak mengandungi nilai keputusan** (`APPROVED`, `REJECTED`, `REVIEW` dan lain-lain), dan ini dikuatkuasakan oleh ujian. Keputusan mesti disimpan secara berasingan daripada status. | Disahkan | `tests/Feature/ApplicationStatusTest.php::test_process_status_does_not_contain_meeting_decisions` |
| D4 | Peralihan status diuji secara menyeluruh, setiap pasangan dari dan ke. Menambah peralihan baharu (contohnya `COMPLETE → NEEDS_AMENDMENT`) **akan memerlukan ujian ini dikemas kini**. | Disahkan | `ApplicationStatusTest` baris 92–103 |
| D5 | Semakan kelengkapan (PP2) melalui `ApplicationReviewService::review()`: transaksi, kunci rekod (`lockForUpdate`), semakan polisi dan peralihan, rekod sejarah, dan e-mel selepas commit. | Disahkan | `app/Services/ApplicationReviewService.php` |
| D6 | Jadual `application_reviews` tidak boleh diubah atau dipadam: model melontar `LogicException`, dan FK tanpa cascade. Ia khusus untuk semakan dan penghantaran semula (`isReviewDecision()`, `actionLabel()`). | Disahkan | `app/Models/ApplicationReview.php`; migration `create_application_reviews_table` |
| D7 | Permohonan dikaitkan dengan **satu** mesyuarat JTICT melalui `applications.jtict_meeting_id`, yang ditetapkan semasa dihantar dan dikekalkan semasa hantar semula jika mesyuarat masih dibuka. | Disahkan | `ApplicationSubmissionService::submit()`, `Application::targetJtictMeeting()` |
| D8 | Lajur keputusan warisan wujud tetapi **tidak pernah ditulis**: `jtict_decision` ENUM('PENDING','APPROVED','APPROVED_CONDITIONAL','REVIEW','REJECTED'), `jtict_remarks`, `jpict_meeting_id`, `jpict_decision`, `jpict_remarks`. Semua 8 permohonan bernilai `PENDING`. | Disahkan | DB; migration `create_sppict_core_tables.php` |
| D9 | `pra_jtict_meeting_id` telah dibuang (lihat Seksyen 1.2). | Disahkan | Migration `2026_10_05_000005` |
| D10 | Senarai Urus Setia boleh menapis mengikut **semua** status, termasuk Lengkap. Ini satu-satunya cara semasa untuk melihat permohonan yang menunggu Pra JTICT. | Disahkan | `app/Livewire/ApplicationList.php` |
| D11 | Halaman butiran tidak mempunyai tindakan untuk permohonan Lengkap. Modal Lengkap menyatakan *"menunggu proses Pra JTICT. Ini bukan keputusan kelulusan."* | Disahkan | `resources/views/livewire/view-application.blade.php` baris 302 |
| D12 | PP3: Urus Setia boleh mendaftar permohonan bagi pihak agensi (`applicant_id` = Urus Setia, `agency_id` = agensi sasaran). Pegawai Utama dan Kedua yang aktif boleh meminda Draf dan Tidak Lengkap. | Disahkan | `ApplicationPolicy::update()`, `createOnBehalf()` |
| D13 | Kebenaran dikawal oleh **peranan + Policy**, bukan permission Spatie. Permission `update-meeting-decisions` dan `recommend-to-jpict` wujud dalam seeder tetapi **tidak dirujuk dalam kod**. | Disahkan | `database/seeders/RolesAndPermissionsSeeder.php`; carian `->can(`, `permission:` |
| D14 | `ApplicationPolicy::review()` mengecualikan superadmin dan pengurusan walaupun mereka memegang peranan lain. Route `application.view` dan `application.edit` menyekat superadmin. | Disahkan | `ApplicationPolicy`; `routes/web.php` |
| D15 | Peranan `pengurusan` tiada akses kepada permohonan: polisi `view` tidak merangkumnya (kecuali dalam agensi yang sama), dan senarai bukan Urus Setia hanya memaparkan permohonan sendiri. Pada masa ini, 0 pengguna memegang peranan ini. | Disahkan | `ApplicationPolicy::view()`; `ApplicationList`; DB |
| D16 | Peraturan ambang kos (RM 20k–49,999.99 di peringkat agensi; RM 50k–499,999.99 ke JTICTNS; RM 500k ke atas ke JTICTNS kemudian JPICTNS) **tidak dikuatkuasakan dalam kod**. | Disahkan | `docs/AGENTS.md` Seksyen 3; carian kod |
| D17 | Rekod sejarah semakan melabel penghantaran semula oleh Pegawai Utama sebagai "Pemohon" (`actorRoleLabel()`), sedangkan selepas PP3 pegawai juga boleh menghantar semula. Isu paparan kecil. | Disahkan | `ApplicationReview::actorRoleLabel()` |
| D18 | `Meeting` hanya mempunyai satu jenis hubungan permohonan (`applications()` melalui `jtict_meeting_id`). `MeetingForm` mewajibkan tarikh tutup permohonan dan minit bagi setiap mesyuarat, iaitu medan yang direka untuk mesyuarat JTICT. | Disahkan | `app/Models/Meeting.php`; `MeetingForm::rules()` |

---

## 4. Gambaran Aliran Kerja Semasa

```
 PEMOHON / URUS SETIA (bagi pihak agensi)
        │
        ▼
  [DRAFT] ──hantar (perlu Mesyuarat JTICT dibuka)──► [SUBMITTED]             ✅ PP1/PP3
                                                      │
                               Urus Setia: Semakan Kelengkapan (PP2)          ✅ PP2
                         ┌────────────────────────────┴───────────┐
                         ▼                                        ▼
               [NEEDS_AMENDMENT]                             [COMPLETE]       ✅ PP2
           (ulasan wajib + e-mel)                     "menunggu Pra JTICT"
                         │                                        │
       pemohon/pegawai pinda & hantar semula                      │
                         └──────────► [SUBMITTED]                 │
                                                                  ▼
                                                  ┌─── KEPUTUSAN PRA JTICT ───┐  ❌ TIADA
                                                  ▼                           ▼
                                             [IN_JTICT]                   [CLOSED]     ⚠ status wujud,
                                                  │                                     tiada pencetus
                                     KEPUTUSAN JTICT (jtict_decision)          ❌ TIADA (fasa kemudian)
                                                  ▼
                                              [CLOSED] ──► Projek / Salin Kaji Semula  ❌ TIADA
```

| Bahagian | Keadaan | Label |
|---|---|---|
| Draf → Dihantar (dengan kawalan mesyuarat) | Berfungsi; lulus UAT PP2 dan PP3 | Disahkan |
| Semakan kelengkapan dan kitaran Tidak Lengkap | Berfungsi; lulus UAT PP2 dan PP3 | Disahkan |
| Senarai "menunggu Pra JTICT" | Hanya melalui penapis Lengkap dalam senarai umum | Disahkan |
| Keputusan Pra JTICT | Tiada | Disahkan |
| Pencetus `IN_JTICT` / `CLOSED` | Tiada | Disahkan |
| Keputusan JTICT, JPICT, penciptaan projek, salin Kaji Semula | Tiada; jadual `projects` kosong | Disahkan |
| Data lama: 0001 dan 0002 berstatus SUBMITTED tanpa mesyuarat JTICT | Akan menjejaskan senarai Pra JTICT jika ditanda Lengkap | Disahkan |

---

## 5. Cadangan Aliran Kerja Pra JTICT

**[Cadangan]** Model asas, bergantung pada keputusan K1 hingga K4:

```
 [COMPLETE] ──► Senarai Pra JTICT (Urus Setia, dikumpul mengikut Mesyuarat JTICT sasaran)
                    │
                    ▼  Urus Setia merekod keputusan (modal pengesahan, ulasan, tarikh sesi)
   ┌────────────┬───────────────┬──────────────────────┬───────────────────┐
   ▼            ▼               ▼                      ▼
 SETUJU      TIDAK SETUJU    KAJI SEMULA (K3)       BATAL (K4)
   │            │               │                      │
   ▼            ▼               ▼                      ▼
[IN_JTICT]   [CLOSED]     Pilihan A: [NEEDS_AMENDMENT] [CLOSED]
 sedia untuk  + e-mel      → pinda → [SUBMITTED] →      + e-mel
 Modul JTICT               semakan → [COMPLETE] →
 (fasa lain)               Pra JTICT semula
                           Pilihan B: kekal [COMPLETE] untuk sesi seterusnya
```

Prinsip reka bentuk:

1. **Satu keputusan Pra JTICT aktif bagi setiap pusingan.** Keputusan hanya boleh direkod apabila status = `COMPLETE`. Selepas keputusan direkod, status berubah, jadi permohonan tidak boleh diputuskan dua kali.
2. **Semua keputusan disimpan sebagai sejarah**, termasuk keputusan terdahulu apabila permohonan dibawa semula.
3. **Status proses dan keputusan kekal berasingan** (D3). Status menunjukkan kedudukan permohonan dalam aliran kerja; jadual keputusan menunjukkan apa yang diputuskan.
4. **`jtict_decision` tidak disentuh dalam PP4.** Ia kekal `PENDING` sehingga Modul Keputusan JTICT.

---

## 6. Analisis Keperluan Fungsian

### 6.1 Senarai permohonan untuk Pra JTICT

**Kriteria kelayakan [Cadangan]:**

| # | Kriteria | Sebab |
|---|---|---|
| 1 | `status = COMPLETE` | Sudah lulus semakan kelengkapan (PP1 dan PP2) |
| 2 | Tidak dipadam (soft delete) | Rekod aktif sahaja |
| 3 | Mempunyai `jtict_meeting_id` | Pra JTICT ialah saringan sebelum JTICT tertentu; permohonan tanpa mesyuarat (data lama 0001 dan 0002) perlu dikaitkan dahulu (K9) |
| 4 | Tiada keputusan Pra JTICT bagi pusingan semasa | Dijamin oleh status: selepas keputusan, status bukan lagi `COMPLETE` |
| 5 | **[Keputusan Pemilik Sistem]** Ambang kos ≥ RM 50,000? | Menurut `AGENTS.md`, permohonan RM 20k–49,999.99 diluluskan di peringkat agensi. Sistem semasa tidak menguatkuasakan ambang ini (D16). |

**Mengaitkan permohonan dengan sesi Pra JTICT [Keputusan Pemilik Sistem, K1]:**

| Pilihan | Penerangan | Kesan teknikal | Penilaian |
|---|---|---|---|
| **A. Keputusan dengan tarikh (disyorkan)** | Pra JTICT direkodkan sebagai keputusan dengan **tarikh sesi**. Permohonan dikumpul mengikut Mesyuarat JTICT sasaran yang sedia ada (`jtict_meeting_id`). | Hanya satu jadual baharu. Tiada perubahan pada Modul Mesyuarat. | Selaras dengan keputusan terdahulu yang membuang `pra_jtict_meeting_id`. Paling sedikit risiko. |
| B. Sesi Pra JTICT sebagai mesyuarat | Tambah Nama Mesyuarat "Pra JTICT" dalam data selenggara. Keputusan merujuk ID mesyuarat Pra JTICT. | `MeetingForm` mewajibkan tarikh tutup permohonan dan minit (D18), dan semakan tempoh bertindih direka untuk JTICT. Modul Mesyuarat perlu diubah. | Lebih lengkap (bilangan, tarikh, minit), tetapi menyentuh modul yang telah lulus UAT. |

**Maklumat dalam senarai [Cadangan]:**

- No. rujukan
- Tajuk projek
- Agensi (kod)
- Kategori permohonan
- Jumlah kos (termasuk SST)
- Mesyuarat JTICT sasaran
- Tarikh Lengkap (rekod `application_reviews` terakhir)
- Pegawai Utama
- Penanda "Dibawa semula" jika ada keputusan Pra JTICT terdahulu
- Penanda "Dihantar semula" (sedia ada)

**Carian dan penapis [Cadangan]:**

- Carian: no. rujukan, tajuk
- Penapis: Mesyuarat JTICT sasaran, agensi, kategori permohonan, julat kos, dan keadaan keputusan (Menunggu Keputusan, atau semua keputusan bagi sesi tertentu)
- Susunan lalai: mengikut Mesyuarat JTICT, kemudian tarikh Lengkap
- Paginasi, seperti senarai sedia ada

### 6.2 Maklumat pembentangan dan penilaian

**[Disahkan] Maklumat yang sudah tersedia (tidak perlu medan baharu):**

| Maklumat | Sumber |
|---|---|
| Tajuk, objektif, skop, kategori permohonan | `applications` (Fasa 1) |
| Jenis dan kaedah perolehan, kos siling, anggaran kos, tempoh, kod hasil | `applications` (Fasa 2) |
| Jumlah kecil, SST dan jumlah keseluruhan | `applications.subtotal_cost`, `sst_amount`, `total_cost` |
| Perincian item | `application_details` |
| Kertas Cadangan dan Slaid Pembentangan JTICT (PDF) | `application_documents` |
| Pegawai Utama dan Kedua | `application_officers` |
| Agensi, pendaftar dan Mesyuarat JTICT sasaran | `applications` dan hubungannya |
| Sejarah semakan kelengkapan | `application_reviews` |

**[Cadangan] Medan baharu yang benar-benar diperlukan:**

| Medan | Wajib? | Justifikasi |
|---|---|---|
| **Keputusan** | Ya | Teras modul |
| **Ulasan / Justifikasi keputusan** | Wajib bagi Tidak Setuju, Kaji Semula dan Batal; pilihan bagi Setuju | Sama seperti ulasan Tidak Lengkap (PP2). Pemohon perlu tahu sebabnya. |
| **Syor kepada JTICT** | Pilihan, bagi Setuju sahaja | PRD 7.2 menyebut "syor untuk diangkat". Membezakan nasihat Pra JTICT daripada keputusan JTICT. |
| **Tarikh sesi Pra JTICT** | Ya | Rekod bila saringan dibuat. Mungkin berbeza daripada masa data dimasukkan. |
| **Pegawai yang merekod** dan **masa rekod** | Automatik | Jejak audit |

**[Cadangan] Medan yang tidak disyorkan pada masa ini:**

| Medan | Sebab |
|---|---|
| "Catatan perbincangan" berasingan | Bertindih dengan Ulasan. Jika perlu, Ulasan boleh mengandungi ringkasan perbincangan. |
| "Isu yang perlu diberi perhatian" berasingan | Bertindih dengan Ulasan dan Syor. Medan berasingan menambah beban input tanpa nilai yang jelas. |
| Penilaian berstruktur (skor, kriteria) | Tiada keperluan dalam PRD atau dokumentasi |

[Keputusan Pemilik Sistem, K7] Jika prosedur BTM memang memerlukan catatan perbincangan atau isu yang berasingan, medan tambahan boleh dimasukkan dalam migration yang sama.

### 6.3 Keputusan Pra JTICT

Lihat Seksyen 7 untuk analisis penuh setiap pilihan.

### 6.4 Sejarah keputusan dan rekod audit

**[Cadangan]** Jadual baharu `pre_jtict_decisions`, dengan rekod yang tidak boleh diubah dan sejarah yang tidak hilang:

| Keperluan prompt | Cara dipenuhi |
|---|---|
| Tarikh dan masa keputusan | `session_date` (tarikh sesi) dan `created_at` (masa rekod) |
| Mesyuarat berkaitan | `jtict_meeting_id` (sasaran ketika keputusan), dan sesi Pra JTICT jika K1 = B |
| Keputusan | `decision` |
| Ulasan | `remarks` dan `recommendation` |
| Pegawai yang merekod | `decided_by` |
| Sejarah terdahulu | Setiap keputusan ialah rekod baharu; tiada kemas kini atau pemadaman (mengikut corak `ApplicationReview::booted()`) |
| Tindakan susulan dan status terkini | `from_status` dan `to_status` merekod kesan; status terkini kekal dalam `applications.status` |

**[Cadangan] Pembetulan keputusan yang salah.** Rekod tidak boleh diubah. Pembetulan memerlukan keputusan anda (K10):

- **(a)** tiada pembetulan dalam sistem, dan Urus Setia merujuk kepada superadmin/pembangun; atau
- **(b)** tindakan "Batalkan Keputusan" yang mencipta rekod baharu dengan sebab wajib dan mengembalikan status kepada `COMPLETE`.

Pilihan (b) lebih telus, tetapi menambah skop.

**[Disahkan]** Log audit owen-it akan terus merekod perubahan `applications.status` sebagai jejak teknikal yang berasingan.

### 6.5 Hubungan dengan proses JTICT

| Perkara | Cadangan / Dapatan | Label |
|---|---|---|
| Permohonan yang boleh dibawa ke JTICT | Hanya `IN_JTICT` (Pra JTICT Setuju). Modul JTICT nanti mesti menolak keputusan bagi status lain. | Cadangan |
| Permohonan yang perlu dikaji semula | Bergantung pada K3 (Seksyen 7) | Keputusan Pemilik Sistem |
| Permohonan yang tidak diteruskan | `CLOSED`, dengan keputusan Tidak Setuju atau Batal dalam sejarah | Cadangan |
| Kaitan dengan mesyuarat JTICT | Kekalkan `jtict_meeting_id` sedia ada. **[Keputusan Pemilik Sistem, K8]** Adakah Urus Setia boleh memindahkan permohonan ke Mesyuarat JTICT lain semasa atau selepas Pra JTICT, contohnya jika agenda penuh? | Keputusan Pemilik Sistem |
| `jtict_decision` | Tidak disentuh dalam PP4; kekal `PENDING` | Cadangan |
| Risiko diproses dua kali | Dikawal oleh kunci rekod (`lockForUpdate`) dan semakan status dalam transaksi, seperti PP2 | Cadangan |
| Risiko keputusan bercanggah | Contohnya Pra JTICT Tidak Setuju tetapi JTICT Lulus. Modul JTICT mesti mensyaratkan status `IN_JTICT`. Peralihan `CLOSED → *` tidak wujud dalam enum, jadi rekod `CLOSED` tidak boleh dibuka semula tanpa perubahan kod. | Disahkan (enum) / Cadangan |
| Kaji Semula JTICT berbanding Kaji Semula Pra JTICT | Kaji Semula JTICT (`jtict_decision = REVIEW`) mencetuskan **salin permohonan baharu** (PP0 Seksyen 13). Kaji Semula Pra JTICT **tidak boleh** mencetuskan salinan. Label paparan perlu jelas, contohnya "Kaji Semula (Pra JTICT)". | Cadangan |

---

## 7. Cadangan Status dan Keputusan Pra JTICT

**[Keputusan Pemilik Sistem, K2]** Analisis di bawah menggunakan pilihan daripada prompt. Kod dalaman yang dicadangkan untuk enum baharu `PreJtictDecision` ialah `AGREED`, `DISAGREED`, `REVIEW` dan `CANCELLED`. Nilai-nilai ini **tidak** dimasukkan ke dalam `ApplicationStatus` (D3).

### 7.1 Setuju

| Perkara | Cadangan |
|---|---|
| Maksud | Permohonan lulus saringan Pra JTICT dan **layak dibentangkan** dalam Mesyuarat JTICT. **Ini bukan kelulusan projek.** |
| Siapa merekod | Urus Setia (`pentadbir_urus_setia`) |
| Maklumat wajib | Tarikh sesi. Ulasan dan Syor kepada JTICT adalah pilihan. |
| Kesan status | `COMPLETE → IN_JTICT` (peralihan sedia ada) |
| Tindakan susulan | Masuk senarai JTICT (fasa seterusnya). E-mel kepada pemohon dan Pegawai Utama: **[Keputusan Pemilik Sistem, K5]**. |
| Boleh ke JTICT? | **Ya** |

### 7.2 Tidak Setuju

| Perkara | Cadangan |
|---|---|
| Maksud | Permohonan **tidak diteruskan** ke JTICT, contohnya kerana tidak selaras dengan dasar ICT atau bertindih dengan projek sedia ada |
| Siapa merekod | Urus Setia |
| Maklumat wajib | Tarikh sesi dan **ulasan (wajib, minimum 10 aksara)** |
| Kesan status | `COMPLETE → CLOSED` (peralihan sedia ada) |
| Tindakan susulan | E-mel kepada pemohon dan Pegawai Utama (K5). Agensi perlu mendaftar **permohonan baharu** jika ingin memohon semula; rekod `CLOSED` tidak boleh dibuka semula. |
| Boleh ke JTICT? | **Tidak** |

### 7.3 Kaji Semula

| Perkara | Cadangan |
|---|---|
| Maksud | Permohonan **berpotensi**, tetapi perlu diperbaiki atau diperjelas sebelum diputuskan semula. **Berbeza daripada Kaji Semula JTICT.** |
| Siapa merekod | Urus Setia |
| Maklumat wajib | Tarikh sesi dan **ulasan wajib** yang menyatakan perkara yang perlu dikaji semula |
| Boleh ke JTICT? | **Belum**. Perlu melalui Pra JTICT semula. |

Kesan status dan tindakan susulan bergantung pada pilihan anda **[Keputusan Pemilik Sistem, K3]**:

| Pilihan | Aliran | Kebaikan | Kesan / Risiko |
|---|---|---|---|
| **A (disyorkan jika pemohon perlu meminda)** | `COMPLETE → NEEDS_AMENDMENT`. Pemohon atau pegawai meminda dan menghantar semula → `SUBMITTED` → semakan kelengkapan → `COMPLETE` → Pra JTICT semula. | Menggunakan semula aliran pindaan dan e-mel PP2 yang telah lulus UAT | Peralihan baharu dalam enum. `ApplicationStatusTest` perlu dikemas kini (D4). Label "Tidak Lengkap" perlu dibezakan, contohnya dengan memaparkan sebab daripada sejarah Pra JTICT. Peraturan mesyuarat semasa hantar semula (PP2 3.4) terpakai: jika mesyuarat JTICT asal sudah ditutup, pemohon mesti memilih mesyuarat aktif. |
| B | Kekal `COMPLETE`. Dibawa semula ke sesi Pra JTICT berikutnya tanpa pindaan pemohon, contohnya menunggu maklumat luar. | Tiada perubahan status atau enum | Pemohon tidak boleh meminda (status bukan Draf atau Tidak Lengkap). Senarai mesti membezakan "Menunggu" dengan "Kaji Semula". |
| C | Status baharu, contohnya `PRE_JTICT_REVIEW` | Maksud paling jelas | Status baharu, peralihan baharu, dan semua paparan dan ujian status perlu dikemas kini. Skop paling besar. |

### 7.4 Batal

| Perkara | Cadangan |
|---|---|
| Maksud yang mungkin | (i) Agensi **menarik balik** permohonan; atau (ii) Permohonan dibatalkan kerana sebab pentadbiran, contohnya pertindihan atau projek dibatalkan oleh agensi. **Batal bukan penilaian merit**, jadi ia berbeza daripada Tidak Setuju. |
| Siapa merekod | Urus Setia (atas permintaan rasmi agensi). **[Keputusan Pemilik Sistem]** Adakah pemohon sendiri boleh menarik balik? |
| Maklumat wajib | **Sebab pembatalan (wajib)** |
| Kesan status | `COMPLETE → CLOSED` |
| Tindakan susulan | E-mel kepada pemohon dan Pegawai Utama (K5) |
| Boleh ke JTICT? | **Tidak** |

**[Keputusan Pemilik Sistem, K4]** Jika Batal sebenarnya **tindakan pentadbiran** yang boleh berlaku pada status lain (Draf, Dihantar, Tidak Lengkap), ia lebih sesuai sebagai fungsi berasingan ("Batal Permohonan") di luar Modul Pra JTICT. Ini mengelakkan Batal kelihatan seperti keputusan jawatankuasa.

### 7.5 Ringkasan kesan status

| Keputusan | Dari | Ke | Peralihan sudah dalam enum? | Ulasan wajib |
|---|---|---|---|---|
| Setuju | COMPLETE | IN_JTICT | ✅ Ya | Tidak |
| Tidak Setuju | COMPLETE | CLOSED | ✅ Ya | Ya |
| Kaji Semula (A) | COMPLETE | NEEDS_AMENDMENT | ❌ Tidak; enum dan ujian perlu dikemas kini | Ya |
| Kaji Semula (B) | COMPLETE | COMPLETE | Tiada peralihan | Ya |
| Batal | COMPLETE | CLOSED | ✅ Ya | Ya |

**[Keputusan Pemilik Sistem, K11]** Label `CLOSED` ialah "Selesai". Bagi Tidak Setuju dan Batal, "Selesai" mungkin mengelirukan pemohon. Paparan perlu menunjukkan keputusan sebenar di sebelah status, contohnya "Selesai: Tidak Setuju (Pra JTICT)".

---

## 8. Cadangan Peranan dan Kawalan Akses

**[Cadangan]** Teruskan corak sedia ada: **Policy + peranan**, bukan permission Spatie (D13). Tambah `ApplicationPolicy::decidePreJtict()` dengan syarat:

- pengguna boleh menggunakan sistem (`canUseSystem`);
- pengguna memegang peranan `pentadbir_urus_setia`;
- pengguna tidak memegang `superadmin` atau `pengurusan`;
- status permohonan = `COMPLETE`.

Corak ini sama seperti `review()`. Kebenaran mesti disemak dalam **komponen dan service**, bukan hanya pada butang.

| Fungsi | Superadmin | Urus Setia | Pengguna Biasa | Pengurusan |
|---|---|---|---|---|
| Lihat senarai Pra JTICT | ❌ | ✅ | ❌ | ⚠ K12 (cadangan: lihat sahaja) |
| Lihat butiran permohonan | ❌ (route menyekat; D14) | ✅ | ✅ Permohonan sendiri, agensi sendiri atau sebagai pegawai (polisi `view` sedia ada) | ⚠ K12 (tiada akses semasa; D15) |
| Rekod ulasan/catatan | ❌ | ✅ (bersama keputusan) | ❌ | ❌ |
| Rekod keputusan | ❌ | ✅ | ❌ | ❌ |
| Kemas kini rekod keputusan | ❌ | ❌ (rekod tidak boleh diubah; pembetulan ikut K10) | ❌ | ❌ |
| Lihat sejarah keputusan | ❌ (melalui Log Audit sahaja) | ✅ Penuh, dengan nama pegawai | ⚠ K13 (cadangan: keputusan dan ulasan, tanpa nama individu, seperti sejarah semakan PP2) | ⚠ K12 |

Perkara yang memerlukan pengesahan anda:

1. **[Keputusan Pemilik Sistem, K12]** PRD menyatakan pengurusan boleh *"Semak Kemasukan Permohonan ke Pra-JTICT / JTICT"*. Memberikan akses lihat kepada pengurusan **mengubah polisi `view`** sedia ada, jadi ia lebih sesuai sebagai fasa berasingan. Tiada pengguna pengurusan buat masa ini.
2. **[Keputusan Pemilik Sistem, K14] Konflik kepentingan (isu tertangguh PP2 dan PP3).** Urus Setia yang mendaftar permohonan bagi pihak agensi juga boleh merekod keputusan Pra JTICT bagi permohonan yang sama. Selaras dengan arahan, PP4 **tidak** menambah sekatan, tetapi isu ini lebih ketara pada peringkat keputusan.

---

## 9. Audit Pangkalan Data dan Struktur Teknikal

### 9.1 Jadual yang boleh digunakan semula [Disahkan]

| Jadual / komponen | Penggunaan dalam PP4 |
|---|---|
| `applications.status` | Status proses (`COMPLETE`, `IN_JTICT`, `CLOSED`) |
| `applications.jtict_meeting_id` → `meetings` | Mengumpul senarai mengikut Mesyuarat JTICT sasaran |
| `application_details`, `application_documents`, `application_officers`, `agencies` | Maklumat pembentangan (baca sahaja) |
| `application_reviews` | Tarikh Lengkap. **Tidak diubah**: modelnya khusus untuk semakan kelengkapan (D6). |
| `audits` (owen-it) | Jejak teknikal perubahan status |
| `ApplicationStatus` enum | Peralihan sedia ada |
| `ApplicationReviewService` | **Corak rujukan** untuk service baharu: transaksi, kunci rekod, e-mel selepas commit |

### 9.2 Medan sedia ada berkaitan keputusan dan status [Disahkan]

| Medan | Keadaan | Cadangan PP4 |
|---|---|---|
| `status` (VARCHAR 30) | Digunakan | Digunakan untuk peralihan |
| `review_remarks` | Ulasan semakan terkini (PP2) | **Jangan gunakan** untuk Pra JTICT; ia akan menimpa ulasan PP2 |
| `jtict_decision` (ENUM MySQL), `jtict_remarks` | Tidak digunakan | **Jangan sentuh**; untuk Modul JTICT |
| `jpict_meeting_id`, `jpict_decision`, `jpict_remarks` | Tidak digunakan | Jangan sentuh |
| `primary_officer_*`, `secondary_officer_*` | Lajur warisan, tidak digunakan | Tiada kaitan |

### 9.3 Hubungan semasa [Disahkan]

```
agencies 1──* applications *──1 users (applicant_id)
                 │  └──*1 meetings (jtict_meeting_id) *──1 meeting_names (accepts_applications)
                 ├──* application_details
                 ├──* application_documents
                 ├──* application_officers *──1 users
                 ├──* application_reviews  *──1 users, *──1 meetings
                 └──1 projects (kosong; untuk selepas JTICT)
```

### 9.4 Cadangan jadual baharu [Cadangan; tidak dilaksanakan]

**Satu migration tambahan:** `create_pre_jtict_decisions_table`

| Lajur | Jenis | Tujuan |
|---|---|---|
| `id` | bigint PK | Kunci dalaman |
| `uuid` | uuid, unique | Rujukan selamat dalam URL (prinsip `AGENTS.md`) |
| `application_id` | FK → `applications`, **tanpa cascade** | Permohonan berkaitan; sejarah menghalang pemadaman kekal |
| `decision` | varchar(30) | `AGREED`, `DISAGREED`, `REVIEW` atau `CANCELLED` (enum PHP `PreJtictDecision`; VARCHAR mengikut corak PP1, supaya pilihan boleh ditambah tanpa migration) |
| `from_status`, `to_status` | varchar(30) | Kesan keputusan terhadap status proses |
| `session_date` | date | Tarikh sesi Pra JTICT |
| `remarks` | text, nullable | Ulasan atau justifikasi (wajib selain Setuju) |
| `recommendation` | text, nullable | Syor kepada JTICT (Setuju) |
| `jtict_meeting_id` | FK → `meetings`, nullable | Mesyuarat JTICT sasaran ketika keputusan |
| `decided_by` | FK → `users` | Urus Setia yang merekod |
| `created_at` | timestamp | Masa rekod; tiada `updated_at` kerana rekod tidak boleh diubah |

Indeks dicadangkan: `(application_id, created_at)` dan `(decision)`. Jika K1 = B, tambah `pre_jtict_meeting_id` (FK → `meetings`).

Keputusan reka bentuk yang dicadangkan:

- **Tiada lajur baharu pada `applications`.** Keputusan terkini = rekod terakhir dalam jadual baharu. Ini mengelak pertindihan data dan percanggahan antara lajur ringkasan dan sejarah.
- **Jadual berasingan daripada `application_reviews`.** Menggabungkannya akan mengubah makna `isReviewDecision()`, `actionLabel()` dan paparan sejarah PP2 yang telah lulus UAT. Ia juga melanggar arahan "jangan ubah `application_reviews` melainkan perlu".
- **Model mengikut corak `ApplicationReview`:** `UPDATED_AT = null` dan `LogicException` pada `updating` dan `deleting`.

### 9.5 Komponen kod yang dijangka [Cadangan]

| Komponen | Baharu / Diubah |
|---|---|
| `app/Enums/PreJtictDecision.php` | Baharu |
| `app/Models/PreJtictDecision.php` dan `Application::preJtictDecisions()` | Baharu / hubungan baharu |
| `app/Services/PreJtictDecisionService.php` | Baharu |
| `ApplicationPolicy::decidePreJtict()` | Kaedah baharu (polisi lain tidak diubah) |
| Komponen Livewire senarai Pra JTICT dan route `urus-setia/pra-jtict` | Baharu |
| `ViewApplication` dan view: panel keputusan dan sejarah | Diubah |
| Notifikasi keputusan | Baharu (bergantung K5) |
| `ApplicationStatus` | Diubah **hanya** jika K3 = A |
| Menu navigasi dan papan pemuka Urus Setia (kad "Menunggu Pra JTICT") | Diubah |

### 9.6 Risiko terhadap PP1 hingga PP3

| Risiko | Tahap | Kawalan |
|---|---|---|
| Menukar enum status (K3 = A) memecahkan `ApplicationStatusTest` | Sederhana | Kemas kini ujian dengan justifikasi; jalankan suite penuh |
| Menggunakan `review_remarks` atau `application_reviews` untuk Pra JTICT menimpa atau mengelirukan sejarah PP2 | Tinggi jika dilakukan | Jadual berasingan (9.4) |
| Menyentuh Modul Mesyuarat (K1 = B) menjejaskan pengumuman mesyuarat dan tempoh permohonan | Sederhana | Pilih K1 = A atau jadikan fasa berasingan |
| Migration MySQL berbeza daripada SQLite | Rendah (jadual baharu sahaja) | Sandaran DB dan `migrate --pretend` sebelum dijalankan |

---

## 10. Cadangan Reka Bentuk Antara Muka

**[Cadangan]** Mengikut gaya antara muka sedia ada (panel Semakan Kelengkapan PP2).

1. **Menu Urus Setia "Pra JTICT"** (`/urus-setia/pra-jtict`):
   - tajuk dan pemilih Mesyuarat JTICT sasaran (lalai: mesyuarat akan datang);
   - kad ringkasan: Menunggu Keputusan, Setuju, Tidak Setuju, Kaji Semula dan Batal bagi mesyuarat dipilih;
   - jadual: No. Rujukan, Tajuk, Agensi, Kategori, Jumlah Kos, Tarikh Lengkap, penanda "Dibawa semula", dan butang **Buka**;
   - penapis dan carian (6.1).
2. **Halaman butiran** (status Lengkap, `@can('decidePreJtict')`):
   - panel **"Keputusan Pra JTICT"** dengan empat butang keputusan;
   - modal pengesahan: tarikh sesi, ulasan (wajib mengikut keputusan), Syor kepada JTICT (Setuju sahaja), penerangan kesan keputusan, dan amaran *"Ini bukan keputusan JTICT."*
3. **Bahagian "Sejarah Keputusan Pra JTICT"**:
   - senarai terkini dahulu;
   - nama pegawai dipaparkan kepada Urus Setia sahaja, mengikut corak sejarah semakan PP2.
4. **Lencana status:** `IN_JTICT` dipaparkan sebagai "Dalam Proses JTICT", dengan keputusan Pra JTICT ditunjukkan di sebelahnya.
5. **Papan pemuka Urus Setia:** kad "Menunggu Pra JTICT", iaitu bilangan permohonan Lengkap.
6. **Paparan pemohon:** keputusan dan ulasan dalam halaman butiran (K13).

---

## 11. Senario Ujian dan Kriteria Penerimaan

| # | Senario | Hasil dijangka |
|---|---|---|
| T1 | Permohonan Lengkap dengan Mesyuarat JTICT dibawa ke Pra JTICT | Dipaparkan dalam senarai Pra JTICT di bawah mesyuarat yang betul; panel keputusan dipaparkan kepada Urus Setia |
| T2 | Permohonan Draf, Dihantar, Tidak Lengkap, `IN_JTICT` atau `CLOSED` | Tiada dalam senarai "Menunggu". Panggilan terus kepada rekod keputusan ditolak (403/422) dan tiada rekod dicipta |
| T3 | Permohonan Lengkap tanpa `jtict_meeting_id` (data lama) | Tidak boleh diputuskan, dengan mesej meminta mesyuarat dikaitkan dahulu (bergantung K9) |
| T4 | Keputusan **Setuju** | Status `IN_JTICT`; 1 rekod `pre_jtict_decisions` (AGREED, COMPLETE → IN_JTICT, `decided_by`); `jtict_decision` kekal PENDING; e-mel mengikut K5 |
| T5 | Keputusan **Tidak Setuju** tanpa ulasan | Ralat pengesahan; status dan rekod tidak berubah |
| T6 | Keputusan **Tidak Setuju** dengan ulasan | Status `CLOSED`; rekod DISAGREED; tidak boleh disunting atau dihantar semula oleh pemohon |
| T7 | Keputusan **Kaji Semula** | Ikut K3. Pilihan A: `NEEDS_AMENDMENT`, pemohon atau pegawai boleh meminda, e-mel dihantar. Pilihan B: kekal `COMPLETE` dan ditanda Kaji Semula dalam senarai |
| T8 | Keputusan **Batal** | Status `CLOSED`; sebab wajib; rekod CANCELLED |
| T9 | Kaji Semula → pinda → hantar semula → Lengkap → Pra JTICT semula → Setuju | Dua rekod `pre_jtict_decisions` (REVIEW, AGREED) dan rekod `application_reviews` bagi pusingan pindaan kekal; status akhir `IN_JTICT`; penanda "Dibawa semula" dipaparkan |
| T10 | Sejarah dikekalkan | Rekod keputusan tidak boleh diubah atau dipadam (`LogicException`); semua rekod terdahulu dipaparkan |
| T11 | Pengguna biasa, pengurusan, superadmin, atau Urus Setia yang juga memegang peranan superadmin/pengurusan cuba merekod keputusan | 403 pada route, komponen dan service; tiada rekod |
| T12 | Dua Urus Setia (atau klik berganda) merekod keputusan serentak | Hanya satu rekod; percubaan kedua menerima mesej "status telah berubah" |
| T13 | Manipulasi payload: nilai keputusan tidak sah, status sasaran, atau `decided_by` | 422 / diabaikan; nilai ditentukan di pelayan |
| T14 | Hubungan JTICT | Hanya `IN_JTICT` dipaparkan sebagai "sedia untuk JTICT"; `CLOSED` tidak boleh diproses ke JTICT; tiada perubahan pada `jtict_decision` |
| T15 | Regresi PP1 hingga PP3 | Suite penuh lulus; aliran Tidak Lengkap PP2 dan permohonan bagi pihak agensi PP3 tidak berubah |
| T16 | Permohonan bagi pihak agensi (PP3) melalui Pra JTICT | Keputusan direkod; e-mel (jika ada) kepada Urus Setia pendaftar dan Pegawai Utama agensi sasaran |

**Kriteria penerimaan PP4 [Cadangan]:**

1. Semua senario T1 hingga T16 lulus dalam ujian automatik.
2. UAT manual bagi T1, T4, T6, T7, T8 dan T9.
3. Tiada perubahan pada data sedia ada; hanya satu jadual baharu.

---

## 12. Risiko, Isu dan Kebergantungan

| # | Perkara | Jenis | Kesan jika tidak diselesaikan sebelum PP4 |
|---|---|---|---|
| R1 | Percanggahan dasar Pra JTICT (Seksyen 1.2) | Dasar | Modul dibina berdasarkan andaian yang salah; kerja semula |
| R2 | Pilihan keputusan belum muktamad | Dasar | Enum dan label perlu diubah selepas pembangunan; data sedia ada perlu dipetakan |
| R3 | Maksud Kaji Semula (K3) | Dasar | Aliran status salah; kekeliruan dengan Kaji Semula JTICT (fungsi salin) |
| R4 | Data lama 0001 dan 0002 tanpa `jtict_meeting_id` | Data | Tidak dapat dikumpulkan dalam senarai mengikut mesyuarat |
| R5 | Ambang kos tidak dikuatkuasakan (D16) | Dasar / Data | Permohonan bawah RM 50k mungkin masuk ke Pra JTICT tanpa keperluan |
| R6 | Konflik kepentingan Urus Setia (K14) | Tadbir urus | Pendaftar boleh menyemak, menandakan Lengkap dan memutuskan Pra JTICT bagi permohonan yang sama |
| R7 | Queue worker tidak berjalan secara automatik; Mailtrap mengehadkan kadar (UAT PP3) | Operasi | E-mel keputusan tertunggak atau gagal |
| R8 | Pembetulan keputusan yang salah (K10) | Proses | Rekod tidak boleh diubah; tiada jalan pembetulan rasmi |
| R9 | Peranan pengurusan tiada akses (D15) | Akses | Jangkaan PRD tidak dipenuhi; tidak kritikal (0 pengguna) |
| R10 | Label sejarah "Pemohon" bagi penghantaran semula oleh pegawai (D17) | Paparan | Kecil; boleh dibetulkan bersama paparan sejarah PP4 |
| R11 | Mesyuarat #1 dalam DB pembangunan mempunyai tarikh buka (05/10/2026) selepas tarikh tutup (09/01/2026) | Data | **[Belum Disahkan]** Puncanya mungkin tarikh buka ditetapkan semasa pengaktifan; tidak menjejaskan PP4 secara langsung |
| R12 | Ujian SQLite tidak membuktikan migration MySQL | Teknikal | Perlu sandaran DB dan semakan `--pretend` |

Kebergantungan:

| Kebergantungan | Status |
|---|---|
| PP1, PP2 dan PP3 | Selesai dan lulus UAT |
| Keputusan K1 hingga K5 | **Diperlukan sebelum pembangunan** |
| Modul JTICT dan salin Kaji Semula | Bergantung pada output PP4 (status `IN_JTICT`) |

---

## 13. Perkara yang Memerlukan Keputusan Pemilik Sistem

| # | Soalan | Pilihan / Cadangan |
|---|---|---|
| **K1** | Adakah Pra JTICT satu **sesi mesyuarat** yang direkodkan dalam sistem, atau hanya **keputusan dengan tarikh**? Adakah komen migration *"tidak lagi sebahagian daripada proses"* masih terpakai? | **Cadangan: A** (keputusan dengan tarikh sesi, dikumpul mengikut Mesyuarat JTICT sasaran) |
| **K2** | Senarai pilihan keputusan muktamad dan labelnya | Setuju, Tidak Setuju, Kaji Semula, Batal (prompt); atau Diterima/Ditolak (PP1). Adakah "Setuju Bersyarat" diperlukan? |
| **K3** | Maksud dan aliran **Kaji Semula** Pra JTICT | A: dikembalikan untuk pindaan; B: kekal Lengkap; C: status baharu |
| **K4** | **Batal**: keputusan Pra JTICT atau tindakan pentadbiran berasingan? Siapa boleh membatalkan? Pada status apa? | Cadangan: Urus Setia, dengan sebab wajib. Pertimbangkan fungsi "Batal Permohonan" berasingan. |
| **K5** | E-mel keputusan: keputusan mana yang dimaklumkan, dan kepada siapa (pendaftar, Pegawai Utama, Pegawai Kedua)? | Cadangan: Tidak Setuju, Kaji Semula dan Batal (wajib); Setuju (pilihan); kepada pendaftar dan Pegawai Utama, seperti PP2 |
| **K6** | Adakah ambang kos (bawah RM 50k tidak ke JTICT) dikuatkuasakan pada peringkat Pra JTICT atau permohonan? | Cadangan: fasa berasingan; PP4 hanya memaparkan jumlah kos |
| **K7** | Adakah medan "Catatan Perbincangan" atau "Isu" berasingan diperlukan? | Cadangan: tidak; gunakan Ulasan dan Syor |
| **K8** | Bolehkah Urus Setia memindahkan permohonan ke Mesyuarat JTICT lain semasa atau selepas Pra JTICT? | Perlu keputusan sebelum Modul JTICT |
| **K9** | Bagaimana mengendalikan 0001 dan 0002 (SUBMITTED tanpa mesyuarat)? | Kaitkan secara manual oleh Urus Setia (fungsi kecil) atau dikecualikan |
| **K10** | Bagaimana keputusan yang tersilap direkod dibetulkan? | (a) melalui pembangun; (b) tindakan "Batalkan Keputusan" dengan sebab |
| **K11** | Label status bagi Tidak Setuju dan Batal | Cadangan: "Selesai" dengan keputusan dipaparkan di sebelahnya |
| **K12** | Akses peranan pengurusan kepada senarai dan butiran | Cadangan: lihat sahaja, dalam fasa berasingan |
| **K13** | Adakah pemohon dan agensi melihat keputusan dan ulasan Pra JTICT? | Cadangan: ya, tanpa nama individu |
| **K14** | Konflik kepentingan: Urus Setia pendaftar merekod keputusan Pra JTICT | Kekalkan (tiada sekatan) atau sekat; keputusan dasar tertangguh sejak PP2 |

---

## 14. Cadangan Fasa Pelaksanaan PP4

Dengan andaian K1 hingga K5 telah diputuskan:

| Fasa | Kandungan | Prasyarat | Hasil |
|---|---|---|---|
| **PP4.0** | Keputusan pemilik sistem K1 hingga K5 (dan K9 hingga K11 jika boleh) | Laporan ini | Keputusan bertulis |
| **PP4.1: Data dan logik** | Migration `pre_jtict_decisions`; enum `PreJtictDecision`; model (tidak boleh diubah); `PreJtictDecisionService` (transaksi, kunci, validasi); `ApplicationPolicy::decidePreJtict()`; perubahan enum status hanya jika K3 = A; ujian automatik T2 hingga T15 | PP4.0; sandaran DB | Logik lengkap tanpa UI; suite penuh lulus |
| **PP4.2: Antara muka Urus Setia** | Senarai Pra JTICT, menu, kad papan pemuka, panel keputusan dan modal, sejarah keputusan | PP4.1 | Fungsi boleh digunakan; ujian Livewire T1, T11 dan T12 |
| **PP4.3: Notifikasi dan paparan pemohon** | E-mel keputusan (mengikut K5), paparan keputusan dan ulasan kepada pemohon (K13), pembetulan label D17 | PP4.2 | Ujian `Notification::fake()`; ujian Mailtrap |
| **PP4.4: UAT** | Senarai semak UAT (T1, T4, T6 hingga T9, T16) | PP4.3; queue worker berjalan | Laporan UAT PP4 |

Di luar skop PP4:

- Modul Keputusan JTICT (`jtict_decision`) dan JPICT;
- Salin permohonan Kaji Semula JTICT;
- Penciptaan rekod `projects`;
- Penguatkuasaan ambang kos (K6) dan akses pengurusan (K12), kecuali diputuskan sebaliknya.

---

*Audit sahaja. Tiada perubahan kod, migration, seeder, data, `.env` atau operasi Git dilakukan. Pertanyaan pangkalan data menggunakan SELECT sahaja.*
