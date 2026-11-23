# Laporan Setup Persekitaran UAT – SPPICT v2.1

**Tarikh:** 17/11/2026 · **Status keseluruhan:** **PASS (persediaan)**. Akaun ujian belum dicipta (§9, §10).

> **Kemas kini (17/11/2026, selepas tindakan pemilik):**
> - Pemilik telah memberi kebenaran MySQL dan memautkan laman Herd `sppict-uat`.
> - Ejen kemudian menjalankan:
>   - pengesahan pangkalan data;
>   - `migrate` (45 migrasi);
>   - 5 seeder data rujukan;
>   - semakan laman HTTPS.
> - Bahagian §5–§11 di bawah mencerminkan keadaan terkini.

## 1. Direktori

| Persekitaran | Direktori |
|---|---|
| Pembangunan (tidak diubah) | `C:\Users\mayzurah\Herd\www\sppict-v2.1` |
| UAT (baharu) | `C:\Users\mayzurah\Herd\www\sppictv2-uat` |

## 2. Sumber kod

- Branch `feature/ui-design`, commit `ab95aad`, termasuk perubahan PP4.3B–PP4.3D yang **belum di-commit**.
- Kaedah: salinan folder (`robocopy`), bukan `git clone`.

**Tidak disalin:**
- `.git`, `.env`, `vendor`, `node_modules` dan `public/build` (dipasang dan dibina semula);
- kandungan `storage`: dokumen permohonan, minit mesyuarat, fail sementara Livewire, log, sesi, cache dan *compiled views*. Hanya struktur folder berserta fail `.gitignore` disalin;
- `bootstrap/cache/*.php`, `.phpunit.result.cache` dan `Prompt-Pengurusan-Log-Harian.md`.

**Pengesahan salinan:**
- 17/17 fail utama PP4.3A–PP4.3D sepadan (hash SHA-256 sama), termasuk:
  - `ApplicationDecisionService`, `ApplicationCopyService` dan `ApplicationDecisionRecorded`;
  - `JtictList` dan `ViewApplication`;
  - templat e-mel `mail/application-decision`;
  - ujian berkaitan.
- 365/365 fail projek yang dijejak Git ada dalam salinan, termasuk laporan PP4.3. Fail yang tidak disalin hanyalah fail yang sengaja dikecualikan.

## 3. `.env` UAT

- **Lokasi:** `C:\Users\mayzurah\Herd\www\sppictv2-uat\.env`. Ia disalin daripada `.env` pembangunan, yang tidak diubah.
- **Nilai yang diubah dalam salinan UAT sahaja:**
  - `APP_NAME="SPPICT v2.1"`
  - `APP_URL=https://sppict-uat.test`
  - `DB_DATABASE=sppictv2_uat`
  - `QUEUE_CONNECTION=database`
- **APP_KEY:** dijana baharu untuk UAT (`key:generate` dari folder UAT). Kunci UAT berbeza daripada kunci pembangunan, dan kunci pembangunan tidak berubah.
- **Dikekalkan:**
  - hos, port dan kelayakan MySQL;
  - Mailtrap sandbox;
  - `LOG_CHANNEL=daily`, `LOG_DAILY_DAYS=90`;
  - sesi dan cache dalam pangkalan data (kini `sppictv2_uat`).
- Nilai rahsia tidak dipaparkan.

## 4. Composer dan npm (folder UAT)

| Perkara | Status |
|---|---|
| `composer install` (ikut `composer.lock`) | PASS |
| `npm ci` + `npm run build` | PASS; Vite manifest wujud |
| `php artisan about` | Laravel 13.23.0, PHP 8.4.27; Cache/Session/Queue `database`; Mail `smtp`; Logs `daily` |
| Kebenaran tulis `storage/*` dan `bootstrap/cache` | PASS |

**Isu ditemui: kelas CSS pagination Livewire tidak dijana pada binaan baharu**
- `tailwind.config.js` tidak mengimbas paparan pagination Livewire (`vendor/livewire/.../SupportPagination/views`).
- Dalam pembangunan, kelas itu kebetulan dijana kerana `storage/framework/views` (yang diimbas) mengandungi paparan pagination yang telah dikompil.
- Pada binaan baharu (UAT, dan **produksi**), 5 kelas tiada: `focus:z-10`, `hover:text-gray-500`, `ml-3`, `ring-blue-300`, `z-0`.
- **Kesan:** kecil, iaitu gaya hover/focus dan jarak pada pautan halaman. Pagination digunakan di 15 tempat.
- **Cadangan (keputusan pemilik, tidak dilaksanakan):** tambah laluan
  `./vendor/livewire/livewire/src/Features/SupportPagination/views/*.blade.php`
  dalam `content` di `tailwind.config.js` projek pembangunan, kemudian bina semula.
- 170 kelas lain yang berbeza datang daripada halaman ralat Laravel, yang tidak menggunakan CSS aplikasi. Tiada kesan.

## 5. Pangkalan data

| Semakan | Status |
|---|---|
| Sambungan dan nama pangkalan data dalam konfigurasi UAT | `mysql` / `sppictv2_uat` |
| Sambungan sebenar ke `sppictv2_uat` | Percubaan pertama GAGAL (*Access denied*, MySQL 1044). Selepas pemilik memberi kebenaran: **PASS**. Pemacu `mysql`, `DATABASE()` = `sppictv2_uat`, 0 jadual sebelum migrasi, kolasi `utf8mb4_unicode_ci`. |
| `migrate:status` (sebelum) | Jadual migrasi belum wujud (pangkalan data kosong) |
| `migrate` | **PASS**: 45 migrasi, batch 1, 38 jadual, 0 tertunda (sama bilangan dengan pembangunan). Pangkalan data aktif disahkan semula sejurus sebelum arahan. |

Tiada GRANT atau perubahan MySQL lain dibuat oleh ejen. Kebenaran diberi oleh pemilik melalui HeidiSQL.

## 6. Mailtrap dan Queue

- **Mailtrap:** hos `.env` UAT disahkan jenis **sandbox**, jadi e-mel tidak dihantar kepada penerima sebenar. Penghantaran sebenar dari UAT: NOT TESTED.
- **Queue:** `database` pada `sppictv2_uat`. Jadual `jobs` dan `failed_jobs` wujud, kedua-duanya 0. Worker belum dijalankan.

## 7. Domain UAT

- Projek menggunakan **Herd**. Laragon juga dipasang tetapi tidak digunakan oleh projek ini.
- Pemilik telah menjalankan `herd link sppict-uat` dan `herd secure sppict-uat`.
  Laman `https://sppict-uat.test` → `C:\Users\mayzurah\Herd\www\sppictv2-uat`, dengan SSL dan PHP 8.4.
- **Semakan HTTPS:**
  - `/login` memulangkan 200, dengan tajuk "SPPICT v2.1" dan borang log masuk;
  - CSS build dimuatkan (200);
  - sesi lawatan disimpan dalam `sppictv2_uat`, membuktikan laman UAT menggunakan pangkalan data UAT.
- **Ralat `herd isolate 8.4`:** arahan itu mencari laman berdasarkan nama folder (`sppictv2-uat`), bukan nama laman. Arahan yang betul ialah `herd isolate 8.4 --site=sppict-uat`. Langkah ini pilihan, kerana laman sudah menggunakan PHP 8.4.

## 8. Seeder

| Seeder | Status |
|---|---|
| `RolesAndPermissionsSeeder` | **Dijalankan**: 4 peranan, 24 kebenaran |
| `MeetingMasterDataSeeder` | **Dijalankan**: 2 nama, 6 bilangan, 2 tahun mesyuarat |
| `ApplicationMasterDataSeeder` | **Dijalankan**: 6 kategori projek, 4 jenis dan 3 kaedah perolehan |
| `ServiceSchemeSeeder` | **Dijalankan**: 18 skim |
| `GradeSeeder` | **Dijalankan**: 15 gred |
| `DemoAccountsSeeder` | **Tidak dijalankan**: akaun demo dengan e-mel `@ns.gov.my` |
| `MeetingSeeder` | Tidak dijalankan: mesyuarat UAT dicipta semasa UAT |

Setiap seeder dijalankan secara berasingan (`--class`), dengan pengesahan `DATABASE()` = `sppictv2_uat` sebelum setiap satu.

- **Jangan jalankan `php artisan db:seed` sepenuhnya.** Dengan `APP_ENV=local`, arahan itu turut menjalankan dua seeder demo di atas.
- **Tiada seeder untuk Agensi dan Jawatan.** Ejen tidak mereka-reka data rujukan rasmi. Data ini perlu dimasukkan oleh pemilik melalui modul Superadmin, atau melalui senarai rasmi yang diberikan.

## 9. Akaun ujian yang perlu disediakan

Gunakan identiti rekaan dan e-mel `@example.test`.

| Peranan | Catatan |
|---|---|
| Superadmin | Melalui `php artisan sppict:create-superadmin` (folder UAT) |
| Urus Setia | Agensi BTM UAT |
| Pemohon / Pendaftar | Agensi A |
| Pegawai Utama (aktif) | Agensi A |
| Pegawai Utama (tidak aktif) | Agensi A, status tidak aktif |
| Pegawai Kedua | Agensi A |
| Pengguna agensi lain | Agensi B |
| Pemohon yang bertukar agensi | Asal agensi A, kini agensi B (untuk C – kelayakan salinan) |

## 10. Arahan manual untuk pemilik

**Agensi UAT (pilihan b, dilaksanakan 17/11/2026):**
- Ejen mencipta agensi `UAT_BTM` – *Bahagian Teknologi Maklumat (UAT)*, kategori `SUK`, aktif, dalam `sppictv2_uat` sahaja (disahkan sebelum tulisan).
- Jumlah agensi UAT: 1.
- `sppictv2_db` tidak terjejas. Audit 399 di sana ialah kemas kini akaun Pengguna ID 2 oleh pengguna itu sendiri pada 16:31 (log masuk pemilik ke laman pembangunan), bukan daripada ejen.

**Baki tindakan (selepas kemas kini):**
- **Agensi pertama.** `sppict:create-superadmin` memerlukan kod agensi yang aktif, tetapi UAT belum mempunyai agensi, dan tiada seeder Agensi atau Jawatan. Pilihan:
  - (a) pemilik memasukkan satu agensi melalui HeidiSQL dalam `sppictv2_uat`;
  - (b) pemilik membenarkan ejen mencipta agensi rekaan bertanda UAT, contohnya kod `UAT_BTM`.
- **Superadmin**, selepas agensi wujud:
  ```powershell
  cd C:\Users\mayzurah\Herd\www\sppictv2-uat
  php artisan sppict:create-superadmin superadmin@example.test --name="Superadmin UAT" --agency=<KOD>
  ```
- **Akaun ujian lain (§9), agensi tambahan dan Jawatan:** dicipta melalui modul Superadmin, atau oleh ejen dengan kebenaran.

*Langkah 1 dan 2 di bawah telah dilaksanakan oleh pemilik.*

1. **Beri kebenaran MySQL (HeidiSQL, akaun pentadbir).**
   - Beri pengguna MySQL yang digunakan oleh aplikasi (nilai `DB_USERNAME` dalam `.env`) kebenaran **yang sama** ke atas `sppictv2_uat.*` seperti yang ada pada `sppictv2_db.*`.
   - Dalam HeidiSQL: *Tools → User manager → pilih pengguna → Add object → `sppictv2_uat`* dan tandakan keistimewaan yang sama.
   - Atau melalui SQL:
     ```sql
     GRANT ALL PRIVILEGES ON sppictv2_uat.* TO '<DB_USERNAME>'@'localhost';
     FLUSH PRIVILEGES;
     ```
2. **Domain Herd (PowerShell):**
   ```powershell
   cd C:\Users\mayzurah\Herd\www\sppictv2-uat
   herd link sppict-uat
   herd secure sppict-uat
   herd isolate 8.4
   ```
   `herd isolate 8.4` menetapkan PHP 8.4, sama seperti pembangunan. Domain akhir ialah `https://sppict-uat.test` (sepadan dengan `APP_URL`).
3. **Selepas langkah 1, ejen boleh meneruskan:**
   - pengesahan pangkalan data aktif;
   - `migrate:status` dan `migrate`;
   - seeder terpilih (§8);
   - semakan `jobs` dan `failed_jobs`.

## 11. Semakan akhir

| ID | Semakan | Status |
|---|---|---|
| U01 | Projek UAT berasingan | PASS |
| U02 | Perubahan PP4.3B–D tersedia | PASS |
| U03 | `.env` UAT berasingan | PASS |
| U04 | Database aktif `sppictv2_uat` | PASS (selepas kebenaran MySQL diberi oleh pemilik) |
| U05 | Database pembangunan tidak disentuh | PASS |
| U06 | Migration UAT selesai | PASS: 45 migrasi, 38 jadual |
| U07 | Dependency Composer tersedia | PASS |
| U08 | Aset Vite tersedia | PASS (dengan isu kecil CSS pagination, §4) |
| U09 | Mailtrap sandbox disahkan | PASS (konfigurasi); penghantaran NOT TESTED |
| U10 | Queue database UAT tersedia | PASS (jadual `jobs` dan `failed_jobs` wujud); worker NOT TESTED |
| U11 | Domain UAT boleh diakses | PASS (`https://sppict-uat.test/login` 200, sesi dalam `sppictv2_uat`) |
| U12 | Akaun ujian tersedia | NOT TESTED (§9) |

## 12. Pengesahan pembangunan tidak diubah

- **`sppictv2_db`:** snapshot baca sahaja sama seperti sebelum UAT.
  - applications 8, application_reviews 12, application_decisions 5;
  - `jobs` 0, `failed_jobs` 0;
  - `audits` maks 398;
  - hash keputusan `c8943c9a…`.
- **`.env` pembangunan tidak berubah:** `APP_NAME`, `APP_URL`, `DB_DATABASE` dan `APP_KEY` sama seperti sebelum.
- **Kod pembangunan tidak berubah.** `git status` kini mempunyai 25 entri: 24 sebelum ini ditambah laporan ini.
- Snapshot `sppictv2_db` disemak semula selepas migrasi dan seeder UAT: masih sama.
- Tiada commit atau push. Tiada arahan git yang mengubah *working tree*.
