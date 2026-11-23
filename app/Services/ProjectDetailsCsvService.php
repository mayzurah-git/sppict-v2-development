<?php

namespace App\Services;

use App\Models\ProjectCategory;
use Illuminate\Support\Collection;

/**
 * PP4.4: Templat & import CSV Perincian Projek (Fasa 3) — audit PP0 T5.
 *
 * Import tidak menyimpan apa-apa ke pangkalan data: hasilnya ialah struktur `form.details` yang sama
 * dengan input manual, disimpan kemudian melalui Simpan Draf / Hantar (validateStep3 + syncDetails).
 * Semua-atau-tiada: satu baris tidak sah = keseluruhan fail ditolak.
 */
class ProjectDetailsCsvService
{
    public const HEADERS = ['kod_kategori_projek', 'spesifikasi_teknikal', 'kuantiti', 'kos_seunit_rm'];

    public const MAX_FILE_KB = 1024;

    public const MAX_ROWS = 500;

    public const MAX_SPEC_LENGTH = 2000;

    public const MAX_QUANTITY = 100000;

    /** Kos seunit mesti kurang daripada RM 10 bilion. */
    public const MAX_UNIT_COST = 9999999999.99;

    /** Bilangan ralat maksimum yang dipaparkan (baki diringkaskan). */
    public const MAX_ERRORS_SHOWN = 20;

    private const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * Kategori Projek aktif (sumber kod sah bagi templat dan import).
     */
    public function activeCategories(): Collection
    {
        return ProjectCategory::active()->ordered()->get(['id', 'code', 'name', 'sort_order']);
    }

    /**
     * Kandungan templat CSV (UTF-8 dengan BOM supaya Excel memaparkan aksara dengan betul).
     * Baris bermula dengan "#" ialah panduan dan diabaikan semasa import.
     */
    public function template(): string
    {
        $lines = [self::HEADERS];
        $lines[] = ['# PANDUAN: baris bermula dengan # diabaikan semasa import. Isi item mulai baris kosong di bawah panduan ini.'];
        $lines[] = ['# Kuantiti: nombor bulat 1 hingga ' . number_format(self::MAX_QUANTITY) . '. Kos seunit: RM sebelum SST (contoh 1500.00). SST dikira oleh sistem jika kotak SST ditanda dalam borang.'];
        $lines[] = ['# Maksimum ' . self::MAX_ROWS . ' item. Kod kategori projek yang sah:'];

        foreach ($this->activeCategories() as $category) {
            $lines[] = ['# ' . $category->code . ' = ' . $category->name];
        }

        $lines[] = ['# Contoh: ' . ($this->activeCategories()->first()->code ?? 'kod_kategori'), 'Komputer riba Core i7 16GB RAM 512GB SSD', '10', '4500.00'];

        $stream = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($stream, $line, ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        // Excel di Windows menjangkakan CRLF
        return self::UTF8_BOM . str_replace("\n", "\r\n", $csv);
    }

    /**
     * Baca dan sahkan fail CSV.
     *
     * @return array{details: array, item_count: int, errors: array<string>}
     *               `details` dalam struktur form.details (dikumpul mengikut Kategori Projek, ikut susunan
     *               kemunculan pertama). Jika `errors` tidak kosong, `details` sentiasa kosong.
     */
    public function parse(string $path): array
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            return $this->failed(['Fail tidak dapat dibaca. Sila cuba semula.']);
        }

        $content = $this->normaliseEncoding($content);

        if (trim($content) === '') {
            return $this->failed(['Fail CSV kosong. Sila gunakan templat yang disediakan.']);
        }

        $records = $this->readRecords($content);
        $header = array_shift($records);

        if (! $this->headerMatches($header)) {
            return $this->failed([
                'Format fail tidak mengikut templat sistem. Baris pertama mesti mengandungi lajur: '
                . implode(', ', self::HEADERS) . '. Sila muat turun templat dan cuba semula.',
            ]);
        }

        $categories = $this->activeCategories()->keyBy(fn ($category) => mb_strtolower($category->code));
        $errors = [];
        $groups = [];
        $itemCount = 0;

        foreach ($records as $index => $cells) {
            $rowNumber = $index + 2; // baris 1 = tajuk lajur (sama dengan nombor baris Excel)

            if ($this->isIgnorable($cells)) {
                continue;
            }

            $itemCount++;

            if ($itemCount > self::MAX_ROWS) {
                return $this->failed(['Fail mengandungi lebih daripada ' . self::MAX_ROWS . ' item. Sila kurangkan bilangan item.']);
            }

            [$item, $rowErrors] = $this->validateRow($cells, $categories);

            foreach ($rowErrors as $message) {
                $errors[] = "Baris {$rowNumber}: {$message}";
            }

            if (! $rowErrors) {
                $groups[$item['category_id']] ??= ['project_category_id' => $item['category_id'], 'items' => []];
                $groups[$item['category_id']]['items'][] = $item['item'];
            }
        }

        if ($itemCount === 0) {
            return $this->failed(['Tiada item perincian dalam fail. Sila isi sekurang-kurangnya satu item di bawah tajuk lajur.']);
        }

        if ($errors) {
            return $this->failed($this->limitErrors($errors));
        }

        return ['details' => array_values($groups), 'item_count' => $itemCount, 'errors' => []];
    }

    /**
     * @return array{0: ?array, 1: array<string>}
     */
    private function validateRow(array $cells, Collection $categories): array
    {
        $cells = array_map(fn ($cell) => trim((string) $cell), $cells);
        $errors = [];

        $extra = array_filter(array_slice($cells, count(self::HEADERS)), fn ($cell) => $cell !== '');
        if ($extra) {
            $errors[] = 'terdapat data di luar ' . count(self::HEADERS) . ' lajur templat.';
        }

        [$code, $spec, $quantity, $cost] = array_pad(array_slice($cells, 0, count(self::HEADERS)), count(self::HEADERS), '');

        $category = $categories->get(mb_strtolower($code));
        if ($code === '') {
            $errors[] = 'kod kategori projek wajib diisi.';
        } elseif (! $category) {
            $errors[] = "kod kategori projek \"{$this->excerpt($code)}\" tidak sah atau tidak aktif.";
        }

        $spec = trim(strip_tags($spec));
        if ($spec === '') {
            $errors[] = 'spesifikasi teknikal wajib diisi.';
        } elseif (mb_strlen($spec) < 3) {
            $errors[] = 'spesifikasi teknikal hendaklah sekurang-kurangnya 3 aksara.';
        } elseif (mb_strlen($spec) > self::MAX_SPEC_LENGTH) {
            $errors[] = 'spesifikasi teknikal tidak boleh melebihi ' . number_format(self::MAX_SPEC_LENGTH) . ' aksara.';
        }

        if (! preg_match('/^\d+$/', $quantity) || (int) $quantity < 1 || (int) $quantity > self::MAX_QUANTITY) {
            $errors[] = 'kuantiti mesti nombor bulat antara 1 dan ' . number_format(self::MAX_QUANTITY) . '.';
        }

        $unitCost = $this->parseAmount($cost);
        if ($unitCost === null) {
            $errors[] = 'kos seunit mesti nombor ≥ 0 dengan maksimum 2 tempat perpuluhan (contoh 1500.00).';
        } elseif ($unitCost > self::MAX_UNIT_COST) {
            $errors[] = 'kos seunit mesti kurang daripada RM 10 bilion.';
        }

        if ($errors) {
            return [null, $errors];
        }

        return [[
            'category_id' => $category->id,
            'item' => [
                'technical_specifications' => $spec,
                'unit_quantity' => (int) $quantity,
                'unit_cost' => $unitCost,
            ],
        ], []];
    }

    /**
     * Terima "1500", "1500.5", "1,500.00" dan "RM 1,500.00" (format biasa Excel); tolak selainnya.
     */
    private function parseAmount(string $value): ?float
    {
        $value = preg_replace('/^RM\s*/i', '', str_replace(' ', '', $value));

        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $value)) {
            $value = str_replace(',', '', $value);
        }

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function headerMatches(?array $header): bool
    {
        if (! $header) {
            return false;
        }

        $header = array_map(fn ($cell) => mb_strtolower(trim((string) $cell)), $header);

        // Excel kadangkala menambah sel kosong di hujung baris
        while ($header && end($header) === '') {
            array_pop($header);
        }

        return $header === self::HEADERS;
    }

    /**
     * Baris kosong sepenuhnya dan baris panduan (bermula dengan "#") diabaikan.
     */
    private function isIgnorable(array $cells): bool
    {
        $cells = array_map(fn ($cell) => trim((string) $cell), $cells);

        return implode('', $cells) === '' || str_starts_with($cells[0], '#');
    }

    /**
     * Rekod CSV menggunakan fgetcsv (menyokong sel berbilang baris dan petikan). Pemisah ";" dikesan
     * apabila tajuk lajur tidak mengandungi koma (tetapan serantau Excel tertentu).
     *
     * @return array<int, array<int, string|null>>
     */
    private function readRecords(string $content): array
    {
        $firstLine = strtok($content, "\r\n");
        $delimiter = ($firstLine !== false && ! str_contains($firstLine, ',') && str_contains($firstLine, ';')) ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $records = [];
        while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $records[] = $row;
        }
        fclose($stream);

        return $records;
    }

    /**
     * Buang BOM; fail yang bukan UTF-8 (contoh "CSV (Comma delimited)" Excel) dianggap Windows-1252.
     */
    private function normaliseEncoding(string $content): string
    {
        if (str_starts_with($content, self::UTF8_BOM)) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return $content;
    }

    private function limitErrors(array $errors): array
    {
        if (count($errors) <= self::MAX_ERRORS_SHOWN) {
            return $errors;
        }

        $remaining = count($errors) - self::MAX_ERRORS_SHOWN;

        return [...array_slice($errors, 0, self::MAX_ERRORS_SHOWN), "... dan {$remaining} ralat lain."];
    }

    private function excerpt(string $value): string
    {
        return mb_strimwidth($value, 0, 40, '…');
    }

    private function failed(array $errors): array
    {
        return ['details' => [], 'item_count' => 0, 'errors' => $errors];
    }
}
