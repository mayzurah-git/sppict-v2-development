# PROMPT VS CODE AGENT — UM2: SELF REGISTER

## SPPICT v2 — Pelaksanaan Pendaftaran Kendiri Pengguna

### 1. Peranan dan konteks projek

Anda bertindak sebagai Senior Laravel Developer yang menyelenggara projek SPPICT v2.

**Konteks projek:**

* Framework: Laravel 12
* Frontend: Livewire, Blade dan Tailwind CSS
* Database: MySQL
* Pengurusan role: Spatie Laravel Permission
* Audit trail: OwenIt Laravel Auditing
* Branch kerja: `feature/application-module`

Modul Pengurusan Pengguna telah melalui audit UM0 dan pelaksanaan UM1 (Database & Model Readiness).

**PENTING:** UM1 telah dilaksanakan dan migration telah dijalankan pada database pembangunan. Jangan menganggap struktur database masih seperti sebelum UM1. Audit kod dan schema sebenar sebelum membuat sebarang perubahan.

### 2. Objektif UM2

Laksanakan **Self Register** supaya pegawai daripada jabatan/agensi boleh mendaftarkan akaun sendiri menggunakan maklumat rasmi, jawatan, skim perkhidmatan dan gred daripada master data sedia ada.

UM2 hanya meliputi:

1. Borang Self Register.
2. Dropdown master data yang saling bergantung.
3. Validation di server.
4. Pemberian role automatik.
5. Penetapan status menunggu kelulusan.
6. Pencegahan akses sebelum akaun diluluskan.
7. Ujian dan laporan pelaksanaan.

**Jangan melaksanakan pendaftaran oleh Urus Setia atau Superadmin dalam fasa ini.** Kedua-duanya akan dibuat dalam fasa berasingan.

---

## 3. Keputusan rasmi yang mesti dipatuhi

Gunakan keputusan berikut sebagai requirement yang telah dipersetujui:

| Perkara            | Keputusan                                                                                 |
| ------------------ | ----------------------------------------------------------------------------------------- |
| No. Kad Pengenalan | Disimpan sebagai teks, 12 digit dan unik                                                  |
| Telefon pejabat    | Disimpan sebagai teks                                                                     |
| Telefon HP         | Disimpan sebagai teks                                                                     |
| Jawatan–Gred       | Many-to-many menggunakan jadual `grade_position`                                          |
| Role Self Register | Automatik `pengguna_biasa`                                                                |
| Pemilihan role     | Tidak dibenarkan dalam borang Self Register                                               |
| Status awal        | Status menunggu kelulusan                                                                 |
| Kelulusan          | Akan dilakukan oleh Urus Setia dalam fasa seterusnya                                      |
| Password           | Pengguna menetapkan password sendiri dan mengesahkannya                                   |
| Legacy fields      | Kekalkan `users.role_id`, `users.position`, `users.phone_number` dan `positions.grade_id` |
| Role sebenar       | Spatie `model_has_roles` ialah sumber utama role                                          |
| Audit              | Password tidak boleh direkodkan dalam audit trail                                         |

Gunakan nilai enum sebenar dalam kod UM1 bagi status menunggu kelulusan. Audit `app/Enums/UserStatus.php` terlebih dahulu untuk mendapatkan nilai yang tepat. Jangan meneka nama case atau nilai database.

---

## 4. Peraturan keselamatan database dan Git

**Larangan mutlak:**

* Jangan jalankan `php artisan migrate:fresh`.
* Jangan jalankan `php artisan migrate:refresh`.
* Jangan jalankan `php artisan db:wipe`.
* Jangan drop, truncate atau recreate jadual sedia ada.
* Jangan rollback migration UM1.
* Jangan padam atau overwrite data pengguna sedia ada.
* Jangan jalankan seeder yang boleh menukar atau memadam data sedia ada tanpa kebenaran.
* Jangan commit, push atau menukar branch secara automatik.
* Jangan ubah `.env` atau konfigurasi mail production.

Gunakan migration baharu hanya jika benar-benar diperlukan dan selepas mengesahkan schema semasa. **Keutamaan UM2 ialah menggunakan schema dan model UM1 yang telah siap, tanpa perubahan database baharu.**

Jangan ubah atau revert perubahan sedia ada yang bukan sebahagian daripada UM2. Working tree mungkin mempunyai banyak perubahan daripada modul lain yang belum di-commit.

---

## 5. Fasa A — Audit sebelum implementasi

Sebelum mengubah sebarang fail:

1. Semak `git status --short` dan branch semasa.
2. Periksa route Self Register dalam `routes/auth.php` dan/atau fail route sebenar yang digunakan.
3. Periksa `resources/views/livewire/pages/auth/register.blade.php` serta komponen Livewire/Volt yang mengendalikannya.
4. Periksa `app/Models/User.php`.
5. Periksa `app/Enums/UserStatus.php`.
6. Periksa model dan hubungan:

   * `Agency`
   * `Position`
   * `ServiceScheme`
   * `Grade`
   * `User`
7. Periksa hubungan many-to-many dan nama pivot sebenar `grade_position`.
8. Periksa `PositionManagement.php` untuk memahami bagaimana gred dikaitkan dengan jawatan.
9. Periksa seeder dan penggunaan sebenar role `pengguna_biasa`.
10. Periksa middleware, route dashboard dan route permohonan projek, khususnya `/application/new`.
11. Periksa ujian sedia ada untuk registration, authentication, email verification dan User Management.
12. Sahkan nama kolum, foreign key, cast, fillable, relationship serta nilai sebenar status pada database pembangunan.

**Selepas audit, teruskan implementasi hanya jika struktur sebenar menyokong requirement ini.** Jika ada konflik, schema tidak sepadan atau keputusan penting belum dapat dipastikan, hentikan perubahan dan laporkan dahulu.

Jangan meminta pengguna mengulangi maklumat yang sudah disahkan dalam laporan UM1. Gunakan kod sebenar sebagai rujukan.

---

## 6. Fasa B — Borang Self Register

Kemaskini borang Self Register sedia ada supaya memaparkan medan berikut mengikut turutan yang sesuai:

1. No. Kad Pengenalan
2. Nama Penuh
3. Jabatan/Agensi
4. Jawatan
5. Skim Perkhidmatan
6. Gred
7. No. Telefon Pejabat
8. No. Telefon HP
9. E-mel Rasmi
10. Kata Laluan
11. Pengesahan Kata Laluan

Gunakan label Bahasa Melayu dan gaya UI kerajaan yang konsisten dengan sistem sedia ada.

### Peraturan setiap medan

**No. Kad Pengenalan**

* Wajib diisi.
* Tepat 12 digit.
* Simpan sebagai teks, bukan integer.
* Mesti unik.
* Kekalkan sifar di hadapan.
* Jangan benarkan input ini mengubah akaun pengguna lain.

**Nama Penuh**

* Wajib diisi.
* Maksimum 255 aksara.
* Trim ruang kosong yang tidak diperlukan.

**Jabatan/Agensi**

* Dropdown daripada jadual `agencies`.
* Paparkan nama agensi.
* Hanya agensi aktif dan tidak dipadam secara soft delete boleh dipilih.
* Jangan menerima nama agensi yang dihantar sebagai teks bebas.

**Jawatan**

* Dropdown daripada `positions`.
* Hanya jawatan aktif dan tidak dipadam secara soft delete boleh dipilih.
* Kecualikan data legacy yang tidak mempunyai hubungan skim/gred yang sah.
* Paparkan nama jawatan yang sesuai dengan struktur sebenar model.

**Skim Perkhidmatan**

* Paparan sahaja (readonly), bukan input yang boleh dipilih pengguna.
* Diperoleh daripada `Position → ServiceScheme`.
* Apabila Jawatan berubah, Skim Perkhidmatan mesti dikemas kini.
* Jangan menerima nilai skim daripada client sebagai sumber kebenaran.

**Gred**

* Dropdown yang bergantung pada Jawatan yang dipilih.
* Dapatkan pilihan melalui hubungan many-to-many `grade_position`.
* Hanya gred aktif dan tidak dipadam secara soft delete yang dikaitkan dengan jawatan tersebut boleh dipilih.
* Apabila Jawatan berubah, kosongkan pilihan Gred sebelumnya dan muat semula senarai gred.
* Jangan gunakan `positions.grade_id` untuk membina dropdown baharu.
* Jika tiada gred yang sah, paparkan mesej yang jelas dan jangan benarkan pendaftaran.

**No. Telefon Pejabat dan No. Telefon HP**

* Wajib atau pilihan mengikut keputusan yang benar-benar wujud dalam spesifikasi projek. Jika belum ditetapkan, jangan mereka requirement baharu; laporkan dan gunakan keputusan yang paling konsisten dengan borang semasa.
* Simpan sebagai teks.
* Kekalkan sifar di hadapan.
* Validation digit sahaja dan had panjang yang munasabah untuk nombor telefon Malaysia.

**E-mel Rasmi**

* Wajib diisi.
* Format e-mel yang sah.
* Unik tanpa mengira huruf besar atau kecil.
* Normalize kepada lowercase sebelum penyimpanan jika selaras dengan konvensyen aplikasi.

**Kata Laluan dan Pengesahan Kata Laluan**

* Pengguna menetapkan password sendiri.
* Gunakan peraturan password Laravel yang konsisten dengan aplikasi.
* Pengesahan kata laluan mesti sepadan.
* Hash password menggunakan mekanisme Laravel sedia ada.
* Jangan simpan password dalam bentuk plaintext.
* Jangan log password dalam audit, log aplikasi atau mesej flash.

Jangan tambahkan medan Tahap Capaian/Role pada borang.

---

## 7. Fasa C — Dropdown dinamik dan validation server

Gunakan Livewire pattern yang konsisten dengan versi dan struktur projek sebenar.

Apabila pengguna memilih Jawatan:

1. Dapatkan semula rekod Jawatan daripada database.
2. Sahkan jawatan aktif dan tidak dipadam.
3. Paparkan Skim Perkhidmatan daripada relationship yang sah.
4. Muatkan Gred yang dipautkan melalui `grade_position`.
5. Tapis gred kepada rekod aktif dan tidak dipadam.
6. Kosongkan `grade_id` lama apabila `position_id` berubah.

Semasa pengguna menghantar borang, **jangan bergantung pada dropdown UI sahaja**.

Sahkan semula di server bahawa:

* `agency_id` merujuk kepada agensi aktif dan tidak dipadam.
* `position_id` merujuk kepada jawatan aktif dan tidak dipadam.
* `grade_id` merujuk kepada gred aktif dan tidak dipadam.
* Gred yang dipilih benar-benar dikaitkan dengan Jawatan melalui `grade_position`.
* E-mel belum digunakan.
* No. KP belum digunakan.
* Role ditentukan oleh server, bukan client.
* Status ditentukan oleh server, bukan client.

Gunakan validation Laravel yang sesuai, termasuk `Rule::exists()` dengan syarat `is_active = true` dan `deleted_at IS NULL` apabila sepadan dengan schema sebenar.

Jangan gunakan `Rule::in()` dengan senarai gred atau jawatan yang di-hard-code.

Jika validation gagal, kekalkan input yang selamat dan paparkan mesej dalam Bahasa Melayu.

---

## 8. Fasa D — Penyimpanan akaun

Selepas semua validation berjaya:

1. Cipta akaun pengguna menggunakan data yang telah disahkan.
2. Simpan `no_kad_pengenalan`.
3. Simpan `agency_id`.
4. Simpan `position_id`.
5. Simpan `grade_id`.
6. Simpan `phone_office` dan `phone_mobile`.
7. Simpan nama dan e-mel rasmi.
8. Hash password.
9. Tetapkan status kepada nilai **menunggu kelulusan** daripada `UserStatus` UM1.
10. Tetapkan role `pengguna_biasa` melalui API Spatie yang sedia digunakan oleh projek, contohnya `assignRole()` jika sesuai.
11. Jangan benarkan pengguna menghantar `role`, `role_id`, `status`, `approved_by`, `approved_at` atau `must_change_password` untuk menentukan nilai akaunnya.
12. Gunakan transaction database jika penciptaan akaun dan pemberian role memerlukan operasi atomik.

### Legacy fields

Kekalkan kolum berikut dan jangan padamnya:

* `users.role_id`
* `users.position`
* `users.phone_number`
* `positions.grade_id`

Jangan menganggap `users.role_id` sebagai sumber utama role. Spatie `model_has_roles` kekal sebagai sumber kebenaran role.

Jangan gunakan teks bebas untuk `users.position` sebagai sumber utama jawatan baharu. Jika kod aplikasi masih memerlukan snapshot legacy, semak corak penggunaan semasa dan isi secara konsisten tanpa memecahkan `ApplicationOfficer`.

Jangan menyalin gred ke `positions.grade_id` untuk memenuhi pendaftaran baharu. Hubungan semasa ialah `grade_position`.

Jangan mengubah atau menukar role tiga pengguna sedia ada dalam UM2.

---

## 9. Fasa E — Status, akses dan selepas pendaftaran

Keputusan UM0 menetapkan bahawa akaun Self Register memerlukan kelulusan Urus Setia.

Selepas pendaftaran berjaya:

* Akaun berstatus menunggu kelulusan.
* Role ialah `pengguna_biasa`.
* Pengguna tidak boleh terus mendapat akses kepada modul yang memerlukan akaun aktif atau kelulusan.
* Paparkan mesej bahawa pendaftaran telah diterima dan sedang menunggu kelulusan.
* Jangan paparkan password atau data sensitif dalam mesej.
* Jangan memberikan akses khas atau role tambahan.

### Kawalan akses

Audit dan gunakan middleware/authorization sedia ada dengan teliti.

Pastikan pengguna berstatus menunggu kelulusan atau ditolak tidak boleh menggunakan akaun untuk mengakses modul dalaman yang sepatutnya hanya tersedia kepada pengguna aktif.

Walau bagaimanapun:

* Jangan sekat halaman yang diperlukan untuk login, logout, pengesahan e-mel atau paparan status permohonan akaun.
* Jangan mengubah keseluruhan sistem middleware secara meluas dalam UM2.
* Jangan merosakkan akses Superadmin atau Urus Setia sedia ada.
* Jangan anggap `role` sahaja membuktikan akaun telah diluluskan.
* Jika perubahan middleware global diperlukan, audit kesan terhadap semua role dan laporkan dengan jelas.

Kelulusan sebenar oleh Urus Setia akan dilaksanakan dalam fasa kemudian. Jangan membina skrin kelulusan dalam UM2.

### Email verification

UM0 mengenal pasti bahawa `User` belum melaksanakan `MustVerifyEmail` dan konfigurasi mail semasa menggunakan `MAIL_MAILER=log`.

Oleh itu:

* Audit keadaan sebenar sebelum mengubahnya.
* Jangan menganggap email verification telah berfungsi hanya kerana route tersedia.
* Jangan menukar konfigurasi mail production.
* Jangan melaksanakan keseluruhan UM6 secara senyap dalam UM2.
* Jika implementasi Self Register memerlukan keputusan email verification yang belum diselesaikan, hentikan bahagian tersebut dan laporkan kebergantungan itu sebelum membuat perubahan meluas.

---

## 10. Fasa F — Ujian

Tambah atau kemas kini ujian feature untuk sekurang-kurangnya senario berikut:

1. Borang Self Register dipaparkan dengan medan yang diperlukan.
2. Pendaftaran sah berjaya.
3. Password disimpan sebagai hash.
4. Role automatik ialah `pengguna_biasa`.
5. Status awal ialah menunggu kelulusan.
6. Pengguna tidak boleh memilih role lain melalui request manipulation.
7. Pengguna tidak boleh menetapkan status kepada `active`.
8. No. KP yang tidak cukup 12 digit ditolak.
9. No. KP pendua ditolak.
10. E-mel pendua ditolak.
11. Agensi tidak aktif atau soft-deleted ditolak.
12. Jawatan tidak aktif atau soft-deleted ditolak.
13. Gred tidak aktif atau soft-deleted ditolak.
14. Gred yang bukan milik Jawatan dipilih ditolak.
15. Jawatan tanpa gred sah tidak membenarkan pendaftaran.
16. Skim Perkhidmatan dikemas kini apabila Jawatan berubah.
17. Password confirmation yang tidak sepadan ditolak.
18. Pengguna yang menunggu kelulusan tidak boleh mengakses modul yang dilindungi.
19. Route login, logout dan halaman status pendaftaran masih berfungsi.
20. Pendaftaran tidak merosakkan pengguna sedia ada atau role mereka.

Gunakan factory, seeder ujian atau setup database ujian mengikut corak suite semasa. Jangan mengubah data pembangunan untuk menjadikan ujian lulus.

Jangan membetulkan `ProfileTest::test_user_can_delete_their_account` dalam UM2. Laporan UM1 menyatakan kegagalan itu telah wujud sebelum perubahan UM1 dan pembaikannya dirancang dalam UM8.

Jalankan:

* Ujian khusus Self Register.
* Ujian authentication dan email verification yang berkaitan.
* Ujian master data yang berkaitan.
* Full test suite jika masa dan persekitaran membenarkan.

Laporkan bilangan ujian lulus/gagal serta bezakan kegagalan baharu daripada kegagalan baseline yang diketahui.

---

## 11. Semakan akhir sebelum berhenti

Selepas implementasi:

1. Semak `git diff --check`.
2. Semak `git status --short`.
3. Cari penggunaan lama bagi medan registration yang telah diganti.
4. Pastikan tiada password hard-coded ditambah.
5. Pastikan tiada role atau status boleh ditentukan oleh client.
6. Pastikan dropdown Gred menggunakan `grade_position`.
7. Pastikan tiada perubahan kepada legacy fields yang tidak diperlukan.
8. Pastikan tiada migration atau operasi database destruktif dijalankan.
9. Pastikan perubahan di luar skop UM2 tidak dibuat.

Jangan commit atau push.

---

## 12. Format laporan akhir

Sediakan fail Markdown:

`docs/LAPORAN_FASA_UM2_SELF_REGISTER.md`

Laporan mesti mengandungi:

1. Status keseluruhan: BERJAYA / BERJAYA DENGAN CATATAN / GAGAL.
2. Ringkasan perubahan.
3. Senarai fail dicipta dan diubah.
4. Aliran Self Register selepas implementasi.
5. Medan borang dan sumber datanya.
6. Kaedah dropdown dinamik Jawatan → Skim → Gred.
7. Validation server dan kawalan manipulasi request.
8. Role dan status yang diberikan.
9. Kawalan akses pengguna belum diluluskan.
10. Keadaan email verification dan sebarang kebergantungan kepada UM6.
11. Ringkasan ujian dengan bilangan lulus/gagal.
12. Kegagalan ujian baharu dan baseline.
13. Perubahan database, jika ada, berserta justifikasi.
14. Risiko atau perkara yang masih belum selesai.
15. Pengesahan bahawa tiada operasi destruktif, commit atau push dilakukan.

**Selepas laporan disediakan, berhenti dan tunggu semakan pengguna. Jangan teruskan UM3, UM4, UM5, UM6 atau UM7 secara automatik.**
