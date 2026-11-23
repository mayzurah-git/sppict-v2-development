# Laporan Pelaksanaan PP4.3D – Notifikasi E-mel Keputusan Pra JTICT dan JTICT

**Tarikh:** 17/11/2026 · **Cawangan:** `feature/ui-design` · **Commit semasa:** `ab95aad` (tiada commit dibuat)
**Asas:** PP4.2, PP4.3A–C dan keputusan pemilik sistem (penerima: Pendaftar Permohonan dan Pegawai Utama aktif sahaja)

## 1. Status skop

| Skop | Status | Ringkasan |
|---|---|---|
| Notifikasi keputusan Pra JTICT (Diterima, Ditolak dengan Pindaan, Ditolak) | **PASS** | Dihantar selepas keputusan disimpan |
| Notifikasi keputusan Mesyuarat JTICT (Lulus, Tidak Lulus, Kaji Semula) | **PASS** | Dihantar selepas keputusan disimpan |
| Penerima mengikut keputusan pemilik | **PASS** | Pendaftar dan Pegawai Utama aktif sahaja; alamat yang sama menerima satu e-mel; alamat tidak sah dilangkau dan dilog |
| Integriti keputusan (e-mel gagal tidak menjejaskan keputusan) | **PASS** | Disahkan dengan transport SMTP simulasi yang sentiasa gagal |
| Pencegahan e-mel berganda | **PASS** (dengan had, lihat §5) | Satu penghantaran bagi setiap rekod keputusan dan setiap alamat |
| Templat HTML responsif + teks biasa | **PASS** (ujian automatik) / **NOT TESTED** (paparan sebenar dalam klien e-mel) | Templat Markdown Laravel Mail |
| Suite penuh (`php artisan test`) | **PASS** | Selepas pilihan A diluluskan pemilik (17/11/2026): `memory_limit` 512M dalam `phpunit.xml` (lihat §7) |
| Penghantaran sebenar melalui Mailtrap / UAT | **NOT TESTED** | |

## 2. Fail ditambah / diubah

| Fail | Perubahan |
|---|---|
| `app/Notifications/ApplicationDecisionRecorded.php` | **Baharu.** Notifikasi `ShouldQueue` + `afterCommit()`, `$tries = 1`, `failed()` melog ralat tanpa butiran kelayakan |
| `resources/views/mail/application-decision.blade.php` | **Baharu.** Templat e-mel (HTML responsif dan teks biasa). Pengepala "SPPICT v2.1". |
| `app/Services/ApplicationDecisionService.php` | `notifyDecision()` dipanggil selepas transaksi `decide()` berjaya; `decisionRecipients()` (penerima) |
| `tests/Feature/ApplicationDecisionNotificationTest.php` | **Baharu.** 17 ujian, 243 assertions |
| `tests/Feature/PreJtictDecisionTest.php`, `tests/Feature/ApplicationDecisionTest.php` | Satu assertion `assertNothingSent()` dalam setiap fail diganti (lihat §6) |

Tiada migrasi, jadual baharu, perubahan `.env`, SMTP atau Queue. Proses keputusan, kawalan akses dan struktur keputusan tidak diubah.

## 3. Keputusan yang mencetuskan e-mel

| Peringkat | Keputusan | Tajuk e-mel | Maklumat khusus |
|---|---|---|---|
| Pra JTICT | Diterima | `[SPPICT] Keputusan Pra JTICT – Diterima – {No. Rujukan}` | Diterima untuk dibawa ke Mesyuarat JTICT |
| Pra JTICT | Ditolak dengan Pindaan | `… – Ditolak dengan Pindaan – …` | Perlu dipinda dan dihantar semula mengikut proses sedia ada |
| Pra JTICT | Ditolak | `… – Ditolak – …` | Tidak diteruskan |
| JTICT | Lulus | `[SPPICT] Keputusan Mesyuarat JTICT – Lulus – …` | Akan diteruskan ke Modul Status Kemajuan Projek apabila modul tersedia (tidak menyatakan rekod Projek telah diwujudkan) |
| JTICT | Tidak Lulus | `… – Tidak Lulus – …` | Tidak diluluskan |
| JTICT | Kaji Semula | `… – Kaji Semula – …` | Fungsi Salin Permohonan boleh digunakan, tertakluk kepada kelayakan dan had satu salinan |

**Kandungan setiap e-mel:**
- SPPICT v2.1, tajuk notifikasi, No. Rujukan, nama projek, Jabatan/Agensi, peringkat, keputusan dan tarikh keputusan;
- nama dan tarikh mesyuarat (jika rekod keputusan mempunyai mesyuarat);
- ulasan, atau "Tiada ulasan." (Diterima dan Ditolak tidak mewajibkan ulasan);
- butang **Lihat Permohonan**.

Semua nilai dibaca daripada rekod rasmi `application_decisions`, bukan daripada lajur legacy `jtict_decision` atau `jtict_remarks`. Ujian membuktikan ulasan legacy tidak dipaparkan.

Pautan dijana melalui `route()`, iaitu `APP_URL` daripada konfigurasi sedia ada. Pengguna masih perlu log masuk dan tertakluk kepada kebenaran `view`. E-mel tidak mempunyai lampiran, ID pangkalan data, nama kelas atau nama jadual. Ulasan dipaparkan ter-escape.

## 4. Penerima (keputusan pemilik sistem)

| Penerima | Peraturan |
|---|---|
| Pendaftar Permohonan | Akaun `applicant_id`. Jika akaun telah dipadam, tiada penerima dan keadaan itu dilog. |
| Pegawai Utama | Lantikan masih aktif (`ended_at` kosong). Jika dipautkan kepada akaun, akaun mesti berstatus aktif, tidak dipadam dan masih dalam agensi permohonan. Pegawai tanpa akaun (snapshot) menerima di alamat e-mel snapshot, sama seperti notifikasi Tidak Lengkap PP2. |
| Pegawai Kedua | Tidak menerima |
| Urus Setia perekod | Tidak menerima, kecuali individu tersebut ialah Pendaftar (cth. daftar bagi pihak) atau Pegawai Utama yang layak |
| Alamat sama | Satu e-mel sahaja (perbandingan tidak sensitif huruf besar/kecil) |
| E-mel tiada / tidak sah | Dilangkau dan dilog (`warning`). Keputusan tetap disimpan. |

## 5. Mekanisme penghantaran dan pencegahan e-mel berganda

1. `ApplicationDecisionService::decide()` menyimpan keputusan dan status dalam transaksi (tidak berubah).
2. **Selepas** transaksi berjaya, `notifyDecision()` menentukan penerima dan menyerahkan notifikasi ke queue. Notifikasi juga ditanda `afterCommit()`.
3. Semua ralat pada langkah 2 ditangkap dan dilog. Keputusan tidak dirollback, dan Urus Setia tetap menerima mesej "Keputusan … direkodkan".

**Log membezakan empat keadaan:**
- keputusan direkodkan (`info`);
- notifikasi diserahkan untuk penghantaran kepada {peranan} (`info`). Ini **bukan** bukti e-mel diterima atau dibaca;
- gagal diserahkan atau gagal dihantar (`error`, melalui `notifyDecision()` atau `failed()` queue);
- penerima tiada alamat sah (`warning`).

Butiran kelayakan seperti nama pengguna SMTP dalam mesej ralat dirahsiakan (`[dirahsiakan]`), dan ini disahkan oleh ujian.

**Pencegahan e-mel berganda:**
- Notifikasi dicetuskan hanya dalam `decide()` apabila rekod keputusan baharu dicipta. Membuka atau memaparkan semula halaman tidak mencetuskan e-mel.
- Klik berganda, halaman lama dan panggilan service berulang ditolak di dalam transaksi (status dan kebenaran disemak semula), jadi tiada rekod kedua dan tiada e-mel kedua.
- `$tries = 1`: queue tidak mencuba semula secara automatik, jadi e-mel yang mungkin telah dihantar tidak dihantar sekali lagi.

**Had:**
- Tiada log penghantaran per rekod dan per alamat dalam pangkalan data (tidak ditambah, mengikut arahan "tiada migrasi").
- Jika queue worker mati selepas SMTP menerima e-mel tetapi sebelum job ditanda selesai, e-mel itu boleh dihantar semula apabila job tamat masa dan diambil semula.
- Job yang gagal kekal dalam `failed_jobs`. Ia hanya dihantar semula jika pentadbir menjalankan `queue:retry` secara manual (mekanisme Laravel sedia ada; tiada halaman baharu dibina).
- Jaminan "tepat sekali" yang mutlak memerlukan jadual log notifikasi. Ini cadangan untuk keputusan pemilik dan **tidak** dilaksanakan.

**Queue:**
- Persekitaran pembangunan menggunakan sambungan `database` (tidak diubah). E-mel hanya dihantar apabila `php artisan queue:work` berjalan.
- Corak ini sama dengan notifikasi Tidak Lengkap (PP2), Mesyuarat dan Pendaftaran yang sedia ada.

## 6. Ujian

SQLite `:memory:`, queue `sync`, mailer `array` atau `Notification::fake()`. Tiada e-mel sebenar dihantar.

| # | Senario | Keputusan |
|---|---|---|
| 1–3 | Pra JTICT Diterima, Ditolak dengan Pindaan dan Ditolak mencetuskan notifikasi | PASS |
| 4–6 | JTICT Lulus, Tidak Lulus dan Kaji Semula mencetuskan notifikasi | PASS |
| 7–8 | Pendaftar dan Pegawai Utama aktif menerima (tepat sekali; tiada notifikasi lain) | PASS |
| 9 | Pegawai Utama tidak aktif tidak menerima: lantikan tamat, akaun tidak aktif, bertukar agensi, akaun dipadam | PASS |
| 10–11 | Pegawai Kedua dan Urus Setia tidak menerima; Urus Setia yang juga Pendaftar menerima | PASS |
| 12 | Alamat sama (pendaftar = pegawai, atau e-mel snapshot sama dengan huruf berbeza) menerima satu e-mel | PASS |
| 13 | E-mel tidak sah dilangkau; tiada penerima sah pun keputusan tetap disimpan dan dilog | PASS |
| 14 | Transport SMTP gagal (laluan sebenar, tanpa fake) dan penyerahan queue gagal: keputusan kekal, mesej berjaya dipaparkan, ralat dilog tanpa nama pengguna SMTP | PASS |
| 15 | Keputusan gagal (validasi, kebenaran, UI) tidak mencetuskan e-mel | PASS |
| 16–17 | Rekod keputusan dan sejarah Pra JTICT tidak berubah selepas penghantaran; kandungan daripada rekod rasmi, bukan legacy; tiada lampiran | PASS |
| 18 | Membuka atau memaparkan semula halaman (Urus Setia, Pendaftar, Pegawai) tidak mencetuskan e-mel | PASS |
| 19 | Klik berganda dan panggilan service berulang: satu rekod dan satu e-mel bagi setiap penerima | PASS |
| 20–25 | Tajuk, No. Rujukan, nama projek (termasuk aksara `\|`), agensi, tarikh keputusan, ulasan, maklumat mesyuarat, pautan `APP_URL`, mesej khusus setiap keputusan (HTML dan teks biasa) | PASS |

| Larian | Keputusan |
|---|---|
| `ApplicationDecisionNotificationTest` | **PASS 17/17** (243 assertions) |
| Ujian keputusan berkaitan (3 fail) | **PASS 56/56** |
| Suite penuh (`memory_limit=512M` untuk larian ini sahaja) | **PASS 568/568**, 4,425 assertions, 0 gagal; 1 *risky* sedia ada (`UserRoleManagementTest`, di luar skop) |
| Suite penuh tanpa ujian baharu (128M) | PASS 551/551 |
| Suite penuh dengan ujian baharu (128M) | **GAGAL – memori habis** pada ujian sedia ada (lihat §7) |

**Ujian sedia ada yang dikemas kini (dengan justifikasi):**
- `PreJtictDecisionTest::test_decision_does_not_touch_legacy_fields_other_applications_or_send_email`
- `ApplicationDecisionTest::test_decisions_do_not_touch_legacy_fields_other_records_email_or_copies`

Kedua-duanya mengesahkan "tiada e-mel" kerana sebelum PP4.3D keputusan tidak menghantar e-mel. Syarat itu kini sengaja digantikan oleh skop PP4.3D. `Notification::assertNothingSent()` diganti dengan semakan bahawa **hanya** `ApplicationDecisionRecorded` dihantar, dengan bilangan tepat (`assertSentTimes` + `assertCount`). `Mail::assertNothingSent()` dan semua assertion lain dikekalkan.

## 7. Isu memori suite penuh (diselesaikan: pilihan A)

**Keputusan pemilik (17/11/2026): pilihan A.**
- `phpunit.xml` kini mengandungi `<ini name="memory_limit" value="512M"/>`. Tetapan ini berkuat kuasa untuk ujian sahaja dan tidak menjejaskan aplikasi.
- `php artisan test` lulus **568/568**, 4,425 assertions, 0 gagal, 1 *risky* sedia ada.
- Pilihan B dan C tidak dilaksanakan. `laravel.log` masih bertambah apabila ujian dijalankan; arkibkan secara berkala jika perlu.

**Latar belakang:**

Pada had lalai PHP 128M, suite penuh berhenti di `SuperadminUserManagementTest::test_setup_tokens_are_not_recorded_in_audit_or_log`. Ini ujian sedia ada, dan ia lulus apabila dijalankan sendiri.

**Punca:**
- Ujian itu membaca keseluruhan `storage/logs/laravel.log` pembangunan dua kali ke dalam memori. Saiz fail kini kira-kira 5.7 MB, dan ia bertambah setiap kali suite dijalankan kerana ujian menulis ke log pembangunan.
- Suite sudah hampir mencecah had 128M. Ujian baharu menambah kira-kira 10 MB penggunaan memori biasa (tiada kebocoran), dan ini cukup untuk melepasi had.

**Pilihan yang dipertimbangkan:**
- **A (disyorkan):** tambah `<ini name="memory_limit" value="512M"/>` dalam `phpunit.xml`.
- **B:** arkibkan atau kosongkan `storage/logs/laravel.log` pembangunan (fail pemilik; saya tidak mengusiknya).
- **C:** ubah ujian berkenaan supaya membaca log bermula dari offset sahaja (`fseek`), dengan assertion yang sama.

## 8. Konfigurasi dan data

- `.env`, SMTP, Mail dan Queue **tidak diubah**. Hanya nama pemacu disemak (`QUEUE_CONNECTION=database`, `MAIL_MAILER=smtp`); tiada nilai rahsia dibaca atau dilaporkan.
- **Data pembangunan (semakan baca sahaja):**
  - applications 8, application_reviews 12, application_decisions 5, meetings 2;
  - `jobs` 0 dan `failed_jobs` 0 (tiada e-mel diletakkan dalam queue pembangunan);
  - migrasi 45 / batch 24;
  - hash `application_decisions` `c8943c9a…` (sama seperti selepas PP4.3C).
- `audits` id maks kini 398. Rekod 398 ialah kemas kini akaun Pengguna ID 2 melalui aplikasi pada 17/11/2026 12:33, iaitu aktiviti pemilik, bukan daripada pelaksanaan ini.
- **Pemerhatian:**
  - `APP_NAME` dalam `.env` ialah "SPPICT v2.0". Templat baharu memaparkan "SPPICT v2.1" pada pengepala dan pengaki tanpa mengubah `.env`.
  - Tag `<title>` HTML dan e-mel lain (PP2, Mesyuarat, Pendaftaran) masih memaparkan "SPPICT v2.0".
  - Nama pengirim e-mel (`MAIL_FROM_NAME`) tidak disemak dan tidak diubah.

## 9. Status Git

Commit semasa: `ab95aad`. Tiada commit atau push. Perubahan PP4.3B dan PP4.3C yang belum di-commit dikekalkan.

```
 M app/Livewire/ApplicationForm.php                       (PP4.3C)
 M app/Livewire/ViewApplication.php                       (PP4.3B/C)
 M app/Policies/ApplicationPolicy.php                     (PP4.3C)
 M app/Services/ApplicationDecisionService.php            (PP4.3D)
 M app/Services/ApplicationSubmissionService.php          (PP4.3C)
 M docs/Laporan Perancangan PP4.3 – Keputusan Mesyuarat JTICT.md
 M phpunit.xml                                            (PP4.3D, pilihan A)
 M resources/views/livewire/application-form.blade.php    (PP4.3C)
 M resources/views/livewire/view-application.blade.php    (PP4.3B/C)
 M tests/Feature/ApplicationDecisionTest.php              (PP4.3D)
 M tests/Feature/PreJtictDecisionTest.php                 (PP4.3B + PP4.3D)
?? app/Notifications/ApplicationDecisionRecorded.php      (PP4.3D)
?? app/Services/ApplicationCopyService.php                (PP4.3C)
?? docs/reports/… PP4.3B, PP4.3C, PP4.3D
?? resources/views/mail/                                  (PP4.3D)
?? tests/Feature/ApplicationCopyTest.php                  (PP4.3C)
?? tests/Feature/ApplicationDecisionNotificationTest.php  (PP4.3D)
?? tests/Feature/DecisionHistoryDisplayTest.php           (PP4.3B)
```

## 10. Belum diuji

- **Penghantaran sebenar melalui Mailtrap: NOT TESTED.** Ini termasuk paparan dalam peti masuk Mailtrap, nama pengirim, pengekodan aksara (–, ©) dan paparan pada desktop dan telefon.
- **Queue worker `database`: NOT TESTED.** Ujian menggunakan `sync`. Dalam UAT, `php artisan queue:work` perlu dijalankan.
- **MySQL: NOT TESTED.** Ujian SQLite sahaja.
- **UAT: NOT TESTED.** Aliran keputusan Pra JTICT dan JTICT dengan pengguna sebenar dalam persekitaran ujian, serta semakan e-mel di Mailtrap.
