<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Makluman Mesyuarat kepada ahli (dihantar melalui queue): pendaftaran baharu atau kemaskini.
 */
class MeetingAnnouncement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Meeting $meeting, public bool $isUpdate = false)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting->loadMissing(['meetingName', 'meetingNumber', 'meetingYear']);
        $label = $meeting->label();

        $mail = (new MailMessage)
            ->subject($this->isUpdate
                ? "[SPPICT] Makluman Kemaskini Mesyuarat {$label}"
                : "[SPPICT] Makluman Mesyuarat {$label}")
            ->greeting("Salam sejahtera {$notifiable->name},")
            ->line($this->isUpdate
                ? 'Dimaklumkan bahawa maklumat mesyuarat berikut telah dikemas kini:'
                : 'Dimaklumkan bahawa mesyuarat berikut telah dijadualkan:')
            ->line("**{$meeting->meetingName?->name}** ({$label})")
            ->line('Tarikh: ' . ($meeting->meeting_date?->format('d/m/Y') ?? '-'))
            ->line('Masa: ' . $meeting->meetingTimeLabel())
            ->line('Tempat: ' . ($meeting->venue ?? '-'));

        if ($meeting->meetingName?->accepts_applications && $meeting->submission_closes_at) {
            $mail->line('Tarikh Tutup Permohonan Projek: ' . $meeting->submission_closes_at->format('d/m/Y'));
        }

        if ($meeting->progress_update_deadline) {
            $mail->line('Tarikh Status Kemaskini Kemajuan Projek: ' . $meeting->progress_update_deadline->format('d/m/Y'));
        }

        $agenda = $this->agendaLines($meeting->agenda);

        if ($agenda !== []) {
            $mail->line('**Agenda Mesyuarat:**');

            foreach ($agenda as $line) {
                $mail->line($line);
            }
        }

        if ($meeting->hasMinutes()) {
            $mail->action('Lihat Minit Mesyuarat Terdahulu', route('meetings.minutes', $meeting->uuid));
        }

        return $mail->line('Sila log masuk ke SPPICT untuk maklumat lanjut.');
    }

    /**
     * Agenda disimpan sebagai HTML (Trix); ditukar kepada baris teks biasa untuk emel.
     *
     * @return array<int, string>
     */
    private function agendaLines(?string $html): array
    {
        if (blank($html)) {
            return [];
        }

        $text = preg_replace('/<(br|\/div|\/li|\/h1|\/blockquote|\/pre|\/p)\b[^>]*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return collect(preg_split('/\R/u', $text))
            ->map(fn (string $line) => trim(str_replace("\u{A0}", ' ', $line)))
            ->filter()
            ->values()
            ->all();
    }
}
