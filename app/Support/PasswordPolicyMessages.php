<?php

namespace App\Support;

/**
 * Mesej validation Bahasa Melayu bagi polisi kata laluan berpusat (UM7A), supaya semua borang
 * penetapan kata laluan memaparkan mesej yang sama.
 */
class PasswordPolicyMessages
{
    public static function forField(string $field = 'password', string $label = 'Kata Laluan'): array
    {
        return [
            "{$field}.required" => "{$label} wajib diisi.",
            "{$field}.min" => "{$label} mesti sekurang-kurangnya :min aksara.",
            "{$field}.letters" => "{$label} mesti mengandungi sekurang-kurangnya satu huruf.",
            "{$field}.mixed" => "{$label} mesti mengandungi huruf besar dan huruf kecil.",
            "{$field}.numbers" => "{$label} mesti mengandungi sekurang-kurangnya satu nombor.",
            "{$field}.symbols" => "{$label} mesti mengandungi sekurang-kurangnya satu simbol.",
            "{$field}.confirmed" => "Pengesahan {$label} tidak sepadan.",
            "{$field}.different" => "{$label} mesti berbeza daripada kata laluan semasa.",
        ];
    }
}
