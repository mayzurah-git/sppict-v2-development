# Laporan Pelaksanaan PP4.4 – Templat dan Import CSV Perincian Projek

**Tarikh:** 19/11/2026 · **Branch:** `feature/pp4-csv-import` (dari commit `62b4bf2`, PP4.3A–D) · **Status:** PASS (ujian automatik dan ujian manual pemilik dalam persekitaran pembangunan). Di-commit sebagai `577afb2`. Belum diuji dalam UAT.

**Rujukan:** Laporan Audit Fasa PP0 – Modul Permohonan Projek, §7 dan isu T5.

## 1. Keputusan pemilik

| # | Perkara | Keputusan |
|---|---|---|
| a | Perincian sedia ada | **Ganti sahaja** (tiada Tambah), dengan amaran pengesahan. Medan kosong diisi terus tanpa amaran. |
| b | Format | CSV sahaja (tiada XLSX). |
| c | Had | 500 item, 1 MB, kuantiti 1–100,000, kos seunit < RM 10 bilion. |
| d | Kategori dalam templat | Kod kategori (`project_categories.code`), aktif sahaja. |

## 2. Aliran pengguna (Fasa 3)

1. **Kaedah 1 (sedia ada):** isi terus dalam medan.
2. **Kaedah 2 (baharu):**
   - Klik **Muat Turun Templat CSV**, isi dalam Excel, simpan sebagai `.csv`, kemudian klik **Muat Naik CSV**.
   - Jika fail sah dan medan kosong, item terus dipaparkan dalam medan.
   - Jika medan sudah ada item, modal *"Perincian sedia ada (N item) akan digantikan dengan M item daripada fail CSV. Teruskan?"* dipaparkan dengan butang [Batal] dan [Ganti].
   - Selepas import, item masih boleh disunting. Data **hanya disimpan** apabila pengguna klik Simpan Draf atau Hantar, melalui `validateStep3()` dan `syncDetails()` sedia ada (termasuk semakan Kos Siling termasuk SST).
3. **Fail ditolak (semua-atau-tiada):**
   - Medan tidak berubah.
   - Ralat dipaparkan dengan nombor baris yang sama dengan nombor baris Excel, maksimum 20 ralat diikuti ringkasan baki.

## 3. Templat

- Lajur: `kod_kategori_projek, spesifikasi_teknikal, kuantiti, kos_seunit_rm`.
- Dijana secara dinamik, jadi senarai kod kategori sentiasa terkini.
- UTF-8 dengan BOM dan CRLF untuk Excel.
- Baris panduan bermula dengan `#` (senarai kod kategori dan satu contoh) diabaikan semasa import.
- Senarai kod juga dipaparkan di skrin dalam bahagian **Format templat & kod kategori projek**.

## 4. Peraturan semakan import

| Perkara | Peraturan |
|---|---|
| Fail | `extensions:csv`, `mimetypes:text/plain,text/csv,application/csv`, ≤ 1 MB |
| Tajuk lajur | Mesti sama dengan templat (huruf besar/kecil dan sel kosong di hujung diabaikan) |
| Pengekodan / pemisah | Buang BOM. Fail bukan UTF-8 dianggap Windows-1252 ("CSV (Comma delimited)" Excel). Pemisah `;` dikesan secara automatik. |
| Baris diabaikan | Baris kosong sepenuhnya dan baris bermula dengan `#` |
| Kod kategori | Wajib, mesti kod Kategori Projek **aktif** (huruf besar/kecil diabaikan) |
| Spesifikasi | Wajib, `strip_tags`, 3–2,000 aksara |
| Kuantiti | Nombor bulat 1–100,000 |
| Kos seunit | ≥ 0, maksimum 2 tempat perpuluhan, < RM 10 bilion. Format Excel `1,500.00` dan `RM 1500` diterima. |
| Lajur tambahan | Data di luar 4 lajur ditolak |
| Bilangan item | 1–500 |

## 5. Fail diubah atau ditambah

| Fail | Perubahan |
|---|---|
| `app/Services/ProjectDetailsCsvService.php` | **Baharu.** Penjanaan templat dan semakan fail |
| `app/Livewire/ApplicationForm.php` | `downloadDetailsTemplate`, `updatedDetailsCsv`, `confirmCsvReplace`, `cancelCsvReplace`, `csvCategories`, `enteredDetailsCount`, `authorizeFormAccess`. Hasil sementara dalam `#[Locked] pendingCsvDetails`. |
| `resources/views/livewire/application-form/phase-3-item-details.blade.php` | Panel import CSV, senarai ralat, rujukan kod kategori, modal amaran Ganti |
| `tests/Feature/ProjectDetailsCsvImportTest.php` | **Baharu.** 19 ujian |

**Kebenaran:** setiap tindakan menyemak `update` (draf sedia ada) atau `create` (borang baharu), sama seperti `mount()`.

**Fail sementara:** dibuang selepas diproses dan tidak disimpan kekal.

**Tiada perubahan pada:** migration, `.env`, dependency dan aliran simpan.

`npm run build` dijalankan semula untuk kelas Tailwind baharu (`public/build` diabaikan Git).

## 6. Keputusan ujian

- `ProjectDetailsCsvImportTest`: **19/19 PASS**. Meliputi:
  - templat;
  - import ke medan kosong;
  - Ganti dan Batal;
  - baris lalai kosong;
  - tajuk salah;
  - ralat bernombor baris;
  - kategori tidak aktif;
  - had kuantiti, kos dan lajur;
  - 500 dan 501 item;
  - fail bukan CSV dan fail > 1 MB;
  - `strip_tags`;
  - pemisah `;` dan Windows-1252;
  - simpan draf;
  - semakan Kos Siling selepas import.
- Suite penuh: **587/587 PASS** (4,512 assertion).
- Ujian manual pemilik (19/11/2026, persekitaran pembangunan): templat dimuat turun, diisi dalam Excel dan berjaya diimport ke Fasa 3.

## 7. Belum dilakukan / cadangan UAT

- Ujian UAT yang dicadangkan:
  - simpan templat dalam Excel sebagai "CSV UTF-8" dan "CSV (Comma delimited)";
  - fail ditolak (tajuk salah, kod kategori salah) dan senarai ralat bernombor baris;
  - amaran Ganti apabila perincian sedia ada telah diisi (Ganti dan Batal);
  - paparan modal pada skrin kecil.
- Gabungkan ke persekitaran UAT selepas UAT PP4.3 selesai, atau sebagai kitaran UAT berasingan.
