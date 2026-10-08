<?php

namespace Tests\Feature;

use App\Livewire\Meetings\MeetingForm;
use App\Livewire\Meetings\MeetingList;
use App\Livewire\Meetings\MeetingNameManagement;
use App\Models\Agency;
use App\Models\Application;
use App\Models\Meeting;
use App\Models\MeetingName;
use App\Models\MeetingNumber;
use App\Models\MeetingYear;
use App\Models\User;
use App\Notifications\MeetingAnnouncement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\TestCase;

class MeetingManagementTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting;

    private User $urusSetia;
    private MeetingName $jtict;
    private MeetingName $jpict;
    private MeetingYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Role::create(['name' => 'pentadbir_urus_setia']);
        Role::create(['name' => 'superadmin']);

        $this->urusSetia = User::factory()->create();
        $this->urusSetia->assignRole('pentadbir_urus_setia');

        $this->jtict = MeetingName::create(['code' => 'JTICTNS', 'name' => 'Jawatankuasa Teknikal ICT Negeri Sembilan', 'accepts_applications' => true]);
        $this->jpict = MeetingName::create(['code' => 'JPICTNS', 'name' => 'Jawatankuasa Pemandu ICT Negeri Sembilan']);
        $this->year = MeetingYear::create(['year' => 2026]);

        foreach (range(1, 6) as $number) {
            MeetingNumber::create(['number' => $number]);
        }
    }

    // --- AKSES ---

    public function test_meeting_pages_are_accessible_to_urus_setia_only(): void
    {
        $meeting = $this->createMeeting();

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        foreach ([route('meetings.index'), route('meetings.create'), route('meetings.edit', $meeting->uuid), route('meetings.minutes', $meeting->uuid)] as $url) {
            $this->actingAs($this->urusSetia)->get($url)->assertOk();
            $this->actingAs($superadmin)->get($url)->assertForbidden();
        }
    }

    // --- DAFTAR ---

    public function test_meeting_can_be_registered(): void
    {
        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->set('agenda', '<div>1. Pengesahan minit<script>alert(1)</script></div><div onclick="x()">2. Hal-hal lain</div>')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('meetings.index'));

        $meeting = Meeting::sole();

        $this->assertSame('JTICTNS Bil. 1/2026', $meeting->label());
        $this->assertSame($this->jtict->id, $meeting->meeting_name_id);
        $this->assertSame($this->number(1)->id, $meeting->meeting_number_id);
        $this->assertSame($this->year->id, $meeting->meeting_year_id);
        $this->assertTrue($meeting->is_active);
        $this->assertTrue($meeting->submission_opens_at->isToday());
        $this->assertSame('09:30', substr($meeting->meeting_time, 0, 5));
        $this->assertSame($this->urusSetia->id, $meeting->created_by);
        $this->assertStringNotContainsString('script', $meeting->agenda);
        $this->assertStringNotContainsString('onclick', $meeting->agenda);
        $this->assertStringContainsString('Hal-hal lain', $meeting->agenda);
        Storage::disk('local')->assertExists($meeting->minutes_file_path);
        $this->assertStringStartsWith("meetings/{$meeting->uuid}/minutes/", $meeting->minutes_file_path);

        // Mesyuarat Aktif bagi JTICT terus dibuka untuk permohonan
        $this->assertTrue($meeting->is(Meeting::currentJtict()));
    }

    public function test_all_fields_are_required(): void
    {
        Livewire::actingAs($this->urusSetia)->test(MeetingForm::class)
            ->set('is_active', '')
            ->call('save')
            ->assertHasErrors([
                'meeting_name_id', 'meeting_number_id', 'meeting_year_id', 'meeting_date', 'meeting_time',
                'venue', 'is_active', 'submission_closes_at', 'progress_update_deadline', 'minutes', 'agenda',
            ]);

        $this->assertSame(0, Meeting::count());
    }

    public function test_agenda_with_only_empty_markup_is_rejected(): void
    {
        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->set('agenda', '<div><br></div><div>&nbsp;</div>')
            ->call('save')
            ->assertHasErrors('agenda');
    }

    public function test_minutes_must_be_a_real_pdf(): void
    {
        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->set('minutes', UploadedFile::fake()->createWithContent('minit.pdf', 'MZ' . str_repeat("\x90", 512)))
            ->assertHasErrors('minutes');
    }

    public function test_closing_date_rules(): void
    {
        $component = $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class));

        $component->set('submission_closes_at', today()->addDays(40)->toDateString())
            ->call('save')
            ->assertHasErrors(['submission_closes_at' => 'before']);

        $component->set('submission_closes_at', today()->subDay()->toDateString())
            ->call('save')
            ->assertHasErrors(['submission_closes_at' => 'after_or_equal']);
    }

    public function test_duplicate_meeting_is_rejected(): void
    {
        $this->createMeeting(['meeting_number_id' => $this->number(1)->id, 'is_active' => false]);

        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->call('save')
            ->assertHasErrors('meeting_number_id');

        $this->assertSame(1, Meeting::count());
    }

    public function test_inactive_master_data_cannot_be_selected(): void
    {
        $this->number(1)->update(['is_active' => false]);

        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->call('save')
            ->assertHasErrors(['meeting_number_id' => 'exists']);
    }

    // --- SATU MESYUARAT JTICT DIBUKA PADA SATU MASA ---

    public function test_only_one_jtict_meeting_can_be_open_at_a_time(): void
    {
        $open = $this->createMeeting(['meeting_number_id' => $this->number(2)->id]);

        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->call('save')
            ->assertHasErrors('is_active');

        // Didaftarkan sebagai Tidak Aktif dibenarkan
        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->set('is_active', '0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($open->is(Meeting::currentJtict()));
    }

    public function test_jpict_meeting_is_not_limited_by_jtict_window(): void
    {
        $this->createMeeting(['meeting_number_id' => $this->number(1)->id]);

        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->set('meeting_name_id', (string) $this->jpict->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Meeting::where('is_active', true)->count());
    }

    // --- SUSUNAN BIL. MESYUARAT (AMARAN, BUKAN SEKATAN) ---

    public function test_next_meeting_number_is_suggested(): void
    {
        $component = Livewire::actingAs($this->urusSetia)->test(MeetingForm::class)
            ->set('meeting_name_id', (string) $this->jtict->id)
            ->set('meeting_year_id', (string) $this->year->id)
            ->assertSet('meeting_number_id', (string) $this->number(1)->id)
            ->assertSee('Belum ada mesyuarat didaftarkan');

        $this->createMeeting(['meeting_number_id' => $this->number(1)->id, 'is_active' => false]);
        $this->createMeeting(['meeting_number_id' => $this->number(2)->id, 'is_active' => false]);

        $component->set('meeting_year_id', '')
            ->set('meeting_year_id', (string) $this->year->id)
            ->assertSet('meeting_number_id', (string) $this->number(3)->id)
            ->assertSee('Bil. terakhir didaftarkan');

        // JPICT mempunyai urutan sendiri
        $component->set('meeting_name_id', (string) $this->jpict->id)
            ->assertSet('meeting_number_id', (string) $this->number(1)->id);
    }

    public function test_skipped_number_shows_warning_and_saves_only_after_confirmation(): void
    {
        $this->createMeeting(['meeting_number_id' => $this->number(1)->id, 'is_active' => false]);

        $component = $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->set('meeting_number_id', (string) $this->number(4)->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSee('JTICTNS Bil. 2 dan 3/2026 belum didaftarkan sebelum Bil. 4/2026');

        $this->assertSame(1, Meeting::count());

        // Mesyuarat Khas: Urus Setia mengesahkan dan meneruskan
        $component->call('save', true)->assertRedirect(route('meetings.index'));

        $this->assertSame(2, Meeting::count());
    }

    public function test_date_out_of_order_shows_warning(): void
    {
        $this->createMeeting(['meeting_number_id' => $this->number(1)->id, 'is_active' => false, 'meeting_date' => today()->addDays(60)]);

        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->set('meeting_number_id', (string) $this->number(2)->id)
            ->call('save')
            ->assertSee('lebih awal daripada JTICTNS Bil. 1/2026');

        $this->assertSame(1, Meeting::count());
    }

    public function test_changing_a_field_clears_pending_warning(): void
    {
        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->set('meeting_number_id', (string) $this->number(3)->id)
            ->call('save')
            ->assertNotSet('sequenceWarnings', [])
            ->set('meeting_number_id', (string) $this->number(1)->id)
            ->assertSet('sequenceWarnings', [])
            ->call('save')
            ->assertRedirect(route('meetings.index'));
    }

    public function test_editing_other_fields_does_not_repeat_sequence_warning(): void
    {
        $meeting = $this->createMeeting(['meeting_number_id' => $this->number(5)->id]);

        Livewire::actingAs($this->urusSetia)->test(MeetingForm::class, ['uuid' => $meeting->uuid])
            ->assertSet('meeting_number_id', (string) $this->number(5)->id)
            ->set('venue', 'Dewan Baharu')
            ->call('save')
            ->assertRedirect(route('meetings.index'));

        $this->assertSame('Dewan Baharu', $meeting->fresh()->venue);
    }

    // --- KEMASKINI ---

    public function test_meeting_can_be_updated_without_reuploading_minutes(): void
    {
        $meeting = $this->createMeeting(['is_active' => false, 'submission_opens_at' => null]);

        Livewire::actingAs($this->urusSetia)->test(MeetingForm::class, ['uuid' => $meeting->uuid])
            ->assertSet('venue', 'Bilik Mesyuarat Ujian')
            ->assertSet('is_active', '0')
            ->set('venue', 'Dewan Utama')
            ->set('is_active', '1')
            ->call('save')
            ->assertHasNoErrors();

        $meeting->refresh();
        $this->assertSame('Dewan Utama', $meeting->venue);
        $this->assertSame('meetings/lama/minit.pdf', $meeting->minutes_file_path);
        $this->assertSame($this->urusSetia->id, $meeting->updated_by);

        // Ditukar kepada Aktif: tempoh permohonan dibuka mulai hari ini
        $this->assertTrue($meeting->submission_opens_at->isToday());
        $this->assertTrue($meeting->is(Meeting::currentJtict()));
    }

    public function test_setting_meeting_inactive_closes_submission(): void
    {
        $meeting = $this->createMeeting();
        $this->assertTrue($meeting->is(Meeting::currentJtict()));

        Livewire::actingAs($this->urusSetia)->test(MeetingForm::class, ['uuid' => $meeting->uuid])
            ->set('is_active', '0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Meeting::currentJtict());
    }

    public function test_meeting_name_cannot_change_once_applications_are_linked(): void
    {
        $meeting = $this->createMeeting();
        $this->linkApplication($meeting);

        Livewire::actingAs($this->urusSetia)->test(MeetingForm::class, ['uuid' => $meeting->uuid])
            ->set('meeting_name_id', (string) $this->jpict->id)
            ->call('save')
            ->assertHasErrors('meeting_name_id');
    }

    // --- SENARAI & PADAM ---

    public function test_list_shows_date_time_venue_and_minutes(): void
    {
        $meeting = $this->createMeeting(['meeting_date' => '2026-12-15', 'meeting_time' => '14:30']);

        Livewire::actingAs($this->urusSetia)->test(MeetingList::class)
            ->assertSee('JTICTNS Bil. 1/2026')
            ->assertSee('15/12/2026')
            ->assertSee('2.30 petang')
            ->assertSee('Bilik Mesyuarat Ujian')
            ->assertSee(route('meetings.minutes', $meeting->uuid));
    }

    public function test_filters_can_be_reset(): void
    {
        $this->createMeeting(['meeting_number_id' => $this->number(1)->id, 'venue' => 'Dewan Utama']);
        $this->createMeeting(['meeting_number_id' => $this->number(2)->id, 'meeting_name_id' => $this->jpict->id, 'is_active' => false]);

        Livewire::actingAs($this->urusSetia)->test(MeetingList::class)
            ->set('search', 'Dewan')
            ->set('meetingNameId', (string) $this->jtict->id)
            ->set('meetingYearId', (string) $this->year->id)
            ->set('status', '1')
            ->assertSee('JTICTNS Bil. 1/2026')
            ->assertDontSee('JPICTNS Bil. 2/2026')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('meetingNameId', '')
            ->assertSet('meetingYearId', '')
            ->assertSet('status', '')
            ->assertSee('JTICTNS Bil. 1/2026')
            ->assertSee('JPICTNS Bil. 2/2026');
    }

    public function test_meeting_time_is_shown_in_12_hour_format_with_pagi_or_petang(): void
    {
        $cases = [
            '00:00' => '12.00 pagi',
            '09:05' => '9.05 pagi',
            '11:45' => '11.45 pagi',
            '12:00' => '12.00 petang',
            '14:30' => '2.30 petang',
            '20:00' => '8.00 petang',
        ];

        foreach ($cases as $time => $label) {
            $this->assertSame($label, (new Meeting(['meeting_time' => $time]))->meetingTimeLabel(), $time);
        }
    }

    public function test_minutes_can_be_downloaded(): void
    {
        $meeting = $this->createMeeting();

        $this->actingAs($this->urusSetia)
            ->get(route('meetings.minutes', $meeting->uuid))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_meeting_with_applications_cannot_be_deleted(): void
    {
        $linked = $this->createMeeting(['meeting_number_id' => $this->number(1)->id]);
        $this->linkApplication($linked);
        $unused = $this->createMeeting(['meeting_number_id' => $this->number(2)->id, 'is_active' => false]);

        Livewire::actingAs($this->urusSetia)->test(MeetingList::class)
            ->call('delete', $linked->uuid)
            ->call('delete', $unused->uuid);

        $this->assertNotSoftDeleted('meetings', ['id' => $linked->id]);
        $this->assertSoftDeleted('meetings', ['id' => $unused->id]);
    }

    public function test_master_data_in_use_cannot_be_deleted(): void
    {
        $this->createMeeting();

        Livewire::actingAs($this->urusSetia)->test(MeetingNameManagement::class)
            ->call('delete', $this->jtict->uuid);

        $this->assertNotSoftDeleted('meeting_names', ['id' => $this->jtict->id]);
    }

    // --- MAKLUMAN EMEL ---

    public function test_notification_is_sent_to_active_members_except_superadmin(): void
    {
        Notification::fake();
        [$pengguna, $pengurusan, $superadmin, $deleted, $noRole] = $this->createRecipientsScenario();

        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->set('send_notification', true)
            ->call('save')
            ->assertHasNoErrors();

        $meeting = Meeting::sole();
        $this->assertNotNull($meeting->notified_at);

        Notification::assertSentTo([$pengguna, $pengurusan, $this->urusSetia], MeetingAnnouncement::class,
            fn (MeetingAnnouncement $notification) => $notification->meeting->is($meeting) && ! $notification->isUpdate);
        Notification::assertNotSentTo([$superadmin, $deleted, $noRole], MeetingAnnouncement::class);
        Notification::assertCount(3);

        $this->assertStringContainsString('Notifikasi e-mel sedang dihantar kepada 3 ahli mesyuarat', session('message'));
    }

    public function test_no_notification_when_checkbox_is_not_ticked(): void
    {
        Notification::fake();

        $this->fillForm(Livewire::actingAs($this->urusSetia)->test(MeetingForm::class))
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
        $this->assertNull(Meeting::sole()->notified_at);
    }

    public function test_update_notification_is_marked_as_update(): void
    {
        Notification::fake();
        $meeting = $this->createMeeting();

        Livewire::actingAs($this->urusSetia)->test(MeetingForm::class, ['uuid' => $meeting->uuid])
            ->assertSet('send_notification', false)
            ->set('venue', 'Dewan Baharu')
            ->set('send_notification', true)
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($this->urusSetia, MeetingAnnouncement::class, fn (MeetingAnnouncement $notification) => $notification->isUpdate);
        $this->assertNotNull($meeting->fresh()->notified_at);
    }

    public function test_announcement_is_queued_and_mail_contains_meeting_details(): void
    {
        $meeting = $this->createMeeting([
            'meeting_date' => '2026-12-15',
            'meeting_time' => '14:30',
            'agenda' => '<div>1. Pengesahan minit</div><ul><li>Hal &amp; lain-lain</li></ul>',
        ]);

        $notification = new MeetingAnnouncement($meeting, isUpdate: true);
        $this->assertInstanceOf(ShouldQueue::class, $notification);

        $mail = $notification->toMail($this->urusSetia);
        $lines = implode("\n", $mail->introLines);

        $this->assertSame('[SPPICT] Makluman Kemaskini Mesyuarat JTICTNS Bil. 1/2026', $mail->subject);
        $this->assertStringContainsString('Tarikh: 15/12/2026', $lines);
        $this->assertStringContainsString('Masa: 2.30 petang', $lines);
        $this->assertStringContainsString('Tempat: Bilik Mesyuarat Ujian', $lines);
        $this->assertStringContainsString("1. Pengesahan minit\nHal & lain-lain", $lines);
        $this->assertSame(route('meetings.minutes', $meeting->uuid), $mail->actionUrl);

        $html = (string) $mail->render();
        $this->assertStringContainsString('Lihat Minit Mesyuarat Terdahulu', $html);
        $this->assertStringContainsString('Masa: 2.30 petang', $html);
    }

    public function test_members_can_open_minutes_but_superadmin_cannot(): void
    {
        $meeting = $this->createMeeting();
        [$pengguna, $pengurusan, $superadmin] = $this->createRecipientsScenario();

        foreach ([$pengguna, $pengurusan, $this->urusSetia] as $user) {
            $this->actingAs($user)->get(route('meetings.minutes', $meeting->uuid))->assertOk();
        }

        $this->actingAs($superadmin)->get(route('meetings.minutes', $meeting->uuid))->assertForbidden();
    }

    // --- PEMBANTU ---

    /**
     * @return array{0: User, 1: User, 2: User, 3: User, 4: User} pengguna, pengurusan, superadmin, dipadam, tiada peranan
     */
    private function createRecipientsScenario(): array
    {
        Role::firstOrCreate(['name' => 'pengguna_biasa']);
        Role::firstOrCreate(['name' => 'pengurusan']);

        $pengguna = User::factory()->create();
        $pengguna->assignRole('pengguna_biasa');

        $pengurusan = User::factory()->create();
        $pengurusan->assignRole('pengurusan');

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $deleted = User::factory()->create();
        $deleted->assignRole('pengguna_biasa');
        $deleted->delete();

        return [$pengguna, $pengurusan, $superadmin, $deleted, User::factory()->create()];
    }

    private function fillForm(Testable $component): Testable
    {
        return $component
            ->set('meeting_name_id', (string) $this->jtict->id)
            ->set('meeting_year_id', (string) $this->year->id)
            ->set('meeting_number_id', (string) $this->number(1)->id)
            ->set('meeting_date', today()->addDays(30)->toDateString())
            ->set('meeting_time', '09:30')
            ->set('venue', 'Bilik Mesyuarat Utama')
            ->set('is_active', '1')
            ->set('submission_closes_at', today()->addDays(14)->toDateString())
            ->set('progress_update_deadline', today()->addDays(20)->toDateString())
            ->set('minutes', UploadedFile::fake()->createWithContent('minit.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"))
            ->set('agenda', '<div>1. Pengesahan minit mesyuarat terdahulu</div>');
    }

    private function createMeeting(array $attributes = []): Meeting
    {
        Storage::disk('local')->put('meetings/lama/minit.pdf', '%PDF-1.4 lama');

        return Meeting::create($attributes + [
            'meeting_name_id' => $this->jtict->id,
            'meeting_number_id' => $this->number(1)->id,
            'meeting_year_id' => $this->year->id,
            'meeting_date' => today()->addDays(30),
            'meeting_time' => '09:00',
            'venue' => 'Bilik Mesyuarat Ujian',
            'is_active' => true,
            'submission_opens_at' => today()->subDays(3),
            'submission_closes_at' => today()->addDays(10),
            'progress_update_deadline' => today()->addDays(20),
            'agenda' => '<div>Agenda</div>',
            'minutes_file_path' => 'meetings/lama/minit.pdf',
            'created_by' => $this->urusSetia->id,
        ]);
    }

    private function number(int $number): MeetingNumber
    {
        return MeetingNumber::firstWhere('number', $number);
    }

    private function linkApplication(Meeting $meeting): Application
    {
        $agency = Agency::firstOrCreate(['code' => 'JKR'], ['name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $applicant = User::factory()->create(['agency_id' => $agency->id]);

        return Application::create([
            'reference_number' => 'SPPICT/2026/' . str_pad((string) (Application::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'agency_id' => $agency->id,
            'applicant_id' => $applicant->id,
            'title' => 'Projek Ujian',
            'estimated_cost' => 10000,
            'status' => 'SUBMITTED',
            'jtict_meeting_id' => $meeting->id,
        ]);
    }
}
