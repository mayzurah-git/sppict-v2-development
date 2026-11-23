<?php

namespace Database\Seeders;

use App\Models\ServiceScheme;
use Illuminate\Database\Seeder;

class ServiceSchemeSeeder extends Seeder
{
    public function run(): void
    {
        $schemes = [
            'A' => 'Pengangkutan',
            'B' => 'Bakat dan Seni',
            'C' => 'Sains',
            'D' => 'Pendidikan',
            'E' => 'Ekonomi',
            'F' => 'Teknologi Maklumat',
            'G' => 'Pertanian',
            'J' => 'Kejuruteraan',
            'K' => 'Keselamatan',
            'L' => 'Perundangan',
            'M' => 'Tadbir dan Diplomatik',
            'N' => 'Tadbir dan Sokongan',
            'P' => 'Pencegahan',
            'Q' => 'Penyelidik',
            'S' => 'Sosial',
            'U' => 'Perubatan dan Kesihatan',
            'W' => 'Kewangan',
            'X' => 'Maritim',
        ];

        foreach ($schemes as $code => $name) {
            ServiceScheme::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
