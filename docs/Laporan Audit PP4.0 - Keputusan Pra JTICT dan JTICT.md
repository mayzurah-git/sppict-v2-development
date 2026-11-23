# Laporan Audit PP4.0: Keputusan Pra JTICT dan Keputusan Mesyuarat JTICT

**Sistem:** SPPICT v2.1 (Laravel 13, Livewire 3, MySQL)
**Tarikh audit:** 2 November 2026
**Rujukan arahan:** Prompt PP4 (Versi Pindaan), Seksyen 13: PP4.0 sahaja
**Jenis dokumen:** Audit dan cadangan reka bentuk. **Tiada perubahan kod, migration, pangkalan data atau konfigurasi dilakukan.**
**Menggantikan:** *Laporan Audit Perancangan PP4 - Modul Keputusan Pra JTICT.md* (versi asal). Laporan ini menggunakan skop baharu, iaitu dua peringkat keputusan berserta Salin Permohonan. Dapatan versi asal yang masih terpakai dirujuk semula di sini.

Label dapatan dalam laporan ini:

| Label | Maksud |
|---|---|
| **[Disahkan]** | Telah disemak dalam kod atau pangkalan data |
| **[Cadangan]** | Saranan berdasarkan analisis |
| **[Belum Pasti]** | Tidak dapat dipastikan daripada kod atau dokumentasi |
| **[Keputusan Pemilik Sistem]** | Perlu pengesahan anda sebelum PP4.1 |
| **[Risiko]** | Perkara yang boleh menjejaskan data, keselamatan atau modul sedia ada |
| **[Percanggahan]** | Dokumentasi tidak sepadan dengan implementasi, atau dua dokumen bercanggah |

---

## 0. Ringkasan Eksekutif

### 0.1 Kedudukan semasa

1. **[Disahkan] Ketiga-tiga fungsi PP4 belum wujud dalam kod:**
   - keputusan Pra JTICT;
   - keputusan Mesyuarat JTICT;
   - fungsi **Salin Permohonan**.

   Tiada jadual, service, polisi, route, skrin atau e-mel untuk ketiga-tiganya.
2. **[Disahkan] Asas aliran kerja sudah disediakan dalam PP1:**
   - status `COMPLETE` (Lengkap, menunggu Pra JTICT);
   - status `IN_JTICT` (Dalam Proses JTICT);
   - status `CLOSED` (Selesai);
   - peralihan `COMPLETE → IN_JTICT`, `COMPLETE → CLOSED` dan `IN_JTICT → CLOSED`. Peralihan ini belum digunakan oleh mana-mana kod.
3. **[Disahkan] Corak yang boleh dicontohi:**
   - sejarah semakan PP2 (`application_reviews`), yang tidak boleh diubah atau dipadam;
   - service semakan PP2 (transaksi, kunci rekod, semakan polisi, e-mel selepas transaksi).

### 0.2 Penemuan penting yang perlu anda ketahui

| # | Penemuan | Kesan |
|---|---|---|
| 1 | **[Percanggahan] Fungsi Salin Permohonan belum wujud.** Prompt meminta penggunaan "mekanisme sedia ada dalam PP1–PP3", tetapi tiada mekanisme itu dalam kod. Laporan PP0 hanya mencadangkan reka bentuk; ia tidak pernah dibina. | Salin Permohonan perlu **dibina baharu** dalam PP4, sebagai satu mekanisme sahaja. Skop salinan perlu anda sahkan (Seksyen 4). |
| 2 | **[Disahkan] Permohonan "Ditolak dengan Pindaan" tidak boleh menggunakan status Tidak Lengkap begitu sahaja.** Kod PP2 mengaitkan status `NEEDS_AMENDMENT` dengan ulasan semakan kelengkapan. | Pemohon akan melihat **ulasan PP2 yang lama**, bukan ulasan Pra JTICT, dan kedua-dua kes tidak dapat dibezakan (Seksyen 3.4). |
| 3 | **[Percanggahan] Laporan PP1 merancang `applications.jtict_decision` sebagai tempat keputusan JTICT.** Prompt pindaan pula meminta sejarah keputusan yang tidak ditimpa. Lajur itu hanya menyimpan satu nilai, jadi keputusan lama akan ditimpa. | Dicadangkan jadual sejarah baharu. `jtict_decision` tidak disentuh (Seksyen 6). |
| 4 | **[Disahkan] Polisi `updateProgress()` sudah wujud** tetapi membenarkan pegawai bagi **semua** permohonan yang bukan Draf atau Tidak Lengkap. Ia tidak menyemak keputusan JTICT Lulus. Polisi ini belum digunakan di mana-mana. | Bukan isu sekarang, tetapi Modul Kemajuan Projek kelak mesti mensyaratkan JTICT Lulus (Seksyen 9, R6). |
| 5 | **[Disahkan] Prompt pindaan menyelesaikan beberapa persoalan audit asal:** pilihan keputusan kini jelas (3 + 3), "Batal" digugurkan, dan Pra JTICT ialah keputusan Urus Setia, bukan mesyuarat berasingan. | Bilangan keputusan yang perlu anda buat berkurang. |

### 0.3 Cadangan utama (ringkas)

- **Satu jadual sejarah keputusan baharu, `application_decisions`**, dengan lajur `stage` (`PRE_JTICT` atau `JTICT`). Setiap keputusan menjadi satu rekod baharu yang tidak boleh diubah atau dipadam.
- **Satu status proses baharu, "Pindaan Pra JTICT"**, untuk Ditolak dengan Pindaan, supaya ia boleh dibezakan daripada Tidak Lengkap PP2.
- **Satu lajur baharu, `applications.copied_from_application_id`**, untuk menjejak hubungan permohonan asal dengan salinannya.
- Jumlah: **2 migration tambahan.** Tiada data sedia ada diubah atau dipadam.

---

## 1. Skop, Kaedah dan Batasan Audit

| Perkara | Penerangan |
|---|---|
| Kod disemak | `ApplicationStatus`, `Application`, `ApplicationReview`, `ApplicationPolicy`, `ApplicationSubmissionService`, `ApplicationReviewService`, `ApplicationMarkedIncomplete`, `ApplicationForm`, `ViewApplication`, `ApplicationList`, `Meeting`, `routes/web.php`, papan pemuka Urus Setia, `ApplicationStatusTest`, migration teras |
| Carian kata kunci | `salin`, `copy`, `replicate`, `duplicate`, `copied_from`, `Kaji Semula`, `jtict_decision`, `NEEDS_AMENDMENT`, `IN_JTICT`, `CLOSED`, `updateProgress` dalam `app`, `database`, `resources`, `routes`, `tests`, `config` dan `docs` |
| Pangkalan data | `sppictv2_db` disemak melalui skrip PHP **SELECT sahaja** |
| Dokumentasi | PRD, `AGENTS.md`, laporan PP0, PP1, PP2 dan PP3, dan Laporan Audit Perancangan PP4 versi asal |

**Kawalan yang dipatuhi:**
- tiada perubahan kod, `.env` atau konfigurasi;
- tiada migration atau seeder;
- tiada `DELETE`, `DROP` atau `TRUNCATE`;
- tiada operasi Git.

**Batasan:**
- **[Belum Pasti]** Prosedur sebenar BTM tidak didokumenkan, contohnya sama ada keputusan JTICT boleh direkod sebelum tarikh mesyuarat, atau sama ada satu permohonan Kaji Semula boleh disalin lebih daripada sekali.
- Data pembangunan kecil: 8 permohonan dan 2 mesyuarat.
- Ujian automatik projek menggunakan SQLite dan tidak membuktikan tingkah laku MySQL.

---

## 2. Struktur Kod Sedia Ada yang Berkaitan

| Komponen | Peranan sekarang | Kaitan PP4 | Label |
|---|---|---|---|
| `app/Enums/ApplicationStatus.php` | 6 status proses; peta peralihan; `editableCases()` = Draf, Tidak Lengkap | Status Pra JTICT dan JTICT; perlu satu status baharu (Seksyen 3.4) | Disahkan |
| `app/Models/Application.php` | `EDITABLE_STATUSES`, `targetJtictMeeting()`, `requiresMeetingSelection()`, `latestAmendmentReview()` | Tiga fungsi terakhir **khusus untuk `NEEDS_AMENDMENT`** dan perlu diluaskan kepada status pindaan baharu | Disahkan |
| `app/Models/ApplicationReview.php` | Sejarah semakan PP2; tidak boleh diubah atau dipadam (`LogicException`) | **Corak rujukan** untuk jadual keputusan. Jadual ini sendiri tidak digunakan untuk keputusan (arahan prompt Seksyen 5). | Disahkan |
| `app/Services/ApplicationReviewService.php` | Semakan Lengkap atau Tidak Lengkap: validasi ulasan (10–5000 aksara), transaksi, `lockForUpdate`, semakan polisi dan peralihan, e-mel selepas transaksi | **Corak rujukan** untuk `ApplicationDecisionService` | Disahkan |
| `app/Services/ApplicationSubmissionService.php` | Simpan draf, hantar dan hantar semula. Hanya status yang boleh beralih kepada `SUBMITTED` boleh dihantar. Rekod sejarah hantar semula **hanya jika status sebelumnya `NEEDS_AMENDMENT`**. `nextReferenceNumber()` ialah kaedah `private`. | (a) Hantar semula selepas Pindaan Pra JTICT perlu direkod. (b) Salin Permohonan memerlukan nombor rujukan draf. | Disahkan |
| `app/Policies/ApplicationPolicy.php` | `create`, `createOnBehalf`, `view`, `update`, `review`, `manageOfficers`, `updateProgress` | Tambah `decidePreJtict`, `decideJtict` dan `copy`. Polisi lain tidak diubah. | Disahkan |
| `app/Livewire/ViewApplication.php` dan view | Panel semakan PP2, sejarah semakan, ulasan Tidak Lengkap (`amendmentRemarks()` hanya bagi `NEEDS_AMENDMENT`) | Tambah panel keputusan, sejarah keputusan dan butang Salin | Disahkan |
| `app/Livewire/ApplicationForm.php` | `isAmendment()` dan `amendmentReview()` memaparkan ulasan Tidak Lengkap semasa pindaan, **hanya bagi `NEEDS_AMENDMENT`** | Perlu memaparkan ulasan Pra JTICT semasa pindaan | Disahkan |
| `app/Livewire/ApplicationList.php` | Urus Setia: semua status, dengan penapis. Pengguna biasa: permohonan sendiri dan permohonan di mana dia pegawai aktif. | Senarai Urus Setia untuk Pra JTICT dan JTICT | Disahkan |
| `resources/views/dashboard/urus-setia.blade.php` | Kad bilangan bagi **setiap** status dalam enum selain Draf | Status baharu akan **muncul sebagai kad secara automatik** | Disahkan |
| `resources/views/components/application-status-badge.blade.php` | Warna lencana mengikut status | Perlu warna bagi status baharu | Disahkan |
| `app/Notifications/ApplicationMarkedIncomplete.php` | E-mel Tidak Lengkap; `ShouldQueue` dan `afterCommit()` | Corak untuk e-mel keputusan | Disahkan |
| `app/Models/Meeting.php` | `scopeJtict`, `openForSubmission`, `applications()` melalui `jtict_meeting_id`, `meeting_date` | Rujukan mesyuarat bagi keputusan JTICT | Disahkan |
| Fungsi Salin Permohonan | **Tidak wujud.** Tiada `replicate()`, route, polisi `copy` atau butang. | Perlu dibina | Disahkan |
| `routes/web.php` | Menu Urus Setia di bawah `role:pentadbir_urus_setia`. Route butiran dan suntingan permohonan menyekat superadmin (`not-role:superadmin`). | Route baharu mengikut corak yang sama | Disahkan |

---

## 3. Status dan Peralihan

### 3.1 Status sedia ada [Disahkan]

| Nilai | Label | Boleh disunting pemohon | Peralihan dibenarkan | Digunakan oleh kod? |
|---|---|---|---|---|
| `DRAFT` | Draf | Ya | → `SUBMITTED` | Ya |
| `SUBMITTED` | Dihantar | Tidak | → `NEEDS_AMENDMENT`, `COMPLETE` | Ya (PP2) |
| `NEEDS_AMENDMENT` | Tidak Lengkap | Ya | → `SUBMITTED` | Ya (PP2) |
| `COMPLETE` | Lengkap | Tidak | → `IN_JTICT`, `CLOSED` | **Tidak** (tiada pencetus) |
| `IN_JTICT` | Dalam Proses JTICT | Tidak | → `CLOSED` | **Tidak** |
| `CLOSED` | Selesai | Tidak | (tiada) | **Tidak** |

**[Disahkan] Ujian pengawal yang mesti diambil kira** (`tests/Feature/ApplicationStatusTest.php`):

| Ujian | Apa yang diperiksa | Kesan jika status ditambah |
|---|---|---|
| `test_status_values_and_malay_labels` | Senarai tepat status dan labelnya | **Perlu dikemas kini** |
| `test_process_status_does_not_contain_meeting_decisions` | Status tidak boleh bernilai atau berlabel keputusan (`APPROVED`, `REJECTED`, `REVIEW`, "Lulus", "Kaji Semula", "Diterima", "Ditolak" dan lain-lain) | Status baharu **tidak boleh** dinamakan seperti keputusan |
| `test_allowed_transitions_follow_workflow` | Setiap pasangan dari dan ke | **Perlu dikemas kini** |
| `test_editable_statuses_come_from_enum` | `EDITABLE_STATUSES` = `editableCases()` | `EDITABLE_STATUSES` perlu dikemas kini |

### 3.2 Keadaan yang diminta prompt dan cara mewakilinya [Cadangan]

Prinsip PP1 dikekalkan: **status = kedudukan dalam aliran kerja; keputusan = rekod berasingan.** Ini juga dikehendaki oleh prompt Seksyen 6 ("bezakan status proses daripada keputusan").

| Peringkat | Keadaan (prompt) | Status proses | Rekod keputusan |
|---|---|---|---|
| PP2 | Lengkap, menunggu Pra JTICT | `COMPLETE` (sedia ada) | Tiada lagi |
| Pra JTICT | Diterima | `IN_JTICT` (sedia ada) | `PRE_JTICT` / `ACCEPTED` |
| Pra JTICT | Ditolak dengan Pindaan | **`PRE_JTICT_AMENDMENT` (baharu)**, label "Pindaan Pra JTICT" | `PRE_JTICT` / `AMENDMENT_REQUIRED` |
| Pra JTICT | Ditolak | `CLOSED` (sedia ada) | `PRE_JTICT` / `REJECTED` |
| JTICT | Menunggu keputusan mesyuarat | `IN_JTICT` (sedia ada) | `PRE_JTICT` / `ACCEPTED` (terkini) |
| JTICT | Lulus | `CLOSED` | `JTICT` / `APPROVED` |
| JTICT | Tidak Lulus | `CLOSED` | `JTICT` / `NOT_APPROVED` |
| JTICT | Kaji Semula | `CLOSED` | `JTICT` / `REVIEW` |

**Paparan:** status dan keputusan dipaparkan bersama supaya `CLOSED` tidak berdiri sendiri. Contohnya:
- "Selesai · Ditolak (Pra JTICT)";
- "Selesai · Lulus (JTICT)";
- "Selesai · Kaji Semula (JTICT)".

### 3.3 Peralihan baharu yang diperlukan [Cadangan]

| Peralihan | Sebab | Sudah dalam enum? |
|---|---|---|
| `COMPLETE → IN_JTICT` | Pra JTICT Diterima | ✅ |
| `COMPLETE → CLOSED` | Pra JTICT Ditolak | ✅ |
| `COMPLETE → PRE_JTICT_AMENDMENT` | Pra JTICT Ditolak dengan Pindaan | ❌ Baharu |
| `PRE_JTICT_AMENDMENT → SUBMITTED` | Pemohon menghantar semula selepas pindaan; ia kemudian melalui semakan PP2 semula | ❌ Baharu |
| `IN_JTICT → CLOSED` | Keputusan JTICT (Lulus, Tidak Lulus atau Kaji Semula) | ✅ |

### 3.4 Mengapa Ditolak dengan Pindaan memerlukan status sendiri [Disahkan + Cadangan]

**Pilihan A: guna semula `NEEDS_AMENDMENT` (Tidak Lengkap).** Tidak disyorkan. Kod sedia ada akan bertindak salah:

| Lokasi | Tingkah laku semasa | Akibat jika Pra JTICT menggunakan `NEEDS_AMENDMENT` |
|---|---|---|
| `ViewApplication::amendmentRemarks()` | Mengambil ulasan **semakan PP2** terkini yang bernilai `NEEDS_AMENDMENT`, atau `review_remarks` | Pemohon melihat **ulasan PP2 lama**, bukan ulasan Pra JTICT |
| `ApplicationForm::amendmentReview()` | `latestAmendmentReview` daripada `application_reviews` | Sama seperti di atas, dalam borang pindaan |
| Label dan lencana | "Tidak Lengkap" (ungu) | Tidak dapat dibezakan daripada PP2, sedangkan prompt Seksyen 6 mewajibkannya |
| Kad papan pemuka dan penapis | Satu kiraan "Tidak Lengkap" | Bilangan bercampur |
| `ApplicationList` `was_returned` | Penanda "pernah dikembalikan" dalam PP2 | Tidak tepat |

**Pilihan B: status baharu `PRE_JTICT_AMENDMENT` ("Pindaan Pra JTICT").** **Disyorkan.**

- Boleh disunting, sama seperti Tidak Lengkap, dan hanya boleh beralih kepada `SUBMITTED`.
- Labelnya menerangkan **kedudukan proses**, bukan keputusan. Oleh itu ujian `test_process_status_does_not_contain_meeting_decisions` kekal sah.
- Kod PP2 yang perlu dilaraskan supaya "status pindaan" merangkumi kedua-dua status:

| Fail | Pelarasan |
|---|---|
| `ApplicationStatus` | Status baharu, label, penerangan, peralihan, `editableCases()` |
| `Application` | `EDITABLE_STATUSES`; `targetJtictMeeting()` dan `requiresMeetingSelection()` supaya peraturan mesyuarat semasa hantar semula (PP2 3.4) juga terpakai |
| `ApplicationSubmissionService` | Rekod sejarah hantar semula juga bagi status baharu |
| `ApplicationReview::isResubmission()` dan `actionLabel()` | Kenal pasti hantar semula daripada Pindaan Pra JTICT |
| `ApplicationForm` dan `ViewApplication` | Paparkan **ulasan Pra JTICT** apabila status ialah Pindaan Pra JTICT |
| `SubmissionClosedException` | Mesej "kekal pada status semasa" |
| Lencana status | Warna baharu |
| `ApplicationStatusTest` | Kemas kini ujian dengan justifikasi |

**[Keputusan Pemilik Sistem, P1]** Pilih A atau B. Cadangan: **B**.

### 3.5 Kitaran Ditolak dengan Pindaan [Cadangan]

```
COMPLETE ──Pra JTICT: Ditolak dengan Pindaan (ulasan wajib)──► PRE_JTICT_AMENDMENT
                                                                    │ pemohon/pegawai pinda
                                                                    ▼
                                    SUBMITTED ◄── hantar semula (ID sama; rekod application_reviews "Dihantar Semula")
                                        │ semakan kelengkapan PP2 (tidak berubah)
                                        ▼
                                    COMPLETE ──► Pra JTICT semula (rekod keputusan BAHARU; rekod lama kekal)
```

- ID, UUID dan nombor rujukan permohonan **kekal sama**. Tiada rekod permohonan baharu dicipta.
- Jika PP2 menetapkan Tidak Lengkap dalam pusingan ini, kitaran Tidak Lengkap PP2 sedia ada digunakan seperti biasa.

---

## 4. Fungsi Salin Permohonan

### 4.1 Dapatan [Disahkan]

- **Tidak wujud dalam kod.** Carian `replicate`, `copy`, `salin`, `duplicate` dan `copied_from` dalam `app`, `routes`, `resources`, `database` dan `tests` tidak menemui sebarang fungsi salinan permohonan.
- **Tiada lajur** yang menghubungkan permohonan asal dengan salinannya.
- **[Percanggahan]** Prompt Seksyen 3.2C dan 9 merujuk kepada "mekanisme sedia ada dalam PP1–PP3" dan "skop data yang telah ditetapkan bagi fungsi Salin Permohonan sedia ada". Kedua-duanya tidak wujud. Satu-satunya rujukan ialah **cadangan** dalam Laporan Audit PP0 Seksyen 13, yang tidak pernah disahkan atau dibina. Laporan PP1, PP2 dan PP3 menyenaraikan salinan Kaji Semula sebagai "di luar skop".
- Maksud arahan "jangan membina mekanisme salinan kedua" difahami sebagai: **bina satu mekanisme sahaja**, digunakan untuk Kaji Semula JTICT.

### 4.2 Bahan sedia ada yang boleh digunakan semula [Disahkan]

| Bahan | Kegunaan dalam salinan |
|---|---|
| `ApplicationSubmissionService::nextReferenceNumber(isDraft: true)` | Nombor rujukan draf baharu (`SPPICT/2026/DRAFT-xxxx`). Kaedah ini `private` dan perlu dijadikan boleh dikongsi, contohnya dipindahkan ke kelas kecil. |
| `fillApplication()` dan `costSummary()` | Kira semula jumlah kos dan SST, bukan salin nombor sahaja |
| `ApplicationOfficer::snapshotFrom()` dan `User::eligibleOfficersFor()` | Jika pegawai disalin: hanya pegawai yang masih layak dalam agensi |
| `Agency::active()` | Pastikan agensi asal masih aktif |

### 4.3 Cadangan reka bentuk Salin Permohonan [Cadangan]

| Perkara | Cadangan |
|---|---|
| Syarat | Keputusan JTICT **terkini** permohonan asal = `REVIEW` (Kaji Semula) dan status `CLOSED` |
| Siapa boleh salin | Pendaftar asal (`applicant_id`) **atau** Pegawai Utama atau Kedua aktif yang masih dalam agensi permohonan. Ini sama seperti polisi `update` selepas UAT PP3. Pengguna juga mesti lulus polisi `create`. **[Keputusan Pemilik Sistem, P6]** |
| Rekod baharu | UUID baharu, ID baharu, nombor rujukan draf baharu, status `DRAFT`, `applicant_id` = pengguna yang menyalin, `agency_id` = agensi asal (mesti aktif), dan `copied_from_application_id` = ID asal |
| Medan disalin | Fasa 1–2 (tajuk, kategori, objektif, skop, jenis dan kaedah perolehan, kos siling, anggaran kos, tempoh, kod hasil), `sst_rate`, dan Fasa 3 (perincian item; rekod baharu dengan UUID baharu) |
| Tidak disalin | `jtict_meeting_id` (pemohon memilih semasa menghantar), `review_remarks`, `jtict_*`, `jpict_*`, sejarah semakan dan sejarah keputusan |
| Dokumen | **[Keputusan Pemilik Sistem, P5]** Cadangan: **tidak disalin**. Kertas cadangan dan slaid perlu dikemas kini untuk mesyuarat baharu, dan fail tidak dikongsi antara dua rekod. |
| Pegawai | **[Keputusan Pemilik Sistem, P5]** Cadangan: disalin **hanya jika** pegawai masih aktif dan layak dalam agensi. Jika tidak, pemohon memilih semula. |
| Rekod asal | **Dibaca sahaja.** Ia dikunci dengan `lockForUpdate` untuk mengelak salinan serentak; tiada `save()` dibuat pada rekod asal. |
| Bilangan salinan | **[Keputusan Pemilik Sistem, P6]** Cadangan: **satu salinan sahaja** bagi setiap permohonan Kaji Semula, untuk mengelak permohonan pendua. Selepas disalin, butang bertukar kepada pautan "Lihat salinan: SPPICT/…" |
| Master data tidak aktif | **[Keputusan Pemilik Sistem, P7]** Kosongkan nilai yang telah tidak aktif supaya pemohon memilih semula, atau kekalkannya |
| Hubungan | Permohonan baharu memaparkan "Disalin daripada SPPICT/…"; permohonan asal memaparkan "Salinan: SPPICT/…". Sejarah keputusan **tidak dicampur**: setiap rekod mempunyai sejarahnya sendiri. |

---

## 5. Kawalan Akses Sedia Ada

### 5.1 Dapatan [Disahkan]

| Perkara | Dapatan |
|---|---|
| Model kebenaran | **Peranan + Policy.** Permission Spatie seperti `update-meeting-decisions` dan `recommend-to-jpict` diberikan kepada Urus Setia dan superadmin dalam DB, tetapi **tidak dirujuk** dalam kod. |
| `review()` (PP2) | Urus Setia sahaja; superadmin dan pengurusan dikecualikan walaupun memegang peranan lain; status mesti `SUBMITTED`. Skop: semua agensi. |
| `view()` | Pendaftar, Urus Setia, pengguna dalam agensi yang sama, atau pegawai aktif |
| `update()` | Draf atau Tidak Lengkap, oleh pendaftar atau pegawai aktif dalam agensi yang sama |
| `updateProgress()` | Pegawai aktif dalam agensi yang sama, bagi permohonan yang **bukan** Draf atau Tidak Lengkap. **Tidak menyemak keputusan JTICT.** Tidak digunakan di mana-mana. |
| Route | Butiran dan suntingan permohonan: `not-role:superadmin`. Menu Urus Setia: `role:pentadbir_urus_setia`. |
| Bilangan pengguna (DB) | pengguna_biasa 10, pentadbir_urus_setia 1, superadmin 1, pengurusan 0 |
| Senarai pengguna biasa | Permohonan sendiri dan permohonan di mana dia pegawai aktif. Rakan agensi boleh **melihat** butiran (polisi `view`) tetapi permohonan itu tidak tersenarai. |

### 5.2 Cadangan polisi baharu [Cadangan]

Polisi baharu mengikut corak `review()`. **Polisi sedia ada tidak diubah.**

| Polisi | Syarat |
|---|---|
| `decidePreJtict` | `canUseSystem`, peranan `pentadbir_urus_setia`, bukan superadmin atau pengurusan, status `COMPLETE` |
| `decideJtict` | Sama seperti di atas, status `IN_JTICT`, **dan** keputusan Pra JTICT terkini = Diterima |
| `copy` | Lihat Seksyen 4.3 |

| Fungsi | Superadmin | Urus Setia | Pengguna Biasa | Pengurusan |
|---|---|---|---|---|
| Senarai Pra JTICT dan JTICT | ❌ | ✅ | ❌ | ❌ (kekal; P11) |
| Rekod keputusan Pra JTICT dan JTICT | ❌ | ✅ | ❌ | ❌ |
| Lihat keputusan dan ulasan | ❌ (Log Audit sahaja) | ✅ dengan nama pegawai | ✅ bagi permohonan yang boleh dilihat (polisi `view`), **tanpa nama individu** seperti sejarah PP2 | ❌ |
| Pinda dan hantar semula (Pindaan Pra JTICT) | ❌ | Jika pendaftar (bagi pihak agensi, PP3) | ✅ pendaftar atau pegawai aktif | ❌ |
| Salin Permohonan (Kaji Semula) | ❌ | Jika pendaftar (P6) | ✅ pendaftar atau pegawai aktif (P6) | ❌ |
| Ubah atau padam rekod keputusan | ❌ | ❌ | ❌ | ❌ |

**Kawalan pelayan [Cadangan]:**
- Polisi disemak di route, di komponen Livewire, **dan semula di dalam transaksi service** selepas rekod dikunci, seperti PP2.
- Nilai keputusan diterima hanya daripada senarai enum.
- Status sasaran, peringkat dan `decided_by` **ditentukan di pelayan** dan tidak dibaca daripada payload.

---

## 6. Cadangan Reka Bentuk bagi Dua Peringkat Keputusan

### 6.1 Satu jadual dengan peringkat, berbanding dua jadual

| Pilihan | Kebaikan | Kelemahan |
|---|---|---|
| **A. Satu jadual `application_decisions` dengan lajur `stage`** (disyorkan) | Satu model, satu peraturan "tidak boleh diubah", satu service dan satu komponen sejarah. Sepadan dengan senarai medan prompt Seksyen 5, yang memasukkan "Jenis peringkat keputusan (Pra JTICT atau JTICT)". | Keputusan bagi setiap peringkat mesti disahkan dalam kod, bukan dalam DB |
| B. Dua jadual (`pre_jtict_decisions`, `jtict_decisions`) | Pemisahan fizikal | Kod dan ujian berganda; paparan sejarah perlu menggabungkan dua sumber |

Kedua-dua pilihan memenuhi syarat "disimpan dan dipaparkan secara berasingan", kerana setiap keputusan ialah rekod sendiri yang ditanda dengan peringkatnya.

**[Keputusan Pemilik Sistem, P4]** Cadangan: **A**.

### 6.2 Enum keputusan [Cadangan]

| Peringkat | Kod | Label |
|---|---|---|
| `PRE_JTICT` | `ACCEPTED` | Diterima |
| `PRE_JTICT` | `AMENDMENT_REQUIRED` | Ditolak dengan Pindaan |
| `PRE_JTICT` | `REJECTED` | Ditolak |
| `JTICT` | `APPROVED` | Lulus |
| `JTICT` | `NOT_APPROVED` | Tidak Lulus |
| `JTICT` | `REVIEW` | Kaji Semula |

Enum `ApplicationDecision` (keputusan) dan `DecisionStage` (peringkat) ialah **enum berasingan** daripada `ApplicationStatus`. Prinsip ujian PP1 dikekalkan.

### 6.3 Peraturan keputusan [Cadangan]

| Keputusan | Dari | Ke | Ulasan | Tindakan susulan |
|---|---|---|---|---|
| Pra JTICT Diterima | `COMPLETE` | `IN_JTICT` | Pilihan | Masuk senarai JTICT |
| Pra JTICT Ditolak dengan Pindaan | `COMPLETE` | `PRE_JTICT_AMENDMENT` | **Wajib** (10–5000 aksara, seperti PP2) | Pemohon meminda dan menghantar semula melalui PP2 |
| Pra JTICT Ditolak | `COMPLETE` | `CLOSED` | **Wajib** | Tiada hantar semula. Polisi `update` sedia ada sudah menyekat kerana `CLOSED` tidak boleh disunting. |
| JTICT Lulus | `IN_JTICT` | `CLOSED` | **[P15]** cadangan: pilihan | Layak ke Modul Kemajuan Projek (dikenal pasti melalui rekod keputusan) |
| JTICT Tidak Lulus | `IN_JTICT` | `CLOSED` | **Wajib** | Tiada hantar semula atau salinan |
| JTICT Kaji Semula | `IN_JTICT` | `CLOSED` | **Wajib** | Butang Salin Permohonan |

**[Percanggahan]** Laporan PP1 Seksyen 4 menetapkan ulasan **wajib bagi ketiga-tiga** keputusan JTICT. Prompt pindaan hanya menyebut "memasukkan ulasan atau alasan". Perlu disahkan (P15).

**Prinsip:**
- Setiap keputusan = satu rekod baharu.
- Keputusan hanya boleh dibuat daripada status yang betul. Selepas status berubah, keputusan berganda tidak boleh berlaku; ini dikawal oleh kunci rekod seperti PP2.

### 6.4 `applications.jtict_decision` [Disahkan + Cadangan]

**Audit impak [Disahkan]:**
- ENUM MySQL (`PENDING`, `APPROVED`, `APPROVED_CONDITIONAL`, `REVIEW`, `REJECTED`); semua 8 rekod bernilai `PENDING`.
- **Tiada kod yang menulis atau membaca** nilai ini, selain senarai `$fillable` dan satu ujian PP2 yang memastikan hantar semula tidak mengubahnya.

**Cadangan:**
- **Jangan gunakan** dalam PP4. Biarkan bernilai `PENDING` dan anggap sebagai lajur warisan.
- Satu-satunya sumber keputusan ialah `application_decisions`. Jika kedua-duanya digunakan, wujud dua sumber kebenaran yang boleh bercanggah.

**[Keputusan Pemilik Sistem, P3]** Jika laporan atau modul lain memerlukan lajur ringkasan, ia boleh diselaraskan dalam transaksi yang sama. Lajur ini tidak mempunyai nilai `NOT_APPROVED`, jadi Tidak Lulus perlu dipetakan kepada `REJECTED`.

### 6.5 Hubungan dengan Mesyuarat JTICT [Disahkan + Cadangan]

- **[Disahkan]** Setiap permohonan sudah dikaitkan dengan satu Mesyuarat JTICT (`jtict_meeting_id`), yang ditetapkan semasa dihantar.
- **[Cadangan]** Rekod keputusan menyimpan `meeting_id`, iaitu Mesyuarat JTICT permohonan pada masa keputusan dibuat. Senarai Urus Setia boleh ditapis mengikut mesyuarat (prompt 8.1).
- **[Keputusan Pemilik Sistem, P8]**
  - (a) Adakah keputusan JTICT hanya dibenarkan **pada atau selepas** `meeting_date`?
  - (b) Bolehkah Urus Setia memindahkan permohonan `IN_JTICT` ke mesyuarat lain, contohnya jika agenda penuh? Ini fungsi baharu dan tidak dicadangkan dalam PP4.
- **[Disahkan]** Permohonan 0001 dan 0002 berstatus `SUBMITTED` **tanpa** mesyuarat (data lama). Jika ditanda Lengkap, mereka tiada mesyuarat untuk keputusan JTICT. **[Keputusan Pemilik Sistem, P14]**

---

## 7. Cadangan Migration dan Model

### 7.1 Migration 1: `create_application_decisions_table` [Cadangan; tidak dilaksanakan]

| Lajur | Jenis | Tujuan |
|---|---|---|
| `id` | bigint PK | ID rekod |
| `uuid` | uuid, unique | Konvensyen projek |
| `application_id` | FK → `applications`, **restrict**, tanpa cascade | Permohonan |
| `stage` | varchar(20) | `PRE_JTICT` atau `JTICT` |
| `decision` | varchar(30) | Kod keputusan (6.2). VARCHAR mengikut corak PP1, bukan ENUM MySQL. |
| `from_status`, `to_status` | varchar(30) | Kesan terhadap status proses |
| `remarks` | text, nullable | Ulasan atau alasan |
| `decision_date` | date | Tarikh keputusan, iaitu tarikh sesi Pra JTICT atau tarikh mesyuarat JTICT |
| `meeting_id` | FK → `meetings`, nullable, restrict | Mesyuarat JTICT berkaitan |
| `decided_by` | FK → `users` | Urus Setia yang merekod |
| `created_at` | timestamp | Masa rekod. **Tiada `updated_at`**: rekod tidak boleh diubah. |

Indeks: `(application_id, stage, created_at)`, `(stage, decision)` dan `(meeting_id)`.

### 7.2 Migration 2: `add_copied_from_application_id_to_applications_table` [Cadangan; tidak dilaksanakan]

| Lajur | Jenis | Nota |
|---|---|---|
| `copied_from_application_id` | FK nullable → `applications`, restrict | Tambahan sahaja; semua rekod sedia ada kekal `NULL`. **Unique** jika P6 = satu salinan sahaja (MySQL membenarkan banyak `NULL` dalam indeks unique). |

**Mengapa lajur pada `applications`, bukan jadual berasingan:** hubungan ini ialah sifat tetap rekod salinan (satu asal sahaja). Satu lajur nullable ialah perubahan paling kecil dan boleh dibaca terus oleh senarai dan butiran permohonan.

### 7.3 Model, enum, service dan polisi [Cadangan]

| Komponen | Baharu / Diubah | Nota |
|---|---|---|
| `app/Enums/DecisionStage.php`, `app/Enums/ApplicationDecision.php` | Baharu | Label, peringkat bagi setiap keputusan, status sasaran, dan sama ada ulasan wajib |
| `app/Models/ApplicationDecision.php` | Baharu | Corak `ApplicationReview`: `UPDATED_AT = null`, dan `LogicException` pada `updating` dan `deleting` |
| `Application` | Diubah | Hubungan `decisions()`, `latestPreJtictDecision()`, `latestJtictDecision()`, `copiedFrom()`, `copies()`; pelarasan status pindaan (3.4) |
| `app/Services/ApplicationDecisionService.php` | Baharu | `decide()`: validasi, transaksi, `lockForUpdate`, polisi dan peralihan disemak semula, rekod sejarah, e-mel selepas commit |
| `app/Services/ApplicationCopyService.php` | Baharu | Seksyen 4.3 |
| Nombor rujukan | Diubah | `nextReferenceNumber()` dijadikan boleh dikongsi tanpa mengubah formatnya |
| `ApplicationPolicy` | Diubah (kaedah baharu sahaja) | `decidePreJtict`, `decideJtict`, `copy` |
| `ApplicationStatus` | Diubah | Status `PRE_JTICT_AMENDMENT` (jika P1 = B) |

---

## 8. Kesan kepada PP1, PP2 dan PP3

| Modul | Perubahan dijangka | Tahap | Kawalan |
|---|---|---|---|
| **PP1 (status)** | Status dan dua peralihan baharu; kemas kini 3 ujian `ApplicationStatusTest` (justifikasi dilaporkan); lencana dan kad papan pemuka | Sederhana | Suite penuh; tiada data status sedia ada diubah |
| **PP2 (semakan)** | Logik "status pindaan" diluaskan dalam `Application`, `ApplicationSubmissionService`, `ApplicationReview`, `ApplicationForm` dan `ViewApplication` (3.4). **Logik semakan Lengkap atau Tidak Lengkap tidak berubah.** `application_reviews` tidak berubah strukturnya. | Sederhana | Ujian PP2 sedia ada mesti lulus tanpa diubah. Ujian baharu bagi kitaran Pindaan Pra JTICT. |
| **PP3 (bagi pihak agensi)** | Tiada perubahan logik. Bagi permohonan bagi pihak agensi, "pemohon" = Urus Setia pendaftar, jadi e-mel dan butang Salin perlu mengambil kira Pegawai Utama agensi. | Rendah | Ujian T19 dan T21 |
| **Modul Mesyuarat** | Tiada perubahan | Tiada | Dibaca sahaja |
| **`jtict_decision` dan ujian PP2 berkaitan** | Tidak disentuh (P3) | Tiada | Ujian sedia ada kekal |

Fail tambahan yang dijangka:
- 2 migration;
- 2 enum, 1 model, 2 service, 1–2 notifikasi;
- komponen Livewire senarai keputusan, dan route `urus-setia/keputusan` atau yang setara;
- panel dalam `ViewApplication`.

---

## 9. Risiko Data dan Keselamatan

| # | Risiko | Tahap | Kawalan dicadangkan |
|---|---|---|---|
| R1 | Pra JTICT menggunakan `NEEDS_AMENDMENT` (P1 = A) menyebabkan ulasan PP2 lama dipaparkan kepada pemohon | Tinggi | Pilihan B |
| R2 | Keputusan berganda akibat klik berganda atau dua Urus Setia serentak | Sederhana | `lockForUpdate` dan semakan status dalam transaksi, seperti PP2 |
| R3 | Manipulasi payload: keputusan atau peringkat tidak sah, status sasaran, `decided_by`, atau ID permohonan lain | Sederhana | Nilai daripada enum sahaja; status dan pengguna ditentukan pelayan; polisi disemak semula dalam service |
| R4 | Keputusan JTICT direkod tanpa Pra JTICT Diterima | Sederhana | `decideJtict` mensyaratkan `IN_JTICT` **dan** keputusan Pra JTICT terkini = Diterima |
| R5 | Salinan berganda atau salinan daripada keputusan bukan Kaji Semula | Sederhana | Polisi `copy`, kunci rekod asal, dan indeks unique (P6) |
| R6 | `updateProgress()` membenarkan pegawai bagi semua permohonan yang telah dihantar (tidak menyemak Lulus) | Rendah sekarang (tidak digunakan); **tinggi** apabila Modul Kemajuan Projek dibina | Dilaporkan sahaja. Polisi tidak diubah dalam PP4 (arahan). Modul seterusnya mesti mensyaratkan JTICT Lulus. |
| R7 | Pembetulan keputusan tersilap tidak mungkin, kerana rekod tidak boleh diubah dan `CLOSED` tiada peralihan keluar | Sederhana | **[P10]** (a) melalui pembangun dengan prosedur rasmi; atau (b) tindakan "Batalkan Keputusan" yang mencipta rekod pembetulan |
| R8 | Konflik kepentingan: Urus Setia yang mendaftar permohonan bagi pihak agensi juga merekod keputusan | Tadbir urus | **[P12]** Isu tertangguh sejak PP2. Tiada sekatan ditambah tanpa keputusan anda. |
| R9 | Mailtrap mengehadkan kadar e-mel; queue worker tidak berjalan secara automatik (UAT PP3) | Operasi | E-mel tertunggak boleh dicuba semula (`queue:retry`). **Tiada perubahan konfigurasi diperlukan.** |
| R10 | Ambang kos (`AGENTS.md`) tidak dikuatkuasakan, jadi permohonan bawah RM 50k boleh masuk Pra JTICT; permohonan ≥ RM 500k perlu ke JPICT selepas JTICT | Dasar | **[P13]** Di luar skop PP4 melainkan diputuskan |
| R11 | Migration pada MySQL berbeza daripada SQLite (ujian) | Rendah (jadual baharu dan lajur nullable sahaja) | Sandaran DB, `migrate --pretend` dan semakan anda sebelum `migrate` |
| R12 | Data ujian PP4 bercampur dengan data UAT sedia ada | Rendah | Ujian automatik guna SQLite in-memory; data UAT manual dikenal pasti, tidak dipadam secara automatik |
| R13 | Data lama 0001 dan 0002 tanpa mesyuarat | Rendah | P14 |

---

## 10. Perkara yang Memerlukan Pengesahan Pemilik Sistem

| # | Soalan | Cadangan |
|---|---|---|
| **P1** | Status bagi Ditolak dengan Pindaan: guna semula Tidak Lengkap (A) atau status baharu "Pindaan Pra JTICT" (B)? | **B** (3.4) |
| **P2** | Status akhir bagi Lulus, Tidak Lulus dan Kaji Semula JTICT: semua `CLOSED` dengan keputusan dipaparkan bersama? | **Ya**. Peralihan sudah wujud; tiada status baharu. |
| **P3** | `jtict_decision`: biarkan `PENDING` (warisan) atau selaraskan sebagai ringkasan? | **Biarkan** |
| **P4** | Satu jadual dengan peringkat (A) atau dua jadual (B)? | **A** |
| **P5** | Skop salinan: adakah dokumen dan pegawai disalin? | Dokumen **tidak**; pegawai **ya** jika masih layak |
| **P6** | Siapa boleh menyalin, dan berapa kali? | Pendaftar atau pegawai aktif dalam agensi; **sekali** |
| **P7** | Master data tidak aktif semasa disalin: kosongkan atau kekalkan? | **Kosongkan** supaya dipilih semula |
| **P8** | (a) Keputusan JTICT hanya pada atau selepas tarikh mesyuarat? (b) Pindah permohonan ke mesyuarat lain? | (a) Ya; (b) di luar skop PP4 |
| **P9** | Penerima e-mel keputusan | Pemohon (pendaftar) dan Pegawai Utama aktif, seperti PP2. Pegawai Kedua: ya/tidak? |
| **P10** | Cara membetulkan keputusan tersilap | (a) melalui pembangun dahulu; (b) dalam fasa kemudian |
| **P11** | Akses peranan pengurusan | Kekal tiada akses dalam PP4 |
| **P12** | Konflik kepentingan Urus Setia pendaftar | Kekal tanpa sekatan sehingga diputuskan |
| **P13** | Ambang kos dan JPICT | Di luar skop PP4 |
| **P14** | Permohonan 0001 dan 0002 tanpa mesyuarat | Dikecualikan daripada UAT PP4, atau dikaitkan melalui fungsi kecil yang berasingan |
| **P15** | Ulasan JTICT: wajib bagi ketiga-tiga keputusan (PP1) atau hanya Tidak Lulus dan Kaji Semula? | Wajib bagi Tidak Lulus dan Kaji Semula; pilihan bagi Lulus |
| **P16** | Adakah Tarikh Keputusan dimasukkan oleh Urus Setia (boleh berbeza daripada tarikh rekod) atau ditetapkan secara automatik? | Dimasukkan, dengan lalai hari ini. Bagi JTICT, lalai = tarikh mesyuarat. |
| **P17** | Selepas Pindaan Pra JTICT, jika Mesyuarat JTICT asal sudah ditutup, pemohon memilih mesyuarat aktif (peraturan PP2 sedia ada)? | **Ya**, kekalkan peraturan PP2 |
| **P18** | Adakah JTICT Lulus perlu terus mencipta rekod `projects`? | **Tidak** dalam PP4. Modul Kemajuan Projek mengenal pasti melalui rekod keputusan. Jadual `projects` sedia ada (0 rekod) disentuh dalam modul itu nanti. |

---

## 11. Ujian Penerimaan yang Dicadangkan

### 11.1 Ujian automatik (PHPUnit; SQLite in-memory)

| # | Senario | Hasil dijangka |
|---|---|---|
| T1 | Permohonan `COMPLETE` dibuka oleh Urus Setia | Panel Pra JTICT dipaparkan; status lain tiada panel; panggilan terus ditolak |
| T2 | Pra JTICT Diterima | `IN_JTICT`; rekod `PRE_JTICT/ACCEPTED`; `jtict_decision` kekal `PENDING` |
| T3 | Ditolak dengan Pindaan tanpa ulasan | Ralat validasi; tiada rekod; status tidak berubah |
| T4 | Ditolak dengan Pindaan dengan ulasan | Status Pindaan Pra JTICT; pemohon atau pegawai boleh menyunting; borang memaparkan **ulasan Pra JTICT**, bukan ulasan PP2 |
| T5 | Pinda dan hantar semula | `SUBMITTED`; **ID, UUID dan nombor rujukan sama**; rekod `application_reviews` "Dihantar Semula" dicipta |
| T6 | PP2 Lengkap → Pra JTICT semula → Diterima | Dua rekod Pra JTICT (`AMENDMENT_REQUIRED`, `ACCEPTED`); sejarah lama kekal |
| T7 | Pra JTICT Ditolak | `CLOSED`; ulasan wajib; polisi `update` dan route suntingan menolak; hantar semula ditolak di service |
| T8 | Keputusan JTICT bagi permohonan bukan `IN_JTICT`, atau tanpa Pra JTICT Diterima | Ditolak; tiada rekod |
| T9 | JTICT Lulus | `CLOSED`; rekod `JTICT/APPROVED`; boleh dikenal pasti melalui `latestJtictDecision()` |
| T10 | JTICT Tidak Lulus | `CLOSED`; tiada butang salin; polisi `copy` menolak |
| T11 | JTICT Kaji Semula | Butang Salin dipaparkan kepada pengguna yang dibenarkan sahaja |
| T12 | Salin | Rekod baharu: ID dan UUID baharu, nombor draf baharu, `DRAFT`, `copied_from_application_id` betul, perincian disalin, dokumen dan sejarah tidak disalin |
| T13 | Permohonan asal selepas salinan | Tiada perubahan medan (bandingkan sebelum dan selepas); tiada audit "updated" |
| T14 | Salin kali kedua (jika P6 = sekali) | Ditolak |
| T15 | Pengguna biasa, pengurusan, superadmin, atau Urus Setia yang juga superadmin cuba merekod keputusan | 403 di route, komponen dan service; tiada rekod |
| T16 | Manipulasi payload (keputusan atau peringkat tidak sah, status, `decided_by`) | 422 atau diabaikan |
| T17 | Rekod keputusan dikemas kini atau dipadam melalui model | `LogicException` |
| T18 | Dua keputusan serentak | Satu rekod sahaja |
| T19 | Akses antara agensi: pengguna agensi lain cuba melihat, menyalin atau meminda | Ditolak |
| T20 | E-mel bagi enam keputusan (`Notification::fake()`) | Penerima betul (P9); dihantar selepas commit; kegagalan tidak membatalkan keputusan |
| T21 | Permohonan bagi pihak agensi (PP3) melalui semua peringkat | E-mel kepada pendaftar dan Pegawai Utama agensi sasaran; pegawai agensi boleh meminda dan menyalin |
| T22 | Regresi | Suite penuh lulus; ujian PP1, PP2 dan PP3 tidak diubah kecuali 3 ujian status yang dijustifikasikan |

**Nota:** ujian SQLite **tidak membuktikan** keserasian MySQL.

### 11.2 UAT manual (MySQL, Mailtrap)

| # | Aliran |
|---|---|
| U1 | Diterima → JTICT Lulus |
| U2 | Ditolak dengan Pindaan → pinda → PP2 Lengkap → Diterima |
| U3 | Ditolak (tiada hantar semula) |
| U4 | Diterima → JTICT Tidak Lulus |
| U5 | Diterima → JTICT Kaji Semula → Salin → hantar salinan |
| U6 | Ujian akses: pengguna agensi lain, dan superadmin |
| U7 | Semakan e-mel di Mailtrap |
| U8 | Semakan `migrate --pretend` dan sandaran DB sebelum migration |

---

## 12. Cadangan Susunan Pelaksanaan PP4.1 hingga PP4.4

| Fasa | Kandungan | Prasyarat | Hasil |
|---|---|---|---|
| **PP4.1: Data dan backend** | Sandaran DB; 2 migration (semak `--pretend` dahulu); enum; model `ApplicationDecision` (tidak boleh diubah); status pindaan (P1) dan pelarasan PP2 (3.4); `ApplicationDecisionService`; `ApplicationCopyService`; polisi `decidePreJtict`, `decideJtict`, `copy`; ujian T2–T19 | Keputusan P1–P7 dan P15 | Logik lengkap tanpa UI; suite penuh lulus |
| **PP4.2: Antara muka Urus Setia** | Menu "Keputusan Permohonan" dengan tab Pra JTICT dan JTICT; senarai dengan carian dan penapis (agensi, status, keputusan Pra JTICT, keputusan JTICT, Mesyuarat JTICT); panel keputusan dan modal pengesahan; sejarah keputusan; lencana status dan keputusan | PP4.1; P8, P16 | Ujian Livewire T1, T15 |
| **PP4.3: Paparan pemohon dan notifikasi** | Paparan keputusan dan ulasan (tanpa nama individu); pindaan dan hantar semula; butang Salin dan pautan asal/salinan; e-mel bagi enam keputusan | PP4.2; P9 | Ujian T20 dan T21 |
| **PP4.4: UAT** | U1–U8 pada MySQL dan Mailtrap; laporan UAT PP4 | PP4.3; queue worker dijalankan | Laporan UAT PP4 |

**Di luar skop PP4:**
- Modul Kemajuan Projek dan penciptaan rekod `projects`;
- keputusan JPICT;
- penguatkuasaan ambang kos;
- akses pengurusan;
- pemindahan permohonan antara mesyuarat;
- tindakan "Batalkan Keputusan" (kecuali jika diputuskan dalam P10).

---

## 13. Ringkasan Percanggahan Dokumentasi dan Implementasi

| # | Dokumen | Kenyataan | Implementasi atau prompt pindaan |
|---|---|---|---|
| C1 | Prompt pindaan 3.2C dan 9 | "Fungsi Salin Permohonan sedia ada" | Tidak wujud (4.1) |
| C2 | Laporan PP1 Seksyen 4 | Keputusan JTICT disimpan dalam `jtict_decision` | Prompt pindaan memerlukan sejarah yang tidak ditimpa, jadi jadual baharu dicadangkan (6.4) |
| C3 | Laporan PP1 Seksyen 4 | Pra JTICT: Diterima atau Ditolak sahaja | Prompt pindaan menambah Ditolak dengan Pindaan |
| C4 | Laporan PP1 Seksyen 4 | Ulasan wajib bagi semua keputusan JTICT | Prompt pindaan tidak menetapkannya (P15) |
| C5 | Komen migration `2026_10_05_000005` | "Mesyuarat Pra-JTICT tidak lagi sebahagian daripada proses" | Selaras dengan prompt pindaan: Pra JTICT ialah keputusan Urus Setia, bukan mesyuarat |
| C6 | PRD 7.2 | Keputusan: Lulus, Lulus Bersyarat, Kaji Semula, Tolak | Prompt pindaan: Lulus, Tidak Lulus, Kaji Semula (tiada Lulus Bersyarat) |
| C7 | Komen `ApplicationStatus` (PP1) | Keputusan JTICT disimpan dalam `jtict_decision` | Komen perlu dikemas kini dalam PP4.1 jika P3 diterima |
| C8 | Laporan Audit Perancangan PP4 (versi asal) | Pilihan Setuju, Tidak Setuju, Kaji Semula, Batal; Modul JTICT di luar skop | Digantikan oleh laporan ini |

---

*PP4.0 sahaja. Tiada perubahan kod, migration, seeder, data, `.env`, konfigurasi atau operasi Git dilakukan. Pertanyaan pangkalan data menggunakan SELECT sahaja. Kerja dihentikan untuk menunggu semakan dan arahan pemilik sistem.*
