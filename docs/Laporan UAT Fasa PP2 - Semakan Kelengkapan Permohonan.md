# Laporan UAT Fasa PP2: Semakan Kelengkapan Permohonan

**Sistem:** SPPICT v2.1
**Modul:** Permohonan Projek: Semakan Kelengkapan oleh Urus Setia (Fasa PP2)
**Tarikh UAT:** 29 Oktober 2026
**Persekitaran:** Pembangunan (MySQL `sppictv2_db`, SMTP Mailtrap Sandbox, queue `database`)
**Keputusan keseluruhan:** **LULUS** (aliran utama), dengan dua senario sampingan yang hanya diliputi ujian automatik (lihat Seksyen 6)

Dokumen berkaitan:

- `Laporan Pelaksanaan Fasa PP2 - Semakan Kelengkapan Permohonan.md`
- `Laporan Pengujian SMTP dan Notifikasi Fasa PP2 - Mailtrap.md`

---

## 1. Tujuan

UAT ini mengesahkan, dari sudut pengguna dan dalam persekitaran sebenar pembangunan, bahawa aliran berikut berfungsi dari awal hingga akhir:

> Urus Setia menandakan **Tidak Lengkap** → **notifikasi e-mel** dihantar → pemohon **melihat ulasan** → pemohon membuat **pembetulan** → pemohon **menghantar semula** → Urus Setia **menyemak semula**

UAT ini juga mengesahkan bahawa **rekod sejarah semakan** dan **status permohonan** adalah tepat dan konsisten.

## 2. Kaedah

| Bahagian | Dilaksanakan oleh | Kaedah |
|---|---|---|
| Tindakan pengguna: semakan, pindaan, hantar semula, semakan e-mel | Penguji (pengguna) | Manual melalui antara muka sistem dan peti masuk Mailtrap |
| Pengesahan data: sejarah, status, queue, audit | Claude Code | Skrip PHP **baca sahaja** (SELECT) terhadap DB pembangunan |

Batasan kaedah:

- Tiada data diubah oleh proses pengesahan.
- Tiada migration, seeder atau arahan pemusnahan data dijalankan.
- Paparan antara muka dan kandungan e-mel tidak meninggalkan jejak dalam DB, jadi kedua-duanya disahkan berdasarkan pemerhatian penguji.

Peranan yang terlibat:

| ID pengguna | Peranan | Tindakan dalam UAT |
|---|---|---|
| u2 | `pentadbir_urus_setia` | Menyemak (Lengkap / Tidak Lengkap) |
| u3 | `pengguna_biasa` | Pemohon: memohon, meminda, menghantar semula |

## 3. Kes Ujian dan Keputusan

| ID | Kes ujian | Jangkaan | Keputusan | Bukti |
|---|---|---|---|---|
| UAT-01 | Urus Setia menandakan permohonan **Tidak Lengkap** dengan ulasan | Status `NEEDS_AMENDMENT`; rekod sejarah dengan ulasan dicipta | ✅ Lulus | Rekod #1 (0004), ulasan 69 aksara |
| UAT-02 | **Notifikasi e-mel** Tidak Lengkap | E-mel kepada Pemohon dan Pegawai Utama; kandungan tepat; tiada job gagal | ✅ Lulus | Mailtrap (disahkan penguji); `failed_jobs` = 0 |
| UAT-03 | Pemohon **melihat ulasan** Urus Setia | Ulasan dipaparkan pada halaman permohonan dan borang pindaan | ✅ Lulus | Disahkan penguji |
| UAT-04 | Pemohon **membuat pembetulan** | Borang boleh dikemas kini semasa status Tidak Lengkap | ✅ Lulus | Pindaan dibuat sebelum rekod #2 |
| UAT-05 | Pemohon **menghantar semula** ke mesyuarat asal yang masih dibuka | Status `SUBMITTED`; mesyuarat asal dikekalkan; rekod "Dihantar Semula" dicipta | ✅ Lulus | Rekod #2: oleh pemohon u3, mesyuarat #2 dikekalkan |
| UAT-06 | Urus Setia **menyemak semula** permohonan yang dihantar semula dan menandakannya **Lengkap** | Status `COMPLETE`; rekod sejarah dicipta | ✅ Lulus | Rekod #3 (0004) |
| UAT-07 | Urus Setia menandakan permohonan baharu **Lengkap** secara terus | Status `COMPLETE`; rekod sejarah dicipta | ✅ Lulus | Rekod #4 (0003) |
| UAT-08 | **Integriti sejarah dan status** | Rantaian sejarah lengkap; peralihan sah; status semasa = semakan terakhir; Tidak Lengkap sentiasa ada ulasan | ✅ Lulus | Semakan konsistensi: tiada isu |
| UAT-09 | **Kawalan peranan** pada rekod | Semakan oleh Urus Setia; hantar semula oleh pemohon atau pegawai permohonan | ✅ Lulus | Rekod #1, #3, #4 oleh u2; rekod #2 oleh u3 |
| UAT-10 | **Audit sedia ada** (owen-it) masih berfungsi | Perubahan permohonan direkod dalam `audits` | ✅ Lulus | 14 rekod audit bagi permohonan yang disemak |
| UAT-11 | **Data sedia ada** tidak terjejas | Tiada rekod dipadam | ✅ Lulus | Jumlah permohonan kekal 5 |

## 4. Sejarah Semakan yang Direkodkan

### 4.1 SPPICT/2026/0004 ("test 123"): kitaran penuh

| # | Masa | Peralihan | Oleh | Mesyuarat JTICT | Ulasan |
|---|---|---|---|---|---|
| 1 | 10:07:44 | Dihantar → **Tidak Lengkap** | Urus Setia (u2) | #2 (dibuka) | 69 aksara |
| 2 | 10:24:06 | Tidak Lengkap → **Dihantar** (hantar semula) | Pemohon (u3) | #2 (dibuka) | – |
| 3 | 10:25:34 | Dihantar → **Lengkap** | Urus Setia (u2) | #2 (dibuka) | 36 aksara |

Status semasa ialah **COMPLETE (Lengkap)**, sepadan dengan rekod #3.

### 4.2 SPPICT/2026/0003: Lengkap secara terus

| # | Masa | Peralihan | Oleh | Mesyuarat JTICT | Ulasan |
|---|---|---|---|---|---|
| 4 | 10:26:19 | Dihantar → **Lengkap** | Urus Setia (u2) | #1 (ditutup) | 32 aksara |

Status semasa ialah **COMPLETE (Lengkap)**. Semakan kelengkapan dibenarkan walaupun tempoh penghantaran mesyuarat #1 telah ditutup. Ini mengikut reka bentuk, kerana tempoh mesyuarat hanya mengawal penghantaran oleh pemohon.

## 5. Keadaan Sistem Selepas UAT

| Perkara | Nilai |
|---|---|
| Taburan status | DRAFT 1, SUBMITTED 2, COMPLETE 2 (jumlah 5) |
| Rekod `application_reviews` | 4 |
| Job tertunggak (`jobs`) | 0 |
| Job gagal (`failed_jobs`) | 0 |

Rekod `application_reviews` daripada UAT ini kekal secara kekal. Ini disengajakan: model tidak membenarkan kemas kini atau pemadaman rekod sejarah.

## 6. Senario yang Tidak Diuji Secara Manual

Senario berikut tidak dilaksanakan dalam UAT kerana mesyuarat asal permohonan 0004 masih dibuka. Untuk mengujinya, tempoh mesyuarat dalam data pembangunan perlu diubah, dan itu tidak dilakukan tanpa arahan.

| Senario | Jangkaan | Liputan semasa |
|---|---|---|
| Hantar semula apabila mesyuarat asal **telah ditutup** | Pemohon wajib memilih Mesyuarat JTICT yang aktif; pilihan disahkan di pelayan | Ujian automatik `JtictSubmissionWindowTest` dan `ApplicationReviewTest` |
| Hantar semula apabila **tiada mesyuarat aktif** | Penghantaran disekat; pindaan disimpan; status kekal Tidak Lengkap | Ujian automatik `ApplicationReviewTest` |
| Ulasan Tidak Lengkap **kosong atau terlalu pendek** | Ditolak oleh pengesahan pelayan | Ujian automatik `ApplicationReviewTest` |

Kesemua senario ini lulus dalam suite ujian automatik (413 lulus, 0 gagal). Senario-senario ini disyorkan diuji secara manual dalam UAT pra-produksi.

## 7. Pemerhatian

1. **Ulasan Lengkap menimpa `applications.review_remarks`.** Bagi 0004, medan ini kini menyimpan ulasan Lengkap, bukan ulasan Tidak Lengkap yang asal. Tiada data hilang, kerana semua ulasan kekal dalam `application_reviews` dan paparan pemohon membaca daripada sejarah. Ini tingkah laku yang direka, bukan kecacatan.
2. **Data lama tanpa mesyuarat.** SPPICT/2026/0001 dan 0002 berstatus Dihantar tetapi tiada `jtict_meeting_id`. Kedua-duanya sudah wujud sebelum PP2. Ia masih boleh disemak, tetapi perlu dikaitkan dengan mesyuarat sebelum aliran Pra JTICT atau JTICT. Ini dicadangkan untuk keputusan dalam fasa seterusnya.
3. **Urus Setia boleh menyemak permohonan yang mereka cipta sendiri.** Ini tidak berlaku dalam UAT ini, tetapi masih menjadi keputusan dasar yang belum dibuat.

## 8. Tindakan Susulan

1. Pulihkan konfigurasi `MAIL_*` dalam `.env` jika perlu, kemudian jalankan `php artisan config:clear`.
2. Sediakan queue worker yang sentiasa berjalan untuk persekitaran sebenar.
3. Ulangi ujian e-mel pada pelayan SMTP organisasi sebelum go-live.
4. Laksanakan senario dalam Seksyen 6 secara manual dalam UAT pra-produksi.
5. Buat keputusan tentang pemerhatian 7.2 dan 7.3.

## 9. Kesimpulan

Aliran utama semakan kelengkapan PP2 **lulus UAT**. Aliran Tidak Lengkap → notifikasi → paparan ulasan → pembetulan → hantar semula → semakan semula → Lengkap berfungsi dari awal hingga akhir. Sejarah semakan direkodkan dengan tepat mengikut peranan, status konsisten dengan sejarah, tiada job e-mel gagal, audit sedia ada masih berfungsi, dan tiada data sedia ada terjejas.

**Pengesahan UAT:**

| Peranan | Nama | Tandatangan | Tarikh |
|---|---|---|---|
| Penguji | | | |
| Pemilik Proses (Urus Setia) | | | |
| Pembangun | | | |
