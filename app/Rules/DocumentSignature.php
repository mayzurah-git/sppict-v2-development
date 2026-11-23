<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Semak tandatangan kandungan fail (bukan sekadar sambungan nama fail),
 * supaya fail lain yang dinamakan semula kepada .pdf / .docx ditolak.
 */
class DocumentSignature implements ValidationRule
{
    /**
     * @param  array<int, string>  $formats  Format dibenarkan: 'pdf', 'docx'
     */
    public function __construct(private array $formats, private string $message)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $path = $value->getRealPath();

        foreach ($this->formats as $format) {
            if ($path && $this->matches($format, $path)) {
                return;
            }
        }

        $fail($this->message);
    }

    private function matches(string $format, string $path): bool
    {
        return match ($format) {
            'pdf' => $this->header($path, 5) === '%PDF-',
            'docx' => $this->isDocx($path),
            default => false,
        };
    }

    private function isDocx(string $path): bool
    {
        if ($this->header($path, 4) !== "PK\x03\x04") {
            return false;
        }

        // libzip gagal membuka laluan yang panjang di Windows (nama fail sementara Livewire
        // sangat panjang), jadi salin ke fail sementara berlaluan pendek dahulu.
        $copy = tempnam(sys_get_temp_dir(), 'doc');

        try {
            if (! $copy || ! copy($path, $copy)) {
                return false;
            }

            $zip = new ZipArchive();
            if ($zip->open($copy, ZipArchive::RDONLY) !== true) {
                return false;
            }

            $isWordDocument = $zip->locateName('word/document.xml') !== false;
            $zip->close();

            return $isWordDocument;
        } finally {
            if ($copy) {
                @unlink($copy);
            }
        }
    }

    private function header(string $path, int $length): string
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return '';
        }

        $header = (string) fread($handle, $length);
        fclose($handle);

        return $header;
    }
}
