<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Models\MeetingName;
use App\Models\MeetingNumber;
use App\Models\MeetingYear;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Mesyuarat JTICT contoh yang sedang dibuka untuk permohonan (persekitaran pembangunan sahaja).
 */
class MeetingSeeder extends Seeder
{
    public function run(): void
    {
        if (Meeting::currentJtict()) {
            return;
        }

        $creator = User::where('email', 'urussetia.mkk@ns.gov.my')->first() ?? User::orderBy('id')->first();
        $meetingName = MeetingName::where('accepts_applications', true)->ordered()->first();

        if (! $creator || ! $meetingName) {
            $this->command?->warn('MeetingSeeder dilangkau: tiada pengguna atau Nama Mesyuarat JTICT (jalankan MeetingMasterDataSeeder dahulu).');

            return;
        }

        $closes = today()->addDays(30);
        $meetingDate = $closes->copy()->addDays(14);

        Meeting::updateOrCreate(
            [
                'meeting_name_id' => $meetingName->id,
                'meeting_number_id' => MeetingNumber::firstOrCreate(['number' => 1])->id,
                'meeting_year_id' => MeetingYear::firstOrCreate(['year' => $meetingDate->year])->id,
            ],
            [
                'meeting_date' => $meetingDate->toDateString(),
                'meeting_time' => '09:00',
                'venue' => 'Bilik Mesyuarat BTM',
                'is_active' => true,
                'submission_opens_at' => today()->subDays(7)->toDateString(),
                'submission_closes_at' => $closes->toDateString(),
                'progress_update_deadline' => $closes->toDateString(),
                'agenda' => '<div>1. Pengesahan minit mesyuarat terdahulu</div><div>2. Pembentangan permohonan projek ICT</div>',
                'created_by' => $creator->id,
            ],
        );
    }
}
