# Laporan UAT Fasa PP3: Permohonan bagi Pihak Agensi

**Sistem:** SPPICT v2.1
**Modul:** Permohonan Projek: Permohonan oleh Urus Setia bagi Pihak Agensi Sasaran (Fasa PP3)
**Tarikh UAT:** 30 Oktober 2026 (UAT awal dan UAT semula)
**Persekitaran:** Pembangunan (MySQL `sppictv2_db`, SMTP Mailtrap Sandbox, queue `database`)
**Keputusan keseluruhan:** **LULUS**. Kedua-dua langkah yang gagal dalam UAT awal (9 dan 12) lulus selepas pembetulan dan UAT semula.

Dokumen berkaitan:

- `Laporan Pelaksanaan Fasa PP3 - Permohonan bagi Pihak Agensi.md` (Seksyen 11 dan 14)
- `Laporan UAT Fasa PP2 - Semakan Kelengkapan Permohonan.md`

---

## 1. Tujuan

UAT ini mengesahkan, dari sudut pengguna, bahawa:

1. Urus Setia boleh memilih agensi sasaran dan mendaftar permohonan bagi pihak agensi tersebut. Aliran Draf, penghantaran dan senarai berfungsi tanpa mengubah peraturan sedia ada.
2. Identiti rekod adalah tepat:
   - agensi permohonan = agensi sasaran;
   - pendaftar = Urus Setia;
   - Pegawai Utama = pegawai agensi sasaran.
3. Pengguna biasa tidak terjejas.
4. Kitaran PP2 (Tidak Lengkap → notifikasi → pindaan → hantar semula) berfungsi bagi permohonan yang didaftarkan bagi pihak agensi.

## 2. Kaedah

| Bahagian | Dilaksanakan oleh | Kaedah |
|---|---|---|
| Tindakan pengguna, paparan antara muka dan semakan e-mel | Penguji (pengguna) | Manual melalui pelayar dan peti masuk Mailtrap, dengan tangkapan skrin dalam artifact UAT |
| Pengesahan data: rekod, pegawai, sejarah, queue | Claude Code | Skrip PHP **baca sahaja** terhadap DB pembangunan |

Batasan kaedah:

- Tiada data diubah oleh proses pengesahan.
- Tiada migration, seeder atau arahan pemusnahan data dijalankan.
- Paparan antara muka dan kandungan e-mel disahkan berdasarkan pemerhatian penguji.

Pengguna dan agensi yang terlibat:

| ID | Peranan | Agensi | Tindakan dalam UAT |
|---|---|---|---|
| u2 | `pentadbir_urus_setia` | agensi #1 (agensi Urus Setia) | Mendaftar 0006 bagi pihak UPBA dan menandakannya Tidak Lengkap |
| u12 | `pengguna_biasa` | UPBA (Unit Pengurusan Bangunan dan Aset, Bahagian Khidmat Pengurusan) | Pegawai Utama 0006; meminda dan menghantar semula; pengguna biasa bagi langkah 10 dan 11 |

**Nota:** maklumat sesi UAT asal menyatakan agensi sasaran ialah Bahagian Dewan Undangan Negeri (BDUN). Rekod yang diuji, **SPPICT/2026/0006**, sebenarnya berada di bawah **UPBA**. BDUN ialah agensi bagi SPPICT/2026/0005, permohonan bagi pihak agensi yang dicipta pada 29/10/2026.

## 3. Kes Ujian dan Keputusan

| # | Langkah | Jangkaan | UAT awal | UAT semula | Bukti |
|---|---|---|---|---|---|
| 1 | Log masuk sebagai Urus Setia | Papan pemuka Urus Setia dipaparkan | ✅ Lulus | – | Tangkapan skrin |
| 2 | Klik **Permohonan Baharu** | Panel "Permohonan bagi Pihak Agensi" dipaparkan; borang belum dipaparkan | ✅ Lulus | – | Tangkapan skrin |
| 3 | Pilih agensi → **Sahkan Agensi & Teruskan** | Banner "Permohonan bagi pihak: …" dipaparkan | ✅ Lulus | – | Disahkan penguji |
| 4 | Lalui Fasa 1 hingga 5 | Banner kekal; Fasa 5 hanya menyenaraikan pegawai agensi sasaran | ✅ Lulus | – | Tangkapan skrin |
| 5 | Simpan Draf → buka semula melalui "Draf Saya" | Data kekal; banner agensi sasaran dipaparkan; tiada butang Tukar Agensi | ✅ Lulus | – | Tangkapan skrin |
| 6 | Hantar tanpa mesyuarat aktif | Butang Hantar dinyahaktifkan | ✅ Lulus | – | Tangkapan skrin |
| 7 | Hantar dengan Mesyuarat JTICT aktif | Nombor rujukan rasmi diberikan | ✅ Lulus | – | SPPICT/2026/0006, mesyuarat #2 |
| 8 | Senarai Urus Setia (Dihantar) | Kod agensi sasaran dipaparkan | ✅ Lulus ¹ | – | Tangkapan skrin |
| 9 | Halaman butiran permohonan | Jabatan/Agensi = sasaran; Didaftarkan oleh = Urus Setia; Pegawai Utama daripada agensi sasaran | ❌ Gagal | ✅ Lulus | "Pegawai Utama:" kini dipaparkan di bahagian atas halaman |
| 10 | Pengguna biasa → **Permohonan Baharu** | Tiada pemilih agensi; banner menunjukkan agensi sendiri | ✅ Lulus | – | Tangkapan skrin |
| 11 | Pengguna biasa **Simpan Draf** | `agency_id` = agensi pengguna | ✅ Lulus | – | SPPICT/2026/DRAFT-0004: pemohon u12, agensi UPBA |
| 12 | Tidak Lengkap → e-mel → pindaan → hantar semula | E-mel kepada pendaftar dan Pegawai Utama; dua rekod sejarah; agensi tidak berubah | ❌ Gagal | ✅ Lulus | Seksyen 4 dan 5 |

¹ Lajur Keputusan bagi langkah 8 tidak ditanda dalam laporan UAT asal. Catatan penguji dan tangkapan skrin menunjukkan kod agensi dipaparkan seperti yang dijangka.

## 4. Kegagalan UAT Awal dan Pembetulan

| Langkah | Punca | Tindakan | Keputusan UAT semula |
|---|---|---|---|
| 9 | Pegawai Utama disimpan dengan betul. Namanya hanya dipaparkan dalam kotak "Pegawai Bertanggungjawab" di bahagian bawah halaman. | "Pegawai Utama: nama" ditambah di bahagian atas halaman butiran, di sebelah "Didaftarkan oleh". | ✅ Lulus |
| 12 (e-mel) | Dua job e-mel dicipta dengan betul pada 14:04:14, tetapi pemproses baris gilir tidak berjalan semasa UAT. | Penguji menjalankan `queue:work`. E-mel kepada pendaftar dihantar. E-mel kepada Pegawai Utama gagal kerana had kadar Mailtrap (pelan percuma: *"Too many emails per second"*), lalu dihantar semula dengan `queue:retry`. | ✅ Lulus (`failed_jobs` = 0) |
| 12 (pindaan) | Polisi `update` asal hanya membenarkan pendaftar meminda. | **Keputusan pengguna:** Pegawai Utama dan Pegawai Kedua yang aktif dan masih dalam agensi permohonan boleh meminda semasa **Draf dan Tidak Lengkap**. `ApplicationPolicy::update` dikemas kini. | ✅ Lulus |

Pembetulan disahkan oleh ujian automatik: `OnBehalfApplicationTest` 16 lulus; suite penuh **439 lulus, 0 gagal, 1 risky**.

## 5. Sejarah Semakan SPPICT/2026/0006

| # | Masa | Peralihan | Oleh | Mesyuarat JTICT |
|---|---|---|---|---|
| 5 | 30/10/2026 14:04:14 | Dihantar → **Tidak Lengkap** | Urus Setia pendaftar (u2) | #2 |
| 6 | 30/10/2026 15:08:00 | Tidak Lengkap → **Dihantar** (hantar semula) | **Pegawai Utama (u12, UPBA)** | #2 |

Keadaan rekod selepas UAT:

| Medan | Nilai | Pengesahan |
|---|---|---|
| Status | SUBMITTED | ✅ Sepadan dengan rekod sejarah #6 |
| Agensi (`agency_id`) | UPBA | ✅ Tidak berubah selepas pindaan |
| Pendaftar (`applicant_id`) | u2 (Urus Setia) | ✅ Tidak berubah selepas pindaan oleh pegawai |
| Pegawai Utama | u12 (UPBA), sejak 30/10/2026 | ✅ Pegawai agensi sasaran |
| Mesyuarat JTICT | #2 | ✅ Mesyuarat asal dikekalkan |

## 6. Keadaan Sistem Selepas UAT

| Perkara | Nilai |
|---|---|
| Taburan status | DRAFT 2, SUBMITTED 4, COMPLETE 2 (jumlah 8) |
| Permohonan bagi pihak agensi | 0005 (BDUN) dan 0006 (UPBA), kedua-duanya didaftarkan oleh u2 |
| Rekod `application_reviews` | 6 (#1 hingga #4 daripada UAT PP2; #5 dan #6 daripada UAT PP3) |
| Job tertunggak (`jobs`) | 0 |
| Job gagal (`failed_jobs`) | 0 |

Tiada rekod sedia ada dipadam atau diubah oleh proses pengesahan. Semua perubahan data datang daripada tindakan penguji melalui antara muka.

## 7. Pemerhatian

1. **Had kadar Mailtrap.** Pelan percuma Mailtrap menolak e-mel kedua yang dihantar dalam saat yang sama. Ini bukan kecacatan sistem, tetapi menunjukkan kepentingan:
   - **queue worker yang sentiasa berjalan**;
   - **cubaan semula automatik** bagi job e-mel dalam persekitaran sebenar.
2. **Perluasan kebenaran pindaan terpakai kepada semua permohonan**, bukan permohonan bagi pihak agensi sahaja. Dalam permohonan pengguna biasa yang melantik pegawai lain sebagai Pegawai Utama atau Kedua, pegawai tersebut kini boleh meminda semasa Draf dan Tidak Lengkap. Ini selaras dengan keputusan pengguna dan diliputi ujian automatik.
3. **Konflik kepentingan (isu tertangguh 12.1 laporan pelaksanaan).** Urus Setia pendaftar 0006 juga ialah penyemaknya (u2). Polisi ini tidak diubah dalam PP3 dan masih memerlukan keputusan dasar.
4. **Senario yang hanya diuji secara automatik:**
   - pindaan oleh **Pegawai Kedua**;
   - sekatan pindaan bagi pegawai yang **ditamatkan** atau **bertukar agensi**;
   - pindaan **Draf** oleh pegawai.

   Semua senario ini lulus dalam `OnBehalfApplicationTest`, dan disyorkan diuji secara manual dalam UAT pra-produksi.

## 8. Tindakan Susulan

1. Pulihkan konfigurasi `MAIL_*` dalam `.env` selepas pengujian, jika perlu, kemudian jalankan `php artisan config:clear`.
2. Sediakan queue worker yang sentiasa berjalan dengan cubaan semula (`--tries`) untuk persekitaran sebenar.
3. Buat keputusan dasar tentang konflik kepentingan (pemerhatian 7.3).
4. Laksanakan senario dalam pemerhatian 7.4 secara manual dalam UAT pra-produksi.

## 9. Kesimpulan

Fasa PP3 **lulus UAT**. Urus Setia boleh memilih agensi sasaran dan mendaftar permohonan bagi pihak agensi tersebut, dengan agensi, pendaftar dan Pegawai Utama direkodkan dan dipaparkan dengan tepat. Pengguna biasa tidak terjejas.

Kitaran Tidak Lengkap → notifikasi → pindaan → hantar semula berfungsi dari awal hingga akhir bagi permohonan yang didaftarkan bagi pihak agensi. Pegawai Utama agensi sasaran kini boleh meminda dan menghantar semula permohonan itu sendiri. Dua kegagalan dalam UAT awal telah dianalisis, dibetulkan dan lulus dalam UAT semula.

**Pengesahan UAT:**

| Peranan | Nama | Tandatangan | Tarikh |
|---|---|---|---|
| Penguji | | | |
| Pemilik Proses (Urus Setia) | | | |
| Pembangun | | | |
