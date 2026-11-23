<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Mews\Purifier\Facades\Purifier;

/**
 * Teks berformat (Trix) bagi medan permohonan seperti Objektif & Skop Projek.
 *
 * HTML sentiasa dibersihkan (profil Purifier `permohonan`) sebelum disimpan dan dipaparkan.
 * Rekod lama yang disimpan sebagai teks biasa ditukar kepada HTML selamat (baris baharu dikekalkan).
 */
class RichText
{
    public static function clean(?string $value): string
    {
        $value = (string) $value;

        if (! self::isHtml($value)) {
            $value = self::fromPlain($value);
        }

        return trim(Purifier::clean($value, 'permohonan'));
    }

    /**
     * Teks sahaja (tanpa tag), untuk semakan panjang minimum dan paparan ringkas.
     */
    public static function plain(?string $value): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/div|\/p|\/li)\b[^>]*>/i', ' ', (string) $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    }

    /**
     * Nilai awal editor Trix: HTML bersih (teks biasa lama ditukar kepada HTML).
     */
    public static function forEditor(?string $value): string
    {
        return blank($value) ? '' : self::clean($value);
    }

    public static function toHtml(?string $value): HtmlString
    {
        return new HtmlString(blank($value) ? '' : self::clean($value));
    }

    /**
     * HTML daripada Trix sentiasa mengandungi tag blok/format yang dikenali; teks biasa lama yang
     * kebetulan mengandungi "<" (cth. "kos < RM10,000") tidak dianggap HTML.
     */
    private static function isHtml(string $value): bool
    {
        return (bool) preg_match('/<\/?(div|br|p|strong|b|em|i|del|a|h1|blockquote|pre|ul|ol|li)\b[^>]*>/i', $value);
    }

    private static function fromPlain(string $value): string
    {
        return '<div>' . nl2br(e(trim($value)), false) . '</div>';
    }
}
