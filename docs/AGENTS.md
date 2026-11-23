# AGENTS.MD - PANDUAN PENTADBIRAN AI & PERANAN PANGKAT (SPPICT v2.0)

Dokumen ini berfungsi sebagai panduan konteks, standard pembangunan, dan penetapan peranan ejen AI (seperti Claude, ChatGPT, Cursor, Windsurf, atau AI Coding Assistants) dalam membantu perekayasaan semula **Sistem Pemantauan Projek ICT Negeri Sembilan (SPPICT v2.0)**.

---

## 1. KONTEKS PROJEK & PRINSIP UTAMA

* **Nama Sistem:** Sistem Pemantauan Projek ICT Negeri Sembilan versi 2.0 (SPPICT v2.0).
* **Pemilik Projek:** Seksyen Multimedia, Korporat dan Koordinasi ICT (MKK), Bahagian Teknologi Maklumat (BTM) Negeri Sembilan.
* **Tujuan Utama:** Memodenkan sistem legacy PHP procedural native (2019/2020) kepada senibina moden berasaskan Laravel MVC, meningkatkan keselamatan data, memperkemas aliran permohonan berfasa, serta menyediakan indikator prestasi dan pelaporan dinamik.

### Prinsip Utama Kod & Keselamatan
1. **Tiada Kebocoran Ralat Sistem (Zero Error Leakage):**
   * Persekitaran pengeluaran (*Production*) mesti menetapkan `APP_DEBUG=false`.
   * Tiada sebarang *PHP Stack Trace*, *SQL Exception*, atau maklumat pelayan dipaparkan kepada pengguna.
   * Ralat wajib ditangkap dan diarahkan ke *Custom Error Page* (HTTP 403, 404, 500) yang mesra pengguna, manakala butiran penuh dicatat ke `storage/logs/laravel.log`.
2. **Keselamatan Parameter URL (UUID v4):**
   * Semua *Route Parameter* untuk operasi baca/kemaskini/padam (View/Edit/Delete) **WAJIB** menggunakan UUID v4 (`char(36)`), bukannya Auto-Increment Integer ID.
   * ID Integer hanya digunakan untuk kecekapan *indexing* dan *foreign key* di peringkat pangkalan data sahaja.
3. **Integriti Rekod (Soft Deletes):**
   * Semua jadual utama (`permohonan`, `projek`, `mesyuarat`, `dokumen`) mesti menggunakan trait `SoftDeletes`.
   * Dilarang melakukan `HARD DELETE` secara terus pada rekod transaksional.
4. **Validasi Muat Naik Dokumen Strictly PDF:**
   * Kertas Cadangan dan Slaid Pembentangan (JTICT / JPICT) wajib dihadkan kepada format `.pdf` sahaja.

---

## 2. SPESIFIKASI TEKNOLOGI (TECH STACK)

Ejen AI **WAJIB** mematuhi pustaka dan versi berikut semasa menjana kod:

* **Core Framework:** Laravel 12 (PHP 8.2+)
* **Frontend Interaktif:** Livewire Volt (Single-file Livewire components)
* **Styling Framework:** Tailwind CSS
* **Database & ORM:** MySQL dengan Eloquent ORM
* **Role & Access Control:** `spatie/laravel-permission`
* **Audit Trail:** `owen-it/laravel-auditing`
* **Penjanaan PDF:** `barryvdh/laravel-dompdf`
* **Eksport Excel/CSV:** `maatwebsite/excel`

---

## 3. RULES PERNIAGAAN & HAD AMBANG KELULUSAN (BUSINESS RULES)

Setiap ejen yang menjana logik perniagaan mesti mematuhi nilai threshold permohonan projek ICT berikut:

1. **RM 20,000 – RM 49,999.99:**
   * Kelulusan & pelaksanaan di peringkat **Jabatan / Agensi sahaja**.
   * Didaftarkan dalam SPPICT v2.0 untuk tujuan pemantauan rekod.
2. **RM 50,000 – RM 499,999.99:**
   * Wajib dibentangkan dan diluluskan dalam **Mesyuarat JTICTNS**.
3. **RM 500,000 dan ke atas:**
   * Wajib dibentangkan di **Mesyuarat JTICTNS** untuk syor/kelulusan awal.
   * Seterusnya diangkat ke **Mesyuarat JPICTNS** untuk kelulusan muktamad.

---

## 4. SENARAI PERANAN EJEN AI (AI AGENT ROLES)

Semasa memberikan arahan atau meminta bantuan penjanaan kod, tentukan peranan ejen menggunakan tajuk peranan di bawah:

### 4.1 System Architect & Security Lead Agent (`@architect`)
* **Tugas:** Merancang skema pangkalan data, ERD, *migrations*, keselamatan URL (UUID), konfigurasi CORS, dan pengurusan log ralat.
* **Standard Utama:**
  * Memastikan setiap migration menyertakan `$table->uuid('uuid')->unique();` dan `$table->softDeletes();`.
  * Memastikan tiada pendedahan ID berurutan (*Sequential ID Leakage*) pada mana-mana *API/Route*.
  * Mengatur integrasi `owen-it/laravel-auditing` pada model Eloquent.

### 4.2 Backend Developer Agent (`@backend`)
* **Tugas:** Menulis *Controllers*, *Services*, *Eloquent Models*, *Form Requests*, *Middleware*, dan *Jobs*.
* **Standard Utama:**
  * Menulis kod mengikut amalan terbaik Laravel 12.
  * Menggunakan `spatie/laravel-permission` untuk *Middleware Checks* (cth: `$this->authorize('update', $application)`).
  * Memastikan pendaftaran permohonan dilaksanakan secara berfasa (Fasa 1 - Fasa 4).
  * Mengira status indikator lampu secara automatik berdasarkan kelewatan fizikal/kewangan:
    * **Hijau (On-Track):** Kelewatan 0%.
    * **Kuning (Lewat):** Kelewatan 1% – 19%.
    * **Merah (Sakit):** Kelewatan $\ge 20\%$.

### 4.3 Frontend & UI/UX Agent (`@frontend`)
* **Tugas:** Menghasilkan antaramuka interaktif menggunakan Livewire Volt & Tailwind CSS.
* **Standard Utama:**
  * Menggunakan komponen Livewire Volt berasaskan *functional api* (`view`, `state`, `rules`, dll.) atau *class-based Volt*.
  * Memastikan antaramuka mesra pengguna (*responsive design*) untuk desktop dan peranti mudah alih.
  * Menyediakan paparan indikator warna yang jelas (Merah/Kuning/Hijau) bagi status kemajuan projek.

### 4.4 QA & Testing Agent (`@qa`)
* **Tugas:** Menulis ujian unit (*Unit Tests*) dan ujian integrasi (*Pest PHP / PHPUnit*).
* **Standard Utama:**
  * Menguji kawalan hak akses mengikut 4 Peranan Utama (`pengguna_biasa`, `pentadbir_urus_setia`, `pengurusan`, `superadmin`).
  * Memastikan pengguna Agensi A tidak boleh mengakses rekod Agensi B melalui UUID route parameter.
  * Memastikan sistem menolak muat naik fail selain `.pdf`.

---

## 5. STRUKTUR MATRIKS CAPAIAN PERANAN (RBAC MATRICES)

```
+---------------------+-------------------+------------------------+-------------------+-----------------+
| Modul / Fungsi      | pengguna_biasa    | pentadbir_urus_setia   | pengurusan        | superadmin      |
+---------------------+-------------------+------------------------+-------------------+-----------------+
| Dashboard           | Agensi Dashboard  | Main Admin Dashboard   | Executive Summary | Full System     |
| Permohonan Baru     | Cipta / Kemaskini | Lihat / Semak          | Lihat Sahaja      | Urus Penuh      |
| Mesyuarat           | Lihat Minit       | Urus Agenda & Minit    | Lihat Minit       | Urus Penuh      |
| Keputusan & Syor    | Lihat Status      | Kemaskini Keputusan    | Lihat Sahaja      | Urus Penuh      |
| Status Kemajuan     | Kemaskini Pres.   | Pengesahan Prestasi    | Lihat Sahaja      | Urus Penuh      |
| Laporan & PDF       | Cetak Sijil/Lap.  | Jana All Reports/Excel | Jana All Reports  | Urus Penuh      |
| Audit Trail & User  | Tiada Capaian     | Pengurusan User Agensi | Tiada Capaian     | Full Audit/User |
+---------------------+-------------------+------------------------+-------------------+-----------------+
```

---

## 6. PANDUAN PENGGUNAAN ARAHAN (*PROMPT EXAMPLES*)

Apabila anda berinteraksi dengan AI, anda boleh menggunakan format berikut:

* **Arahan Pangkalan Data:**
  > `"@architect Sediakan migration Laravel 12 untuk jadual 'applications' dan 'projects' berpandukan spesifikasi PRD SPPICT v2.0. Pastikan menggunakan UUID v4, SoftDeletes, dan Audit Trail."`

* **Arahan Backend & Livewire:**
  > `"@backend & @frontend Hasilkan komponen Livewire Volt untuk Borang Permohonan Projek Berfasa (Fasa 1 - Fasa 4) dengan validasi fail PDF sahaja bagi slaid dan kertas kerja."`

* **Arahan Keselamatan & Testing:**
  > `"@qa Tulis ujian Pest PHP untuk menguji bahawa 'pengguna_biasa' tidak boleh mengemaskini permohonan projek agensi lain walaupun mereka tahu UUID permohonan tersebut."`

---
*Fail AGENTS.MD ini disediakan khas sebagai acuan panduan AI bagi Pembangunan SPPICT v2.0.*
