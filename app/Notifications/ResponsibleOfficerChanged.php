<?php

namespace App\Notifications;

use App\Enums\OfficerRole;
use App\Models\Application;
use App\Models\ApplicationOfficer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemakluman pertukaran Pegawai Bertanggungjawab (tiada kelulusan diperlukan).
 */
class ResponsibleOfficerChanged extends Notification
{
    use Queueable;

    public function __construct(
        public Application $application,
        public OfficerRole $role,
        public ?ApplicationOfficer $previous,
        public ?ApplicationOfficer $assigned,
        public User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $effectiveDate = ($this->assigned?->started_at ?? $this->previous?->ended_at)?->format('d/m/Y');

        $mail = (new MailMessage)
            ->subject("[SPPICT] Pertukaran {$this->role->label()}: {$this->application->reference_number}")
            ->greeting("Salam sejahtera {$notifiable->name},")
            ->line("Dimaklumkan bahawa {$this->role->label()} bagi permohonan/projek berikut telah dikemas kini:")
            ->line("**{$this->application->reference_number}** – {$this->application->title}")
            ->line('Pegawai terdahulu: ' . ($this->previous?->name ?? '-'))
            ->line('Pegawai baharu: ' . ($this->assigned?->name ?? 'Tiada (tugasan ditamatkan)'))
            ->line("Tarikh berkuat kuasa: {$effectiveDate}");

        if ($this->previous?->end_reason) {
            $mail->line("Sebab pertukaran: {$this->previous->end_reason->label()}");
        }

        return $mail
            ->line("Dikemas kini oleh: {$this->actor->name}")
            ->action('Lihat Permohonan', route('application.view', $this->application->uuid));
    }
}
