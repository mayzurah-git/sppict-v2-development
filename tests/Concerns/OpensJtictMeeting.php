<?php

namespace Tests\Concerns;

use App\Models\Meeting;
use App\Models\MeetingName;
use App\Models\MeetingNumber;
use App\Models\MeetingYear;
use App\Models\User;

trait OpensJtictMeeting
{
    /**
     * Cipta mesyuarat yang sedang dibuka. Pilihan tambahan (bukan kolum):
     * 'code' (kod Nama Mesyuarat, lalai JTICTNS) dan 'series' ("Bil. N/YYYY", lalai "Bil. 1/2026").
     */
    protected function openJtictMeeting(array $attributes = []): Meeting
    {
        $code = $attributes['code'] ?? 'JTICTNS';
        $series = $attributes['series'] ?? 'Bil. 1/2026';
        unset($attributes['code'], $attributes['series']);

        preg_match('/(\d+)(?:\/(\d{4}))?/', $series, $match);
        $number = (int) ($match[1] ?? 1);
        $year = (int) ($match[2] ?? 2026);

        $meetingName = MeetingName::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'accepts_applications' => $code === 'JTICTNS'],
        );

        return Meeting::create($attributes + [
            'meeting_name_id' => $meetingName->id,
            'meeting_number_id' => MeetingNumber::firstOrCreate(['number' => $number])->id,
            'meeting_year_id' => MeetingYear::firstOrCreate(['year' => $year])->id,
            'meeting_date' => today()->addDays(30),
            'meeting_time' => '09:00',
            'venue' => 'Bilik Mesyuarat Ujian',
            'submission_opens_at' => today()->subDays(7),
            'submission_closes_at' => today()->addDays(7),
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);
    }
}
