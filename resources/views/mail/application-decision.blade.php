{{--
    PP4.3D: e-mel keputusan Pra JTICT / Mesyuarat JTICT.
    Templat Markdown Laravel Mail: menghasilkan versi HTML responsif dan versi teks biasa.
    Susun atur sendiri (bukan <x-mail::message>) supaya pengepala memaparkan "SPPICT v2.1"
    tanpa mengubah APP_NAME atau templat e-mel lain.
--}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
SPPICT v2.1
</x-mail::header>
</x-slot:header>

# {{ $heading }}

{{ $greeting }}

Dimaklumkan bahawa keputusan rasmi bagi permohonan projek berikut telah direkodkan dalam Sistem Pemantauan Projek ICT (SPPICT).

<x-mail::table>
| | |
|:--|:--|
| **No. Rujukan** | {{ str_replace('|', '\|', $referenceNumber) }} |
| **Nama Projek** | {{ str_replace('|', '\|', $title) }} |
| **Jabatan/Agensi** | {{ str_replace('|', '\|', $agencyName) }} |
| **Peringkat Keputusan** | {{ $stageLabel }} |
| **Keputusan** | **{{ $decisionLabel }}** |
| **Tarikh Keputusan** | {{ $decisionDate }} |
@if ($meetingName)
| **Mesyuarat** | {{ str_replace('|', '\|', $meetingName) }} |
| **Tarikh Mesyuarat** | {{ $meetingDate ?? '-' }} |
@endif
</x-mail::table>

{{ $outcome }}

**Ulasan / Catatan Keputusan:**

<x-mail::panel>
{{-- Ulasan teks biasa (ter-escape); baris baharu dikekalkan sebagai pemisah baris Markdown. --}}
{{ preg_replace('/\R/u', "  \n", $remarks ?? 'Tiada ulasan.') }}
</x-mail::panel>

<x-mail::button :url="$url">
Lihat Permohonan
</x-mail::button>

Pautan di atas membawa anda ke SPPICT. Sila log masuk untuk melihat butiran permohonan.

Sekian, terima kasih.

**Urus Setia JTICT**
Sistem Pemantauan Projek ICT (SPPICT)

<x-slot:subcopy>
<x-mail::subcopy>
E-mel ini dijana secara automatik oleh SPPICT v2.1. Sila jangan balas e-mel ini.
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} SPPICT v2.1 · Kerajaan Negeri Sembilan
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
