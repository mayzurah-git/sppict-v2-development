<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kadar SST (%)
    |--------------------------------------------------------------------------
    |
    | Kadar Cukai Jualan & Perkhidmatan yang dikenakan ke atas Jumlah Kecil
    | perincian projek (Fasa 3). Kadar semasa disimpan bersama setiap
    | permohonan supaya rekod lama kekal tepat jika kadar berubah.
    |
    */

    'sst_rate' => (float) env('SST_RATE', 8),

    /*
    |--------------------------------------------------------------------------
    | Penyahaktifan Automatik Akaun Tidak Aktif (UM11A)
    |--------------------------------------------------------------------------
    |
    | Akaun Aktif (bukan Superadmin) dinyahaktifkan dengan sebab "Tidak Aktif
    | Melebihi 6 Bulan" jika tarikh rujukan terkemudian (log masuk terakhir,
    | akaun dicipta/diluluskan, status ditukar, atau tarikh ciri dilancar)
    | melebihi tempoh berikut. `baseline` = tarikh ciri dilancar; tetapkan
    | SPPICT_DORMANCY_BASELINE kepada tarikh go-live di persekitaran production.
    |
    */

    'dormancy' => [
        'months' => (int) env('SPPICT_DORMANCY_MONTHS', 6),
        'baseline' => env('SPPICT_DORMANCY_BASELINE', '2026-10-21'),
    ],

];
