<?php

namespace Database\Seeders;

use App\Models\MeetingName;
use App\Models\MeetingNumber;
use App\Models\MeetingYear;
use Illuminate\Database\Seeder;

/**
 * Data selenggara awal Pengurusan Mesyuarat: Nama, Bilangan dan Tahun Mesyuarat.
 */
class MeetingMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            ['code' => 'JTICTNS', 'name' => 'Jawatankuasa Teknikal ICT Negeri Sembilan', 'accepts_applications' => true],
            ['code' => 'JPICTNS', 'name' => 'Jawatankuasa Pemandu ICT Negeri Sembilan', 'accepts_applications' => false],
        ];

        foreach ($names as $order => $name) {
            MeetingName::firstOrCreate(
                ['code' => $name['code']],
                $name + ['sort_order' => $order + 1, 'is_active' => true],
            );
        }

        foreach (range(1, 6) as $number) {
            MeetingNumber::firstOrCreate(['number' => $number], ['is_active' => true]);
        }

        foreach ([now()->year, now()->year + 1] as $year) {
            MeetingYear::firstOrCreate(['year' => $year], ['is_active' => true]);
        }
    }
}
