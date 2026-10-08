<?php

// Hanya tetapan yang diubah daripada lalai Livewire. Tetapan lain menggunakan
// vendor/livewire/livewire/config/livewire.php (digabung pada peringkat atas).
return [

    /*
    |---------------------------------------------------------------------------
    | Muat Naik Fail Sementara
    |---------------------------------------------------------------------------
    |
    | Had 25MB bagi dokumen sokongan permohonan (Fasa 4). Pastikan php.ini juga
    | membenarkan saiz ini (upload_max_filesize & post_max_size).
    |
    */

    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:25600'], // 25MB
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
