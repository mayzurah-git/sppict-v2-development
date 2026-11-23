# Laporan Pengujian SMTP dan Notifikasi Fasa PP2 (Mailtrap)

**Sistem:** SPPICT v2.1
**Modul:** Permohonan Projek: Semakan Kelengkapan oleh Urus Setia (Fasa PP2)
**Tarikh ujian:** 29 Oktober 2026
**Persekitaran:** Pembangunan (MySQL `sppictv2_db`, Laravel 13)
**Keputusan keseluruhan:** **LULUS**

---

## 1. Tujuan

Laporan PP2 menyenaraikan satu batasan: e-mel *Permohonan Tidak Lengkap* belum diuji dengan pelayan SMTP sebenar. Ujian ini menutup batasan itu. Ia mengesahkan bahawa notifikasi `ApplicationMarkedIncomplete`:

1. dihantar melalui SMTP;
2. dihantar melalui queue `database` oleh queue worker;
3. sampai kepada penerima yang betul (Pemohon dan Pegawai Utama);
4. mempunyai kandungan yang tepat;
5. tidak meninggalkan job gagal dalam queue.

## 2. Konfigurasi Ujian

| Perkara | Nilai |
|---|---|
| Mailer | `smtp` |
| Pelayan SMTP | Mailtrap Email Testing (Sandbox): `sandbox.smtp.mailtrap.io:2525` (STARTTLS) |
| Queue | `database` |
| Queue worker | `php artisan queue:work` |
| Notifikasi diuji | `App\Notifications\ApplicationMarkedIncomplete` (`ShouldQueue`, `afterCommit`) |

Mailtrap Sandbox menangkap semua e-mel dalam peti masuk ujian. Tiada e-mel sampai kepada penerima sebenar.

Kelayakan SMTP tidak direkodkan dalam laporan ini. Fail `.env` dikemas kini sendiri oleh pengguna, bukan oleh Claude Code.

## 3. Langkah Ujian

1. Kelayakan Mailtrap Sandbox dimasukkan ke dalam `.env`, kemudian `php artisan config:clear` dijalankan.
2. **Ujian sambungan SMTP:** satu e-mel ringkas dihantar melalui Tinker:
   ```php
   Mail::raw('Ujian SMTP SPPICT', fn($m) => $m->to('ujian@example.com')->subject('Ujian Mailtrap'));
   ```
   Cubaan pertama gagal dengan parse error kerana cara PowerShell mengendalikan `\$`. Ini isu quoting pada baris arahan, bukan isu aplikasi. Arahan dijalankan semula dalam Tinker interaktif dan berjaya.
3. Queue worker dimulakan.
4. **Ujian aliran sebenar:** Urus Setia menandakan permohonan ujian **SPPICT/2026/0004 ("test 123")** sebagai **Tidak Lengkap** dengan ulasan.
5. E-mel dalam peti masuk Mailtrap disemak, diikuti jadual `jobs` dan `failed_jobs`.

## 4. Keputusan

| # | Kriteria | Keputusan | Bukti |
|---|---|---|---|
| 1 | Sambungan SMTP ke Mailtrap berjaya | ✅ Lulus | E-mel ujian Tinker diterima dalam Mailtrap |
| 2 | E-mel notifikasi muncul dalam Mailtrap | ✅ Lulus | Disahkan oleh pengguna dalam peti masuk Mailtrap |
| 3 | Penerima betul (Pemohon dan Pegawai Utama, tanpa pendua) | ✅ Lulus | Disahkan oleh pengguna |
| 4 | Kandungan tepat (subjek `[SPPICT] Permohonan Tidak Lengkap: <No. Rujukan>`, tajuk, ulasan Urus Setia, arahan, pautan ke halaman permohonan) | ✅ Lulus | Disahkan oleh pengguna |
| 5 | Queue diproses tanpa job gagal | ✅ Lulus | `jobs` = 0, `failed_jobs` = 0 (semakan DB secara baca sahaja) |

## 5. Bukti Pangkalan Data

Disemak secara **baca sahaja** selepas ujian:

| Semakan | Hasil |
|---|---|
| Mailer aktif | `smtp` → `sandbox.smtp.mailtrap.io:2525` |
| Sambungan queue | `database` |
| Job tertunggak (`jobs`) | 0 |
| Job gagal (`failed_jobs`) | 0 |
| Rekod `application_reviews` | 1 rekod: SPPICT/2026/0004, `SUBMITTED` → `NEEDS_AMENDMENT`, 2026-10-29 10:07:44 |
| Status semasa SPPICT/2026/0004 | `NEEDS_AMENDMENT` (Tidak Lengkap) |
| Taburan status permohonan | DRAFT 1, SUBMITTED 3, NEEDS_AMENDMENT 1 (jumlah 5, sama seperti sebelum ujian) |

Satu rekod Draf telah dihantar semasa sesi ujian. Taburan status berubah daripada DRAFT 2 / SUBMITTED 3 kepada nilai di atas, tetapi jumlah rekod kekal 5 dan tiada rekod dipadam.

Rekod `application_reviews` daripada ujian ini **kekal secara kekal**. Ini disengajakan: model tidak membenarkan kemas kini atau pemadaman rekod sejarah.

## 6. Kesan kepada Data

- Satu permohonan ujian (SPPICT/2026/0004) kini berstatus **Tidak Lengkap** dan mempunyai satu rekod sejarah semakan.
- Tiada rekod permohonan, pengguna, mesyuarat atau audit yang dipadam atau ditulis semula.
- Tiada migration, seeder atau arahan pemusnahan data dijalankan.

## 7. Tindakan Susulan

1. **Pulihkan konfigurasi e-mel.** Semasa semakan, `.env` masih menghala ke Mailtrap Sandbox. Pulihkan nilai `MAIL_*` asal jika perlu, kemudian jalankan `php artisan config:clear`.
2. **Hentikan queue worker ujian** jika ia masih berjalan.
3. **Queue worker untuk operasi sebenar:** sediakan worker yang sentiasa berjalan, contohnya Supervisor, Windows Task Scheduler atau perkhidmatan setara. Tanpanya, notifikasi Tidak Lengkap dan Makluman Mesyuarat tidak akan dihantar.
4. **Ujian penerimaan sebelum produksi:** ulangi ujian pada pelayan SMTP sebenar organisasi sebelum go-live. Sahkan penghantaran ke domain `ns.gov.my`, termasuk SPF/DKIM dan penapis spam.
5. **Permohonan ujian:** permohonan SPPICT/2026/0004 boleh digunakan untuk menguji aliran *Kemas Kini & Hantar Semula* oleh pemohon.

## 8. Kesimpulan

Notifikasi e-mel Tidak Lengkap berfungsi dari hujung ke hujung: keputusan Urus Setia → queue `database` → queue worker → SMTP → peti masuk. Penerima dan kandungan betul, dan tiada job gagal. Batasan "e-mel belum diuji dengan SMTP sebenar" dalam Laporan PP2 boleh ditandakan **selesai untuk persekitaran pembangunan**. Ujian penerimaan pada pelayan SMTP produksi masih diperlukan.
