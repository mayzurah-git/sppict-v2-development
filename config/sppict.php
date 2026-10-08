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

];
