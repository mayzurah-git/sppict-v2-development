<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Makluman kepada pemohon Self Register bahawa pendaftaran tidak diluluskan (tanpa ulasan).
 * Dihantar melalui queue selepas transaksi penolakan berjaya.
 */
class RegistrationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
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
            ->subject('[SPPICT] Pendaftaran Akaun Tidak Diluluskan')
            ->greeting("Salam sejahtera {$notifiable->name},")
            ->line('Dukacita dimaklumkan bahawa permohonan pendaftaran akaun SPPICT tuan/puan tidak diluluskan.')
            ->line('Sebarang pertanyaan lanjut, tuan/puan boleh menghubungi pihak Urus Setia melalui e-mel ' . config('sppict.secretariat_email') . '.')
            ->salutation('Sekian, terima kasih.');
    }
}
