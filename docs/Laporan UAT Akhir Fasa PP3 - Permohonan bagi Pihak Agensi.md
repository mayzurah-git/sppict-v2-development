# Laporan UAT Akhir Fasa PP3: Permohonan bagi Pihak Agensi

**Sistem:** SPPICT v2.1
**Fasa:** PP3A, pengesahan akhir UAT selepas pembaikan (Seksyen 14.4, *Laporan Pelaksanaan Fasa PP3*)
**Tarikh pengesahan:** 30 Oktober 2026
**Rekod diuji:** SPPICT/2026/0006, didaftarkan oleh Urus Setia bagi pihak agensi UPBA
**Keputusan:** **LULUS SEPENUHNYA**

Dokumen berkaitan:

- `Laporan Pelaksanaan Fasa PP3 - Permohonan bagi Pihak Agensi.md`
- `Laporan UAT Fasa PP3 - Permohonan bagi Pihak Agensi.md` (UAT awal; kesimpulannya digantikan oleh laporan ini)

---

## 0. Kaedah dan Kawalan

Pengesahan dibahagikan antara penguji dan Claude Code:

| Bahagian | Dilaksanakan oleh | Kaedah |
|---|---|---|
| Tindakan antara muka: log masuk, pindaan, hantar semula, peti masuk Mailtrap | Penguji (pengguna) | Manual, dengan tangkapan skrin Mailtrap |
| Konfigurasi, queue, data, sejarah, audit, paparan | Claude Code | Skrip PHP **baca sahaja**, render komponen Livewire tanpa menyimpan, dan pemprosesan queue selepas pengesahan Mailtrap |

Kawalan yang dipatuhi:

- Tiada perubahan kod dalam PP3A.
- Tiada perubahan pada `.env`, konfigurasi SMTP atau konfigurasi queue.
- Tiada migration, seeder atau operasi pemusnahan data.
- Tiada operasi Git.
- Tiada rekod permohonan, sejarah, pengguna atau peranan diubah atau dipadam oleh Claude Code.
- Tiada job queue dipadam secara manual. Job yang gagal dihantar semula melalui `queue:retry` standard.
- Tiada kata laluan, kunci API atau maklumat sambungan dipaparkan.

## 1. Pengesahan SMTP Mailtrap

Konfigurasi disemak secara baca sahaja melalui konfigurasi aplikasi, **sebelum setiap pemprosesan queue**:

| Perkara | Nilai | Keputusan |
|---|---|---|
| Persekitaran aplikasi | `local` | ✅ |
| Mailer lalai | `smtp` | ✅ |
| Hos SMTP | `sandbox.smtp.mailtrap.io` (Mailtrap Sandbox) | ✅ Mailtrap |
| Port SMTP | 2525 | ✅ |
| Sambungan queue | `database` | ✅ |
| Pangkalan data | `mysql`, iaitu pangkalan data pembangunan `sppictv2_db` | ✅ |

Kesimpulan: syarat keselamatan dipenuhi. Pemprosesan queue dibenarkan.

## 2. Keputusan Pemprosesan Queue

UAT melalui **dua kitaran** Tidak Lengkap → hantar semula bagi 0006. Setiap kitaran menghasilkan dua job `ApplicationMarkedIncomplete`: satu kepada Urus Setia pendaftar (u2) dan satu kepada Pegawai Utama (u12).

### 2.1 Kitaran 1 (Tidak Lengkap pada 14:04:14)

| Masa | Peristiwa | Pelaksana |
|---|---|---|
| 14:04:14 | 2 job dicipta. Pemproses queue tidak berjalan, jadi kedua-dua job tertunggak. | – |
| 14:55:23 – 14:55:29 | Job kepada u2 **DONE** | Penguji (`queue:work`) |
| 14:55:29 – 14:55:30 | Job kepada u12 **FAIL**: `550 5.7.0 Too many emails per second` (had kadar pelan percuma Mailtrap) | Penguji |
| Selepas 14:55 | Job u12 dihantar semula dan **DONE** | Penguji (`queue:retry` dan `queue:work`) |

### 2.2 Kitaran 2 (Tidak Lengkap pada 16:47:59)

| Masa | Peristiwa | Pelaksana |
|---|---|---|
| 16:48:00 | Job #7 (penerima u2) dan job #8 (penerima u12) dicipta, kedua-duanya bagi SPPICT/2026/0006 | – |
| 16:56:16 – 16:56:23 | Job kepada u2 **DONE** | Claude Code (`queue:work --stop-when-empty --rest=3`, selepas Seksyen 1 disahkan) |
| 16:56:26 – 16:56:27 | Job kepada u12 **FAIL**: had kadar Mailtrap yang sama, walaupun dengan rehat 3 saat | Claude Code |
| 16:56:48 – 16:56:53 | Job u12 dihantar semula dan **DONE** | Claude Code (`queue:retry all` dan `queue:work --stop-when-empty`) |

### 2.3 Status semasa queue

| Jadual | Bilangan |
|---|---|
| `jobs` | **0** |
| `failed_jobs` | **0** |

### 2.4 Penerimaan dalam Mailtrap

Tangkapan skrin Mailtrap daripada penguji menunjukkan dua e-mel **"[SPPICT] Permohonan Tidak Lengkap: SPPICT/2026/0006"** bagi kitaran 1:

| Penerima | Peranan | Keputusan |
|---|---|---|
| urussetia.mkk@ns.gov.my | Urus Setia pendaftar (u2) | ✅ Diterima |
| k.sunarti@ns.gov.my | Pegawai Utama (u12) | ✅ Diterima. Kandungan dibuka dengan sapaan "Salam sejahtera SUNARTI BINTI KAMARUDDIN" dan menyatakan permohonan "ditetapkan sebagai **Tidak Lengkap**". |

Kedua-dua e-mel dihantar daripada `SPPICT v2.0 <urussetia.mkk@ns.gov.my>`.

E-mel kitaran 2 telah diterima oleh pelayan SMTP Mailtrap: kedua-dua job berstatus DONE, dan tiada job gagal yang tinggal.

## 3. Langkah 9: Paparan Pegawai Utama

Halaman butiran 0006 dirender, tanpa menyimpan apa-apa, sebagai Urus Setia pendaftar (u2) dan sebagai Pegawai Utama (u12). Kedua-duanya menghasilkan paparan yang sama:

> Jabatan/Agensi: **Unit Pengurusan Bangunan dan Aset, Bahagian Khidmat Pengurusan (UPBA)**
> Didaftarkan oleh: **Mas Nazliza binti Mohd Adnan**
> Pegawai Utama: **SUNARTI BINTI KAMARUDDIN**

| Semakan | Keputusan |
|---|---|
| Nama Pegawai Utama dipaparkan di bahagian atas halaman | ✅ Lulus |
| "Didaftarkan oleh" memaparkan Urus Setia asal | ✅ Lulus |
| Agensi tidak berubah (UPBA) | ✅ Lulus |
| Pegawai tidak berubah (rekod pegawai #9: PRIMARY u12, sejak 30/10/2026, tiada tarikh tamat) | ✅ Lulus |

**Keputusan langkah 9: ✅ LULUS**

## 4. Langkah 12: Pindaan oleh Pegawai Utama

Bukti di bawah merujuk kepada kitaran 2, iaitu pindaan sebenar oleh Pegawai Utama.

| # | Semakan | Bukti | Keputusan |
|---|---|---|---|
| 12.1 | Log masuk sebagai Pegawai Utama 0006 | Semua tindakan pindaan direkodkan atas nama u12 (Seksyen 5.2) | ✅ |
| 12.2 | Buka permohonan | Penguji | ✅ |
| 12.3 | Butang "Kemas Kini & Hantar Semula" dipaparkan | Penguji. u12 dapat memadam dokumen, menyimpan dan menghantar melalui borang, dan semua tindakan ini dikawal oleh `can('update')`. | ✅ |
| 12.4 | Buat pindaan | u12 **memadam Slaid Pembentangan JTICT lama** (dokumen #12, 16:48:47) dan **memuat naik slaid baharu** (dokumen #13, 16:50:43). Perubahan disimpan sebelum dihantar. | ✅ |
| 12.5 | Hantar semula | Rekod #8, 16:51:19 | ✅ |
| 12.6 | Status menjadi `SUBMITTED` | `applications.status` = SUBMITTED | ✅ |
| 12.7 | `application_reviews` mempunyai rekod sejarah berkaitan | Dua pasangan: #5/#6 dan #7/#8 | ✅ |
| 12.8 | Rekod penghantaran semula menunjukkan Pegawai Utama sebagai pelaku | #6 dan #8, `user_id` = u12 | ✅ |
| 12.9 | `agency_id` dan `applicant_id` tidak berubah | Seksyen 6 | ✅ |
| 12.10 | "Didaftarkan oleh" masih merujuk Urus Setia asal | Seksyen 3 | ✅ |

**Keputusan langkah 12: ✅ LULUS**

## 5. Bukti Penghantaran Semula dan Sejarah Semakan

### 5.1 Sejarah semakan (`application_reviews`)

| # | Masa | Peralihan | Pelaku | Mesyuarat | Ulasan |
|---|---|---|---|---|---|
| 5 | 14:04:14 | SUBMITTED → **NEEDS_AMENDMENT** | u2 (Urus Setia pendaftar) | #2 | 55 aksara |
| 6 | 15:08:00 | NEEDS_AMENDMENT → **SUBMITTED** | **u12 (Pegawai Utama)** | #2 | – |
| 7 | 16:47:59 | SUBMITTED → **NEEDS_AMENDMENT** | u2 (Urus Setia pendaftar) | #2 | 32 aksara |
| 8 | 16:51:19 | NEEDS_AMENDMENT → **SUBMITTED** | **u12 (Pegawai Utama)** | #2 | – |

Rantaian lengkap dan berselang-seli dengan betul. Status semasa (SUBMITTED) sepadan dengan rekod terakhir, dan mesyuarat asal (#2) dikekalkan sepanjang kedua-dua kitaran.

### 5.2 Jejak audit owen-it bagi pindaan kitaran 2 (pelaku u12)

| Audit | Masa | Rekod | Peristiwa | Maksud |
|---|---|---|---|---|
| #328 | 16:48:47 | ApplicationDocument #12 | deleted | Slaid Pembentangan JTICT lama dipadam |
| #329 – #331 | 16:50:43 | ApplicationDetail #183 – #185 | created | Perincian disimpan semula semasa simpanan |
| #332 | 16:50:43 | ApplicationDocument #13 | created | Slaid Pembentangan JTICT baharu dimuat naik |
| #333 | 16:51:19 | Application #9 | updated (`status`) | Dihantar semula |
| #334 – #336 | 16:51:19 | ApplicationDetail #186 – #188 | created | Perincian disimpan semula semasa penghantaran |

### 5.3 Kandungan selepas pindaan

| Perkara | Keadaan |
|---|---|
| Dokumen aktif | Kertas Cadangan #11 (asal) dan Slaid Pembentangan JTICT #13 (baharu, 16:50:43). Dokumen #12 dipadam lembut dan kekal untuk jejak audit. |
| Perincian projek | 3 baris aktif (RM 349,380.00). Kandungannya sama dengan sebelum pindaan; pindaan kitaran 2 tertumpu pada dokumen. |
| Medan utama `applications` | Hanya `status` berubah |

**Nota kitaran 1:** penghantaran semula pertama oleh u12 (15:08) tidak mengubah kandungan. Kitaran 2 melengkapkan bukti pindaan sebenar.

## 6. Pengesahan Agensi dan Pendaftar Tidak Berubah

| Medan | Semasa dicipta (audit #287) | Semasa (selepas UAT) | Audit yang mengubah medan | Keputusan |
|---|---|---|---|---|
| `agency_id` | 8 (UPBA) | 8 (UPBA) | **0** | ✅ Tidak berubah |
| `applicant_id` | u2 (Urus Setia) | u2 (Urus Setia) | **0** | ✅ Tidak berubah |
| Pegawai Utama | u12 (UPBA) | u12 (UPBA), aktif | – | ✅ Tidak berubah |
| Mesyuarat JTICT | #2 (sejak dihantar) | #2 | – | ✅ Dikekalkan |

## 7. Isu yang Masih Belum Selesai

Perkara pengesahan UAT telah **diselesaikan**:

- Penerimaan e-mel Mailtrap disahkan melalui tangkapan skrin penguji.
- Pindaan sebenar oleh Pegawai Utama dibuktikan dalam kitaran 2.

Isu yang masih tinggal tidak menghalang penerimaan PP3, tetapi perlu diselesaikan sebelum produksi:

| # | Isu | Jenis | Tindakan dicadangkan |
|---|---|---|---|
| 1 | **Had kadar SMTP.** Mailtrap (pelan percuma) menolak e-mel kedua dalam setiap kitaran, termasuk dengan rehat 3 saat antara job. Kedua-duanya berjaya selepas `queue:retry`. | Operasi | Dalam persekitaran sebenar, jalankan queue worker yang sentiasa berjalan dengan `--tries` dan `--backoff` supaya job dicuba semula secara automatik. Uji semula pada SMTP organisasi sebelum go-live. |
| 2 | **Queue worker tidak berjalan secara automatik.** Job tertunggak sehingga `queue:work` dijalankan secara manual, dalam kedua-dua kitaran. | Operasi | Sediakan supervisor atau perkhidmatan Windows bagi queue worker. |
| 3 | **Konflik kepentingan.** Urus Setia pendaftar 0006 (u2) juga ialah penyemaknya, dalam kedua-dua kitaran. | Keputusan dasar (tertangguh sejak PP3) | Keputusan pemilik proses. |
| 4 | **Senario yang hanya diuji secara automatik:** pindaan oleh Pegawai Kedua, pindaan Draf oleh pegawai, dan sekatan bagi pegawai yang ditamatkan atau bertukar agensi. | Liputan UAT | UAT manual dalam pra-produksi. |
| 5 | **Data lama:** 0001 dan 0002 berstatus Dihantar tanpa `jtict_meeting_id`. | Data (dipindahkan daripada PP2) | Keputusan sebelum aliran Pra JTICT/JTICT. |
| 6 | **Penanda "bagi pihak" tidak disimpan** sebagai medan. | Reka bentuk (tertangguh) | Keputusan jika laporan statistik memerlukannya. |
| 7 | **`.env` masih menghala ke Mailtrap.** | Konfigurasi | Pulihkan `MAIL_*` dan jalankan `php artisan config:clear` selepas pengujian, jika perlu. |

## 8. Rumusan

| Bahagian | Keputusan |
|---|---|
| Pengesahan SMTP Mailtrap dan pangkalan data | ✅ Lulus |
| Pemprosesan queue (`jobs` 0, `failed_jobs` 0) | ✅ Lulus |
| Penerimaan e-mel dalam Mailtrap | ✅ Lulus (tangkapan skrin penguji) |
| Langkah 9: paparan Pegawai Utama | ✅ Lulus |
| Langkah 12: pindaan dan hantar semula oleh Pegawai Utama | ✅ Lulus |
| Sejarah semakan dan audit | ✅ Lulus |
| Agensi dan pendaftar tidak berubah | ✅ Lulus |

**PP3 lulus UAT sepenuhnya.** Semua fungsi yang diuji berfungsi seperti yang direka:

- pemilihan agensi sasaran;
- identiti rekod (agensi, pendaftar, Pegawai Utama);
- paparan;
- notifikasi e-mel kepada pendaftar dan Pegawai Utama;
- pindaan dan penghantaran semula oleh Pegawai Utama;
- sejarah semakan dan jejak audit.

Tiada kegagalan fungsi baharu ditemui. Isu dalam Seksyen 7 adalah perkara operasi dan dasar untuk persediaan produksi.

**PP4 atau fasa seterusnya tidak dimulakan.**

**Pengesahan UAT Akhir:**

| Peranan | Nama | Tandatangan | Tarikh |
|---|---|---|---|
| Penguji | | | |
| Pemilik Proses (Urus Setia) | | | |
| Pembangun | | | |
