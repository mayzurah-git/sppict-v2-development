<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Makluman kepada pemohon Self Register bahawa pendaftaran telah diluluskan dan akaun boleh digunakan.
 * Dihantar melalui queue selepas transaksi kelulusan berjaya; tiada kata laluan/token disertakan.
 */
class RegistrationApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $roleLabel)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[SPPICT] Pendaftaran Akaun Diluluskan')
            ->greeting("Salam sejahtera {$notifiable->name},")
            ->line('Dimaklumkan bahawa permohonan pendaftaran akaun SPPICT tuan/puan telah diluluskan. Akaun tuan/puan kini aktif dan boleh digunakan.')
            ->line("ID Log Masuk: **{$notifiable->email}**")
            ->line("Tahap Capaian: **{$this->roleLabel}**")
            ->line('Sila log masuk menggunakan kata laluan yang telah ditetapkan semasa pendaftaran.')
            ->action('Log Masuk ke SPPICT', route('login'))
            ->line('Sebarang pertanyaan lanjut, tuan/puan boleh menghubungi pihak Urus Setia melalui e-mel ' . config('sppict.secretariat_email') . '.')
            ->salutation('Sekian, terima kasih.');
    }
}
