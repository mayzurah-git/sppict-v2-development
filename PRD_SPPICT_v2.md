# DOKUMEN KEPERLUAN PRODUK (PRODUCT REQUIREMENTS DOCUMENT - PRD)
## Perekayasaan Semula Sistem Pemantauan Projek ICT Negeri Sembilan (SPPICT v2.0)

---

## DOKUMEN KAWALAN (DOCUMENT CONTROL)

| Parameter | Maklumat |
| :--- | :--- |
| **Nama Projek** | Perekayasaan Semula Sistem Pemantauan Projek ICT (SPPICT v2.0) |
| **Versi Dokumen** | 2.0.0 |
| **Tarikh** | 4 Ogos 2026 |
| **Pemilik Projek** | Seksyen Multimedia, Korporat dan Koordinasi ICT (MKK), Bahagian Teknologi Maklumat (BTM) |
| **Status Dokumen** | Draf Muktamad (Final Draft for Implementation) |
| **Sasaran Pengguna** | Pasukan Pembangun, System Architect, UI/UX Designer, QA Tester, Urus Secretariat & Stakeholders |

---

## 1. RINGKASAN EKSEKUTIF & LATAR BELAKANG

### 1.1 Latar Belakang Sistem Sedia Ada (Legacy System)
Sistem Pemantauan Projek ICT (SPPICT) telah dibangunkan pada tahun 2019 dan mula beroperasi secara rasmi pada tahun 2020. Seksyen Multimedia, Korporat dan Koordinasi ICT (MKK) bertindak sebagai urus setia bagi dua jawatankuasa utama Negeri Sembilan:
1. **Mesyuarat Jawatankuasa Teknikal ICT Negeri Sembilan (JTICTNS)**
2. **Mesyuarat Jawatankuasa Pemandu ICT Negeri Sembilan (JPICTNS)**

Aplikasi sedia ada merupakan aplikasi web PHP legacy berasaskan fail di mana skrip PHP bercampur terus dengan templat HTML/Bootstrap. Aplikasi menggunakan pemacu PDO MySQL, session PHP natif, serta komponen frontend seperti jQuery, SweetAlert2, CKEditor, dan Magnific Popup. Tiada *framework* berasaskan MVC, sistem kawalan persekitaran (*environment configuration*), *ORM*, mahupun mekanisme penjelajahan (*caching*) yang formal.

### 1.2 Rasional & Matlamat Perekayasaan Semula (SPPICT v2.0)
Perekayasaan semula ke SPPICT v2.0 bertujuan untuk:
* **Pembersihan Seni Bina (Architectural Modernization):** Memindahkan sistem ke *framework* moden berasaskan Model-View-Controller (MVC) dengan *reactive components* tanpa kebergantungan tinggi pada *JavaScript framework* yang kompleks.
* **Pengukuhan Keselamatan & Audit:** Menggunakan UUID untuk pendedahan parameter luaran, pengasingan *role-based access control* (RBAC), serta penyimpanan jejak audit (*audit trail*) bagi setiap tindakan kritikal.
* **Peningkatan Kecekapan Proses:** Menambah baik aliran kerja permohonan berfasa, memperkemas modul Pra-JTICT dan JPICT, serta menyediakan sistem penjejakan status kemajuan projek berasaskan indikator warna (Kuning/Merah/Hijau).
* **Automasi Laporan:** Menyediakan keupayaan penjanaan laporan dinamik (PDF dan Excel) mengikut tahun, kod hasil, siri mesyuarat, dan kategori projek.

---

## 2. OBJEKTIF PROJEK

1. **Menambah baik Aliran Proses Permohonan & Pelaporan:** Memastikan aliran kerja kelulusan daripada peringkat pendaftaran, semakan urus setia, mesyuarat, hingga pelaksanaan berfasa berjalan secara telus dan sistematik.
2. **Meningkatkan Keupayaan Pelaporan Khas:** Membolehkan pihak pengurusan membuat analisis dinamik bagi amaun peruntukan, status projek belum selesai, serta kajian separuh penggal.
3. **Mengkaji Semula Indikator Prestasi & Pemantauan:** Melaksanakan penilaian status fizikal dan kewangan menggunakan lampu indikator (*Red/Amber/Green status*) secara automatik.
4. **Meningkatkan Kualiti Keselamatan & Kawalan Data:** Mencegah kebocoran ralat sistem (*error leakage*), menghalang pendedahan ID pangkalan data, dan memastikan data *historical* dipelihara (*soft deletes*).

---

## 3. PERATURAN PERNIAGAAN & HAD AMBANG KELULUSAN (BUSINESS RULES)

Kelulusan permohonan projek ICT di Negeri Sembilan ditadbir mengikut ambang nilai peruntukan projek seperti berikut:

```
+-----------------------------------------------------------------------------------+
|                            NILAI PERMOHONAN PROJEK                                |
+-----------------------------------------------------------------------------------+
                                         |
         +-------------------------------+-------------------------------+
         |                               |                               |
  RM 20,000 - RM 49,999           RM 50,000 - RM 499,999             RM 500,000 +
         |                               |                               |
         v                               v                               v
+------------------+           +-------------------+           +-------------------+
| Pelaksanaan di   |           | Pembentangan &    |           | Pembentangan di   |
| Peringkat Jabatan|           | Kelulusan dalam   |           | JTICTNS, kemudian |
| / Agensi Sahaja  |           | Mesyuarat JTICTNS |           | diangkat ke       |
+------------------+           +-------------------+           | Mesyuarat JPICTNS |
                                                               +-------------------+
```

### 3.1 Had Nilai & Salur Kelulusan
* **RM 20,000 hingga RM 49,999.99:** Dilaksanakan di peringkat Jabatan / Agensi sahaja. Memerlukan pendaftaran dalam sistem untuk tujuan rekod dan pemantauan.
* **RM 50,000 hingga RM 499,999.99:** Wajib dibentangkan dan diluluskan dalam **Mesyuarat JTICTNS**.
* **RM 500,000 dan ke atas:** Wajib dibentangkan di peringkat **Mesyuarat JTICTNS** untuk syor/kelulusan awal sebelum diangkat ke **Mesyuarat JPICTNS** untuk kelulusan muktamad.

---

## 4. PERBANDINGAN TINGKATAN TEKNOLOGI (TECH STACK SPECIFICATION)

| Komponen | Sistem Legacy (Semasa) | SPPICT v2.0 (Sasaran) | Justifikasi Perekayasaan |
| :--- | :--- | :--- | :--- |
| **Core Framework** | PHP 8.4.15 Procedural Native | **Laravel 12 (Core)** | Menyediakan struktur MVC, pengurusan routing, dependency injection, dan ORM yang kukuh. |
| **Frontend Engine** | jQuery, HTML Vanilla, Templat Start Bootstrap | **Livewire Volt** | Membolehkan UI interaktif secara *reactive* menggunakan kepingan komponen PHP tanpa *overhead* Single Page Application (SPA). |
| **Styling Framework**| Bootstrap 4/5 Custom | **Tailwind CSS** | Reka bentuk responsif moden dengan sistem utiliti fleksibel. |
| **Database & ORM** | MySQL (PDO Native Query) | **MySQL (Eloquent ORM)** | Menggunakan skema gabungan Integer Auto-Increment ID (Dalaman) & UUID v4 (URL/Public). |
| **Role & Security** | Session PHP Manual | **spatie/laravel-permission** | Pengurusan peranan dan *permission* secara dinamik dan berasaskan *middleware*. |
| **Audit Trail** | Tiada | **owen-it/laravel-auditing** | Merekodkan log perubahan data (`created`, `updated`, `deleted`) beserta IP dan identiti pengguna. |
| **Penjanaan PDF** | Tiada / Manual Print | **barryvdh/laravel-dompdf** | Menerbitkan Surat Kelulusan, Sijil, Kertas Arahan, dan Laporan Eksekutif berasaskan templat HTML. |
| **Eksport Laporan** | Tiada | **maatwebsite/excel** | Mengeksport data laporan ke dalam format XLSX/CSV secara fleksibel. |

---

## 5. KEPERLUAN KESELAMATAN & SENI BINA DATA

### 5.1 Logik Identifikasi Data (UUID vs Integer ID)
* **Internal Database Primary Key:** Menggunakan `bigIncrements('id')` untuk kecekapan *indexing*, *foreign key relationships*, dan prestasi *query* pangkalan data.
* **Public / URL Route Parameter:** Setiap jadual awam mesti mengandungi ruang `char(36) uuid` yang dijana secara automatik (`Str::uuid()`).
* **Prinsip:** Mencegah *Insecure Direct Object Reference (IDOR)*. Pengguna tidak boleh mengagak ID rekod lain melalui penukaran angka URL (contoh: `/projek/view/a1b2c3d4-e5f6-...` dan bukannya `/projek/view/105`).

### 5.2 Integriti Rekod (Soft Deletes)
* Semua jadual transaksional utama (`projek`, `permohonan`, `mesyuarat`, `dokumen`) **mesti** menggunakan trait `SoftDeletes` (`deleted_at`).
* Tiada rekod permohonan atau keputusan mesyuarat dipadam secara fizikal (`HARD DELETE`) dari pangkalan data demi mengekalkan integriti sejarah audit.

### 5.3 Mesej Ralat & Penyelenggaraan Persekitaran
* Persekitaran pengeluaran (*Production*) **WAJIB** menetapkan `APP_DEBUG=false` dalam `.env`.
* Paparan ralat sistem teknikal (seperti *SQL Exception* atau *PHP Stack Trace*) dilarang sama sekali daripada muncul pada skrin pengguna. Sistem mesti memaparkan *Custom Error Page* (cth: HTTP 404, 403, 500) yang mesra pengguna, manakala butiran ralat dicatat ke dalam `storage/logs/laravel.log`.

---

## 6. STRUKTUR PERANAN & CAPAIAN HAK AKSES (RBAC)

Menggunakan perpustakaan `spatie/laravel-permission`, sistem dibahagikan kepada 4 peranan utama:

| No | Peranan (Role Name) | Deskripsi & Sub-Pengguna | Ringkasan Matriks Capaian |
| :--- | :--- | :--- | :--- |
| **1** | `pengguna_biasa` | **Pemohon / Pengurus Projek Agensi** (Jabatan / Agensi Negeri Sembilan) | • Lihat Dashboard Agensi<br>• Carian & Muat Turun Minit Mesyuarat<br>• Daftar & Kemaskini Permohonan Projek Agensi<br>• Muat Naik Slaid (PDF) & Kertas Cadangan (PDF)<br>• Kemaskini Prestasi Perolehan & Status Kemajuan<br>• Kemaskini Kajian Separuh Penggal (Sistem Pembangunan)<br>• Jana & Cetak Sijil Kelulusan / Laporan Projek Agensi |
| **2** | `pentadbir_urus_setia` | **Pentadbir Sistem / Urus Setia** (Seksyen MKK, BTM) | • Dashboard Utama Pentadbiran<br>• Pengurusan Pengguna (Daftar/Kemaskini User Agensi)<br>• Pengurusan Mesyuarat (Pra-JTICT, JTICTNS, JPICTNS)<br>• Semakan Kelengkapan Permohonan (Lengkap/Tidak Lengkap)<br>• Pengurusan Kelulusan Minit & Maklum Balas Mesyuarat<br>• Semakan Tapisan & Kemaskini Keputusan Mesyuarat<br>• Pengesahan Prestasi Perolehan & Status Kemajuan (Selesai/Tidak Selesai)<br>• Pengesahan Kajian Separuh Penggal & Laporan |
| **3** | `pengurusan` | **Pihak Pengurusan & Ahli Jawatankuasa** (Pengurusan Tertinggi / Ahli Panel) | • Landing Page Dashboard Pengurusan Eksekutif<br>• Carian & Semakan Sejarah Permohonan Agensi<br>• Semakan Permohonan Baru (Telah Disemak Urus Setia)<br>• Semak Kemasukan Permohonan ke Pra-JTICT / JTICT<br>• Papar / Muat Turun Minit Mesyuarat |
| **4** | `superadmin` | **Pembangun Sistem / Administrator Utama** | • Capaian Penuh Seluruh Sistem<br>• Tetapan Sistem & Configuration<br>• Pengurusan Roles & Permissions Modul<br>• Semakan Audit Trail (`owen-it/laravel-auditing`) |

---

## 7. SPESIFIKASI MODUL UTAMA & CIRI BAHARU (MODULE SPECIFICATIONS)

### 7.1 Modul Auth & Profil
* **Pendaftaran & Log Masuk:** Log masuk menggunakan e-mel rasmi / ID pengguna beserta kata laluan yang disulitkan (*bcrypt*).
* **Profil Pengguna:** Pengemaskinian maklumat peribadi, agensi/jabatan, nombor telefon, dan penukaran kata laluan.
* **HasRole Trait Integration:** Capaian setiap fungsi divalidasi melalui *middleware* Laravel `role:` atau `permission:`.

### 7.2 Modul Pengurusan Mesyuarat (Pra-JTICT, JTICTNS, JPICTNS)
* **Kategori Mesyuarat:**
  1. *Pra-JTICT* (Saringan awal peringkat urus setia)
  2. *JTICTNS* (Jawatankuasa Teknikal ICT)
  3. *JPICTNS* (Jawatankuasa Pemandu ICT)
* **Pendaftaran Mesyuarat & Agenda:** Penetapan tarikh, masa, tempat, siri mesyuarat, dan susunan agenda permohonan.
* **Pengurusan Minit & Pengumuman:** Muat naik minit mesyuarat (format PDF) dan pendaftaran pengumuman rasmi.
* **Tindakan Keputusan Mesyuarat:** Urus setia memasukkan status keputusan (*Lulus, Lulus Bersyarat, Kaji Semula, Tolak*) beserta ulasan dan syor untuk diangkat ke JPICTNS jika berkaitan.

### 7.3 Modul Permohonan Projek Berfasa & Dokumen
* **Pendaftaran Permohonan Baru Berfasa:**
  * Fasa 1: Maklumat Am & Kategori Projek ICT
  * Fasa 2: Jenis & Kaedah Perolehan, Anggaran Kos Projek
  * Fasa 3: Perincian Projek, Justifikasi & Implikasi
  * Fasa 4: Muat Naik Dokumen Sokongan
* **Ketetapan Muat Naik Dokumen:**
  * **Slaid Pembentangan (JTICT / JPICT):** WAJIB dalam format `.pdf`.
  * **Kertas Cadangan / Permohonan:** WAJIB dalam format `.pdf`.
  * Saiz maksimum fail tertakluk kepada tetapan sistem (cth: 15MB).

### 7.4 Modul Semakan & Tapisan Urus Setia
* **Semakan Kelengkapan:** Urus setia menyemak permohonan baharu. Jika lengkap, status ditukar kepada *Lengkap (Disahkan)* dan diatur ke agenda Pra-JTICT/JTICT. Jika tidak lengkap, notifikasi pembaikan dihantar kepada pemohon.
* **Pengesahan Keputusan:** Urus setia memasukkan Keputusan Mesyuarat (Setuju, Tidak Setuju, Kaji Semula) dan ulasan rasmi.

### 7.5 Modul Pemantauan & Prestasi Kemajuan Projek
* **Status Prestasi Perolehan:** Pemohon mengemas kini maklumat kontraktor, tarikh mula, tarikh siap, dan kemajuan kewangan/fizikal.
* **Modul Prestasi Kemajuan Projek Belum Selesai (Baharu):** Menjejak projek yang belum selesai atau mengalami kelewatan (*overdue/delayed*).
* **Penilaian Indikator Lampu (Red/Amber/Green):**
  * **Lampu Hijau (On-Track):** Kemajuan mengikut jadual asal.
  * **Lampu Kuning (Lewat / Dalam Perhatian):** Kelewatan antara 1% - 19% daripada jadual.
  * **Lampu Merah (Sakit / Kritikal):** Kelewatan $\ge 20\%$ daripada jadual atau mengalami isu kritikal.
* **Pengesahan Pemandu ICT / Urus Setia:** Urus setia mengesahkan penandaan status kemajuan projek (*Selesai / Tidak Selesai*).

### 7.6 Modul Kajian Separuh Penggal (Baharu)
* Khusus untuk projek dalam kategori **Pembangunan Sistem**.
* Pemohon wajib mengemas kini laporan penilaian impak, penggunaan sistem, dan isu teknikal apabila projek mencapai tempoh separuh penggal pelaksanaan.
* Urus setia menyemak dan mengesahkan laporan kajian separuh penggal.

### 7.7 Modul Dashboard Landing Page Mengikut Peranan (Baharu)
* **Dashboard Pemohon:** Memaparkan senarai permohonan projek agensi sendiri, status terkini permohonan, dan tindakan susulan yang diperlukan.
* **Dashboard Pentadbir (Urus Setia):** Memaparkan senarai projek baharu diterima yang memerlukan semakan, status mesyuarat akan datang, dan status pengesahan kemajuan.
* **Dashboard Pengurusan:** Memaparkan *Executive Widget*:
  * Ringkasan Bilangan Projek Selesai vs Belum Selesai
  * Carta Jumlah Peruntukan Projek mengikut Agensi/Tahun
  * Indikator Projek Sakit/Lewat (Lampu Merah/Kuning)

### 7.8 Modul Pelaporan & Eksport Data (Baharu)
Menggunakan `barryvdh/laravel-dompdf` (PDF) dan `maatwebsite/excel` (Excel):
1. **Laporan Permohonan Projek Mengikut Tahun:** Carian dan penjanaan senarai permohonan bagi tahun tertentu.
2. **Laporan Keseluruhan Amaun Projek Mengikut Kod Hasil:** Analisis pecahan kos mengikut Jenis Kod Hasil bagi setiap tahun.
3. **Laporan Bilangan Projek & Amaun Mengikut Siri Mesyuarat:** Statistik permohonan mengikut siri Mesyuarat JTICTNS / JPICTNS.
4. **Laporan Bilangan & Jumlah Kos Mengikut Kategori Projek ICT:** Pecahan kos berdasarkan kategori (cth: Hardware, Software, Network, System Development, Maintenance).
5. **Cetakan Sijil Kelulusan:** Penjanaan Sijil Kelulusan JTICTNS/JPICTNS secara automatik untuk muat turun pemohon.

---

## 8. ALIRAN PROSES & WORKFLOW SISTEM (FLOWCHART ANALYSIS)

Berdasarkan senibina aliran proses sistem, perekayasaan ini dibahagikan kepada 3 Fasa Utama:

```
+---------------------------------------------------------------------------------------------------+
| FASA 1: PERMOHONAN & KELULUSAN TEKNIKAL                                                           |
+---------------------------------------------------------------------------------------------------+
| Urus Setia       -->  Daftar Mesyuarat (Pra-JTICT / JTICT / JPICT)                               |
| Pemohon          -->  Isi & Hantar Permohonan Projek (Berfasa) + Muat Naik PDF                   |
| Urus Setia       -->  Semak Dokumen Lengkap & Patuh?                                             |
|                       ├── [TIDAK] --> Notifikasi Gagal / Mohon Pindaan (Kembali ke Pemohon)       |
|                       └── [YA]    --> Masuk Agenda Mesyuarat ICT                                  |
| Jawatankuasa     -->  Mesyuarat Jawatankuasa Bersidang                                            |
| Keputusan        -->  [Lulus / Bersyarat]  --> Notifikasi Lulus kepada Pemohon & Pemandu ICT     |
|                       [Tolak]              --> Notifikasi Gagal / Tolak                           |
+---------------------------------------------------------------------------------------------------+
                                                 |
                                                 v
+---------------------------------------------------------------------------------------------------+
| FASA 2: PELAKSANAAN                                                                               |
+---------------------------------------------------------------------------------------------------+
| Pemohon / Pengurus Projek --> Kemas kini Info Projek (Tarikh Mula, Tarikh Tamat, Kontraktor)       |
+---------------------------------------------------------------------------------------------------+
                                                 |
                                                 v
+---------------------------------------------------------------------------------------------------+
| FASA 3: PEMANTAUAN & LAPORAN                                                                      |
+---------------------------------------------------------------------------------------------------+
| Pemohon          --> Hantar Laporan Status Kemajuan Fizikal & Kewangan                            |
| Pemandu ICT      --> Sahkan Kemajuan & Semak Isu / Risiko                                         |
|                      ├── [On-Track]     --> Penilaian Status: Lampu Hijau                         |
|                      └── [Lewat / Sakit] --> Penilaian Status: Lampu Merah / Kuning               |
|                                              (Ulasan & Arahan Pembetulan)                         |
| Sistem           --> Jana Dashboard & Laporan Status Kemajuan Eksekutif                           |
| Tamat / Kitaran Seterusnya                                                                        |
+---------------------------------------------------------------------------------------------------+
```

---

## 9. KEPERLUAN PRESTASI, AUDIT DAN KUALITI SISTEM

1. **Audit Trail (owen-it/laravel-auditing):** Setiap perubahan data pada entiti `Permohonan`, `Projek`, `KeputusanMesyuarat`, dan `User` mesti merekodkan:
   * ID Pengguna yang membuat perubahan.
   * Nilai asal (*Old Values*) dan nilai baharu (*New Values*).
   * Alamat IP dan *User Agent* pelayar.
   * Tarikh & Masa (*Timestamp*).
2. **Standard Validasi Fail:** System-level validation memastikan hanya fail bermodus `.pdf` diterima untuk slaid dan kertas kerja.
3. **Penyimpanan Fail Selamat:** Fail sokongan disimpan dalam direktori `storage/app/private` dan hanya boleh dicapai melalui *signed stream URL* selepas mengesahkan hak akses pengguna.
4. **Responsif & Kebolehalihan:** Antaramuka berasaskan Tailwind CSS & Livewire Volt mesti fully responsive untuk paparan desktop, tablet, dan peranti mudah alih.

---
*Dokumen ini disediakan sebagai acuan utama pembangunan semula Sistem Pemantauan Projek ICT (SPPICT v2.0).*
