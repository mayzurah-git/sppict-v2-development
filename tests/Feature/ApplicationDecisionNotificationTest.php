<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Enums\OfficerEndReason;
use App\Enums\OfficerRole;
use App\Enums\UserStatus;
use App\Livewire\ViewApplication;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\ApplicationOfficer;
use App\Models\User;
use App\Notifications\ApplicationDecisionRecorded;
use App\Services\ApplicationDecisionService;
use App\Services\ApplicationReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Concerns\OpensJtictMeeting;
use Tests\TestCase;

/**
 * PP4.3D: notifikasi e-mel keputusan Pra JTICT dan Mesyuarat JTICT kepada Pendaftar Permohonan
 * dan Pegawai Utama aktif.
 *
 * Nota: SQLite in-memory, queue `sync` dan mailer `array`/fake (phpunit.xml); tiada e-mel sebenar dihantar.
 * Tidak membuktikan penghantaran sebenar melalui SMTP / Mailtrap atau worker queue `database`.
 */
class ApplicationDecisionNotificationTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting;

    private const REMARKS = 'Ulasan rasmi keputusan bagi permohonan ini.';

    private Agency $agency;

    private User $urusSetia;

    private User $registrar;

    private User $officer;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Jabatan Kerja Raya', 'category' => 'SUK', 'is_active' => true]);
        $this->urusSetia = $this->user(['pentadbir_urus_setia'], ['name' => 'Siti Urus Setia', 'email' => 'urus.setia@ns.gov.my']);
        $this->registrar = $this->user(['pengguna_biasa'], ['name' => 'Ali Pendaftar', 'email' => 'ali.pendaftar@ns.gov.my']);
        $this->officer = $this->user(['pengguna_biasa'], ['name' => 'Bakar Pegawai Utama', 'email' => 'bakar.utama@ns.gov.my']);
    }

    // --- A: PRA JTICT (1–3), C: PENERIMA (7, 8, 10, 11), E: TAJUK (20) ---

    public function test_each_pre_jtict_decision_notifies_registrar_and_primary_officer(): void
    {
        $subjects = [
            Decision::Accepted->value => '[SPPICT] Keputusan Pra JTICT – Diterima – ',
            Decision::AmendmentRequired->value => '[SPPICT] Keputusan Pra JTICT – Ditolak dengan Pindaan – ',
            Decision::Rejected->value => '[SPPICT] Keputusan Pra JTICT – Ditolak – ',
        ];

        foreach ([Decision::Accepted, Decision::AmendmentRequired, Decision::Rejected] as $decision) {
            Notification::fake();
            $application = $this->completeApplication("Projek Pra {$decision->value}");
            $secondary = $this->addOfficer($application, OfficerRole::Secondary);

            $record = $this->decide($application, DecisionStage::PreJtict, $decision, self::REMARKS);

            $this->assertRecipients($record, [$this->registrar, $this->officer], "Pra JTICT {$decision->value}");
            Notification::assertNotSentTo($secondary, ApplicationDecisionRecorded::class);
            Notification::assertNotSentTo($this->urusSetia, ApplicationDecisionRecorded::class);

            $this->assertSame($subjects[$decision->value] . $application->reference_number, $this->mailFor($record)->subject);
        }
    }

    // --- B: JTICT (4–6) ---

    public function test_each_jtict_decision_notifies_registrar_and_primary_officer(): void
    {
        foreach ([Decision::Approved, Decision::NotApproved, Decision::Review] as $decision) {
            $application = $this->inJtictApplication("Projek JTICT {$decision->value}");
            $secondary = $this->addOfficer($application, OfficerRole::Secondary);
            Notification::fake(); // abaikan notifikasi Pra JTICT semasa penyediaan

            $record = $this->decide($application, DecisionStage::Jtict, $decision, self::REMARKS);

            $this->assertRecipients($record, [$this->registrar, $this->officer], "JTICT {$decision->value}");
            Notification::assertNotSentTo($secondary, ApplicationDecisionRecorded::class);
            Notification::assertNotSentTo($this->urusSetia, ApplicationDecisionRecorded::class);

            $this->assertSame(
                "[SPPICT] Keputusan Mesyuarat JTICT – {$decision->label()} – {$application->reference_number}",
                $this->mailFor($record)->subject
            );
        }
    }

    public function test_decision_through_ui_notifies_and_confirms_decision_to_urus_setia(): void
    {
        Notification::fake();
        $application = $this->completeApplication('Projek Melalui UI');

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openPreJtictModal', Decision::Rejected->value)
            ->set('preJtictDate', today()->toDateString())
            ->set('preJtictRemarks', self::REMARKS)
            ->call('submitPreJtictDecision')
            ->assertHasNoErrors()
            ->assertSee('Keputusan Pra JTICT direkodkan: Ditolak');

        $this->assertRecipients($application->decisions()->sole(), [$this->registrar, $this->officer]);
    }

    // --- C: URUS SETIA SEBAGAI PENDAFTAR (11) ---

    public function test_urus_setia_receives_only_when_they_are_the_registrar(): void
    {
        Notification::fake();
        $application = $this->completeApplication('Projek Didaftar Urus Setia', ['applicant_id' => $this->urusSetia->id]);

        $record = $this->decide($application, DecisionStage::PreJtict, Decision::Rejected);

        $this->assertRecipients($record, [$this->urusSetia, $this->officer]);
    }

    // --- C: PEGAWAI UTAMA TIDAK AKTIF (9) ---

    public function test_inactive_primary_officer_is_not_notified(): void
    {
        $cases = [
            'lantikan tamat' => function (Application $application) {
                $application->primaryOfficer->forceFill(['ended_at' => now(), 'end_reason' => OfficerEndReason::Reassignment])->save();
            },
            'akaun tidak aktif' => fn () => $this->officer->forceFill(['status' => UserStatus::Inactive])->save(),
            'bertukar agensi' => fn () => $this->officer->forceFill([
                'agency_id' => Agency::create(['code' => 'JPN', 'name' => 'Agensi Lain', 'category' => 'SUK', 'is_active' => true])->id,
            ])->save(),
            'akaun dipadam' => fn () => $this->officer->delete(),
        ];

        foreach ($cases as $label => $deactivate) {
            Notification::fake();
            $this->officer = $this->user(['pengguna_biasa'], ['email' => "utama.{$this->sequence}@ns.gov.my"]);
            $application = $this->completeApplication("Projek {$label}");
            $deactivate($application);

            $record = $this->decide($application, DecisionStage::PreJtict, Decision::Rejected);

            $this->assertRecipients($record, [$this->registrar], $label);
            Notification::assertNotSentTo($this->officer, ApplicationDecisionRecorded::class);
            $this->assertNotNull($record->fresh(), "Keputusan disimpan ({$label})");
        }
    }

    public function test_primary_officer_without_account_is_notified_at_snapshot_email(): void
    {
        Notification::fake();
        $application = $this->completeApplication('Projek Pegawai Snapshot');
        $application->primaryOfficer->forceFill(['user_id' => null, 'email' => 'pegawai.luar@ns.gov.my'])->save();

        $record = $this->decide($application, DecisionStage::PreJtict, Decision::Rejected);

        Notification::assertSentToTimes($this->registrar, ApplicationDecisionRecorded::class, 1);
        Notification::assertSentOnDemand(ApplicationDecisionRecorded::class,
            fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'pegawai.luar@ns.gov.my'
                && $notification->decision->is($record));
        Notification::assertCount(2);
    }

    // --- C: E-MEL SAMA (12) ---

    public function test_same_email_for_registrar_and_primary_officer_receives_single_notification(): void
    {
        // Pendaftar sendiri ialah Pegawai Utama
        Notification::fake();
        $application = $this->completeApplication('Projek Pendaftar Pegawai Utama', [], $this->registrar);
        $this->decide($application, DecisionStage::PreJtict, Decision::Rejected);

        Notification::assertSentToTimes($this->registrar, ApplicationDecisionRecorded::class, 1);
        Notification::assertCount(1);

        // Pegawai Utama tanpa akaun dengan e-mel snapshot sama (huruf besar/kecil berbeza) dengan Pendaftar
        Notification::fake();
        $application = $this->completeApplication('Projek E-mel Sama');
        $application->primaryOfficer->forceFill(['user_id' => null, 'email' => 'ALI.Pendaftar@ns.gov.my'])->save();
        $this->decide($application, DecisionStage::PreJtict, Decision::Rejected);

        Notification::assertSentToTimes($this->registrar, ApplicationDecisionRecorded::class, 1);
        Notification::assertCount(1);
    }

    // --- C: TIADA E-MEL SAH (13) ---

    public function test_recipients_without_valid_email_are_skipped_without_failing_decision(): void
    {
        Notification::fake();
        Log::spy();

        // Pegawai Utama tanpa e-mel sah: Pendaftar masih menerima
        $application = $this->completeApplication('Projek Pegawai Tiada E-mel');
        $this->officer->forceFill(['email' => 'bukan-alamat-emel'])->save();
        $record = $this->decide($application, DecisionStage::PreJtict, Decision::Rejected);

        $this->assertRecipients($record, [$this->registrar]);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'tidak dihantar kepada Pegawai Utama: alamat e-mel tiada atau tidak sah'));

        // Tiada penerima sah langsung: keputusan tetap direkodkan
        Notification::fake();
        $this->registrar->forceFill(['email' => 'tiada-domain'])->save();
        $application = $this->completeApplication('Projek Tiada Penerima');
        $record = $this->decide($application, DecisionStage::PreJtict, Decision::Rejected);

        $this->assertSame(ApplicationStatus::Closed->value, $application->fresh()->status);
        $this->assertNotNull($record->fresh());
        Notification::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'tiada penerima dengan alamat e-mel sah'));
    }

    // --- D: INTEGRITI KEPUTUSAN (14–19) ---

    public function test_decision_is_kept_when_mail_transport_fails(): void
    {
        // Laluan sebenar (tanpa fake): queue sync + transport yang sentiasa gagal (simulasi SMTP gagal)
        Mail::extend('gagal', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Failed to authenticate on SMTP server with username "rahsia.smtp@contoh.test".');
            }

            public function __toString(): string
            {
                return 'gagal://';
            }
        });
        config(['mail.mailers.gagal' => ['transport' => 'gagal'], 'mail.default' => 'gagal']);
        Log::spy();

        $application = $this->completeApplication('Projek SMTP Gagal');

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openPreJtictModal', Decision::AmendmentRequired->value)
            ->set('preJtictDate', today()->toDateString())
            ->set('preJtictRemarks', self::REMARKS)
            ->call('submitPreJtictDecision')
            ->assertHasNoErrors()
            ->assertSee('Keputusan Pra JTICT direkodkan: Ditolak dengan Pindaan');

        $record = $application->decisions()->sole();
        $this->assertSame(Decision::AmendmentRequired, $record->decision);
        $this->assertSame(ApplicationStatus::PreJtictAmendment->value, $application->fresh()->status);

        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'gagal')
            && str_contains($message, $application->reference_number)
            && ! str_contains($message, 'rahsia.smtp@contoh.test'));
        Log::shouldNotHaveReceived('error', [\Mockery::on(fn ($message) => str_contains((string) $message, 'rahsia.smtp'))]);
    }

    public function test_decision_is_kept_when_notification_dispatch_throws(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('Sambungan queue gagal.'));
        Notification::shouldReceive('route')->andReturnUsing(fn ($channel, $route) => (new AnonymousNotifiable)->route($channel, $route));
        Log::spy();

        $application = $this->inJtictApplication('Projek Queue Gagal');
        $record = $this->decide($application, DecisionStage::Jtict, Decision::Approved, self::REMARKS);

        $this->assertSame(Decision::Approved, $record->fresh()->decision);
        $this->assertSame(ApplicationStatus::Closed->value, $application->fresh()->status);
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'gagal diserahkan') && str_contains($message, 'Sambungan queue gagal.'));
    }

    public function test_queue_failure_handler_logs_without_credentials(): void
    {
        Notification::fake();
        Log::spy();
        $application = $this->completeApplication('Projek Kegagalan Queue');
        $record = $this->decide($application, DecisionStage::PreJtict, Decision::Rejected);

        (new ApplicationDecisionRecorded($record))->failed(new TransportException('Failed to authenticate on SMTP server with username "rahsia.smtp@contoh.test" using 2 possible authenticators.'));

        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, $application->reference_number)
            && str_contains($message, '[dirahsiakan]')
            && ! str_contains($message, 'rahsia.smtp'));
        $this->assertSame(1, ApplicationDecision::count());
    }

    public function test_failed_decision_does_not_send_notification(): void
    {
        Notification::fake();
        $application = $this->completeApplication('Projek Gagal Disimpan');

        // Ulasan wajib tiada
        try {
            $this->decide($application, DecisionStage::PreJtict, Decision::AmendmentRequired, '');
            $this->fail('Keputusan sepatutnya gagal validasi');
        } catch (ValidationException) {
        }

        // Pengguna tidak dibenarkan
        try {
            app(ApplicationDecisionService::class)->decide($application->fresh(), DecisionStage::PreJtict, Decision::Rejected, null, today(), $this->registrar);
            $this->fail('Keputusan sepatutnya ditolak');
        } catch (AuthorizationException) {
        }

        // Melalui UI: validasi gagal
        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openPreJtictModal', Decision::AmendmentRequired->value)
            ->set('preJtictDate', today()->toDateString())
            ->set('preJtictRemarks', '')
            ->call('submitPreJtictDecision')
            ->assertHasErrors(['preJtictRemarks']);

        $this->assertSame(0, ApplicationDecision::count());
        Notification::assertNothingSent();
    }

    public function test_sending_does_not_change_decision_record_and_uses_official_record(): void
    {
        // Laluan sebenar dengan mailer `array`: rekod keputusan dibandingkan sebelum & selepas penghantaran
        $application = $this->inJtictApplication('Projek Rekod Rasmi');
        $application->forceFill(['jtict_remarks' => 'ULASAN LEGACY TIDAK DIGUNAKAN', 'jtict_decision' => 'PENDING'])->save();
        $preJtictBefore = ApplicationDecision::orderBy('id')->get()->map->getAttributes()->all();

        $record = $this->decide($application, DecisionStage::Jtict, Decision::NotApproved, self::REMARKS);

        $this->assertEquals($record->getAttributes(), $record->fresh()->getAttributes(), 'Rekod keputusan tidak berubah');
        $this->assertSame($preJtictBefore, ApplicationDecision::where('stage', 'PRE_JTICT')->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame('PENDING', $application->fresh()->jtict_decision);

        $sent = collect(app('mailer')->getSymfonyTransport()->messages())
            ->filter(fn ($message) => str_contains($message->getOriginalMessage()->getSubject(), 'Mesyuarat JTICT'));
        $this->assertCount(2, $sent, 'Pendaftar & Pegawai Utama');

        foreach ($sent as $message) {
            $email = $message->getOriginalMessage();
            $this->assertSame("[SPPICT] Keputusan Mesyuarat JTICT – Tidak Lulus – {$application->reference_number}", $email->getSubject());
            $this->assertStringContainsString(self::REMARKS, $email->getHtmlBody());
            $this->assertStringContainsString(self::REMARKS, $email->getTextBody());
            $this->assertStringNotContainsString('ULASAN LEGACY', $email->getHtmlBody());
            $this->assertEmpty($email->getAttachments(), 'Tiada lampiran');
        }

        $this->assertEqualsCanonicalizing(
            [$this->registrar->email, $this->officer->email],
            $sent->map(fn ($message) => $message->getOriginalMessage()->getTo()[0]->getAddress())->values()->all()
        );
    }

    public function test_viewing_or_rerendering_application_does_not_send_notification(): void
    {
        $application = $this->inJtictApplication('Projek Dipaparkan');
        $this->decide($application, DecisionStage::Jtict, Decision::Review, self::REMARKS);
        Notification::fake();

        foreach ([$this->urusSetia, $this->registrar, $this->officer] as $user) {
            Livewire::actingAs($user)->test(ViewApplication::class, ['uuid' => $application->uuid])
                ->assertSee('Sejarah Keputusan Permohonan')
                ->call('$refresh')
                ->assertOk();

            $this->actingAs($user)->get(route('application.view', $application->uuid))->assertOk();
        }

        Notification::assertNothingSent();
    }

    public function test_repeated_submission_of_same_decision_sends_only_once(): void
    {
        Notification::fake();
        $application = $this->completeApplication('Projek Klik Berganda');

        // Klik berganda: hantar dua kali daripada halaman yang sama (borang modal diisi semula)
        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openPreJtictModal', Decision::Rejected->value)
            ->set('preJtictDate', today()->toDateString())
            ->set('preJtictRemarks', self::REMARKS)
            ->call('submitPreJtictDecision')
            ->assertSee('Keputusan Pra JTICT direkodkan: Ditolak')
            ->set('preJtictDecision', Decision::Rejected->value)
            ->set('preJtictDate', today()->toDateString())
            ->set('preJtictRemarks', self::REMARKS)
            ->call('submitPreJtictDecision')
            ->assertSee('telah direkodkan atau permohonan tidak lagi menunggu keputusan');

        // Panggilan service berulang
        try {
            $this->decide($application, DecisionStage::PreJtict, Decision::Rejected);
            $this->fail('Keputusan kedua sepatutnya ditolak');
        } catch (AuthorizationException) {
        }

        $this->assertSame(1, ApplicationDecision::count());
        Notification::assertSentToTimes($this->registrar, ApplicationDecisionRecorded::class, 1);
        Notification::assertSentToTimes($this->officer, ApplicationDecisionRecorded::class, 1);
        Notification::assertCount(2);
        $this->assertSame(1, (new ApplicationDecisionRecorded($application->decisions()->sole()))->tries, 'Tiada percubaan semula automatik oleh queue');
    }

    // --- E: KANDUNGAN (20–25) ---

    public function test_pre_jtict_mail_content(): void
    {
        Notification::fake();
        $application = $this->completeApplication('Sistem Pengurusan Aset | Fasa 2');
        $this->travelTo(today()->addDay()->setTime(10, 0));
        $record = $this->decide($application, DecisionStage::PreJtict, Decision::Accepted, null, today());
        $this->travelBack();

        [$html, $text] = $this->render($record);
        $meeting = $application->jtictMeeting;

        foreach ([$html, $text] as $body) {
            $this->assertStringContainsString('SPPICT v2.1', $body);
            $this->assertStringContainsString('Makluman Keputusan Pra JTICT', $body);
            $this->assertStringContainsString($application->reference_number, $body);
            $this->assertStringContainsString('Sistem Pengurusan Aset', $body);
            $this->assertStringContainsString('Fasa 2', $body);
            $this->assertStringContainsString('Jabatan Kerja Raya', $body);
            $this->assertStringContainsString('Diterima', $body);
            $this->assertStringContainsString($record->decision_date->format('d/m/Y'), $body);
            $this->assertStringContainsString($meeting->label(), $body);
            $this->assertStringContainsString($meeting->meeting_date->format('d/m/Y'), $body);
            $this->assertStringContainsString('diterima dalam Pra JTICT untuk dibawa ke Mesyuarat JTICT', $body);
            $this->assertStringContainsString('Tiada ulasan.', $body);
            $this->assertStringContainsString(route('application.view', $application->uuid), $body);
            $this->assertStringNotContainsString('App\\', $body);
            $this->assertStringNotContainsString('application_decisions', $body);
        }

        $this->assertSame(today()->addDay()->format('d/m/Y'), $record->decision_date->format('d/m/Y'));
        $this->assertStringStartsWith(rtrim(config('app.url'), '/') . '/', route('application.view', $application->uuid), 'Pautan menggunakan APP_URL');
        $this->assertStringContainsString('Ali Pendaftar', $this->mailFor($record)->render());

        $amend = $this->decide($this->completeApplication('Projek Pindaan'), DecisionStage::PreJtict, Decision::AmendmentRequired, self::REMARKS);
        [$amendHtml] = $this->render($amend);
        $this->assertStringContainsString('hantar semula permohonan mengikut proses sedia ada', $amendHtml);
        $this->assertStringContainsString(self::REMARKS, $amendHtml);

        $reject = $this->decide($this->completeApplication('Projek Ditolak'), DecisionStage::PreJtict, Decision::Rejected, "Baris pertama ulasan.\n<script>alert(1)</script>");
        [$rejectHtml] = $this->render($reject);
        $this->assertStringContainsString('ditolak dalam Pra JTICT dan tidak diteruskan', $rejectHtml);
        $this->assertStringNotContainsString('<script>', $rejectHtml, 'Ulasan dipaparkan ter-escape');
    }

    public function test_jtict_mail_content_includes_meeting_and_outcome(): void
    {
        $outcomes = [
            Decision::Approved->value => 'Modul Status Kemajuan Projek apabila modul tersebut tersedia',
            Decision::NotApproved->value => 'tidak diluluskan oleh Mesyuarat JTICT',
            Decision::Review->value => 'Salin Permohonan',
        ];

        foreach ([Decision::Approved, Decision::NotApproved, Decision::Review] as $decision) {
            $application = $this->inJtictApplication("Projek Kandungan {$decision->value}", today()->subDays(2));
            $record = $this->decide($application, DecisionStage::Jtict, $decision, self::REMARKS, today()->subDay());
            $meeting = $application->jtictMeeting;

            [$html, $text] = $this->render($record);

            foreach ([$html, $text] as $body) {
                $this->assertStringContainsString('Makluman Keputusan Mesyuarat JTICT', $body);
                $this->assertStringContainsString($decision->label(), $body);
                $this->assertStringContainsString(today()->subDay()->format('d/m/Y'), $body, 'Tarikh keputusan');
                $this->assertStringContainsString($meeting->meetingName->name, $body);
                $this->assertStringContainsString($meeting->label(), $body);
                $this->assertStringContainsString($meeting->meeting_date->format('d/m/Y'), $body, 'Tarikh mesyuarat');
                $this->assertStringContainsString(self::REMARKS, $body);
                $this->assertStringContainsString($outcomes[$decision->value], $body);
            }

            if ($decision === Decision::Approved) {
                $this->assertStringNotContainsString('rekod Projek telah', $html);
            }
            if ($decision === Decision::Review) {
                $this->assertStringContainsString('had satu salinan', $html);
            }
        }
    }

    // --- PEMBANTU ---

    private function decide(Application $application, DecisionStage $stage, Decision $decision, ?string $remarks = null, $date = null): ApplicationDecision
    {
        return app(ApplicationDecisionService::class)->decide($application->fresh(), $stage, $decision, $remarks, $date ?? today(), $this->urusSetia);
    }

    /**
     * Penerima tepat: setiap pengguna menerima satu notifikasi bagi rekod ini, dan tiada notifikasi lain.
     *
     * @param  array<int, User>  $users
     */
    private function assertRecipients(ApplicationDecision $record, array $users, string $label = ''): void
    {
        foreach ($users as $user) {
            Notification::assertSentToTimes($user, ApplicationDecisionRecorded::class, 1);
            Notification::assertSentTo($user, ApplicationDecisionRecorded::class,
                fn (ApplicationDecisionRecorded $notification, array $channels) => $notification->decision->is($record) && $channels === ['mail']);
        }

        Notification::assertCount(count($users));
        $this->assertTrue(true, $label);
    }

    private function mailFor(ApplicationDecision $record)
    {
        return (new ApplicationDecisionRecorded($record->fresh()))->toMail($this->registrar);
    }

    /**
     * @return array{0: string, 1: string} HTML dan teks biasa
     */
    private function render(ApplicationDecision $record): array
    {
        $mail = $this->mailFor($record);
        $text = (string) app(Markdown::class)->renderText($mail->markdown, $mail->data());

        return [(string) $mail->render(), html_entity_decode($text, ENT_QUOTES)];
    }

    private function user(array $roles, array $attributes = []): User
    {
        $user = User::factory()->create(['agency_id' => $this->agency->id] + $attributes);
        $user->assignRole($roles);

        return $user->fresh();
    }

    private function addOfficer(Application $application, OfficerRole $role): User
    {
        $user = $this->user(['pengguna_biasa'], ['email' => "pegawai.{$role->value}.{$this->sequence}@ns.gov.my"]);
        $application->officers()->create(ApplicationOfficer::snapshotFrom($user) + ['role' => $role, 'started_at' => today()->subMonth()]);

        return $user;
    }

    private function makeApplication(string $title, array $attributes = [], ?User $primary = null): Application
    {
        $this->sequence++;

        $application = Application::create($attributes + [
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->registrar->id,
            'title' => $title,
            'estimated_cost' => 10000,
            'status' => 'SUBMITTED',
        ]);
        $application->officers()->create(ApplicationOfficer::snapshotFrom($primary ?? $this->officer) + [
            'role' => OfficerRole::Primary,
            'started_at' => today()->subMonth(),
        ]);

        return $application;
    }

    /**
     * Permohonan Lengkap (PP2) dengan Mesyuarat JTICT akan datang yang sah untuk Pra JTICT Diterima.
     */
    private function completeApplication(string $title, array $attributes = [], ?User $primary = null): Application
    {
        $meeting = $this->openJtictMeeting(['series' => 'Bil. ' . ($this->sequence + 1) . '/2026', 'meeting_date' => today()->addDays(10)]);
        $application = $this->makeApplication($title, $attributes + ['jtict_meeting_id' => $meeting->id], $primary);
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);

        return $application->fresh();
    }

    /**
     * Permohonan Dalam Proses JTICT melalui aliran sebenar (Pra JTICT Diterima pada tarikh mesyuarat).
     */
    private function inJtictApplication(string $title, ?Carbon $meetingDate = null): Application
    {
        $meetingDate ??= today()->subDay();
        $meeting = $this->openJtictMeeting(['series' => 'Bil. ' . ($this->sequence + 1) . '/2026', 'meeting_date' => $meetingDate]);

        $this->travelTo($meetingDate->copy()->setTime(9, 0));
        $application = $this->makeApplication($title, ['jtict_meeting_id' => $meeting->id]);
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);
        $this->decide($application, DecisionStage::PreJtict, Decision::Accepted);
        $this->travelBack();

        return $application->fresh();
    }
}
