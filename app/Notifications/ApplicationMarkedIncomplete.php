<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PP2: makluman Permohonan Tidak Lengkap kepada Pemohon dan Pegawai Utama (melalui queue,
 * selepas transaksi semakan berjaya). Pautan memerlukan log masuk & kebenaran `view`.
 */
class ApplicationMarkedIncomplete extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application, public ApplicationReview $review)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $greeting = $notifiable instanceof User ? "Salam sejahtera {$notifiable->name}," : 'Salam sejahtera,';

        return (new MailMessage)
            ->subject("[SPPICT] Permohonan Tidak Lengkap: {$this->application->reference_number}")
            ->greeting($greeting)
            ->line('Dimaklumkan bahawa permohonan projek berikut dalam Sistem Pemantauan Projek ICT (SPPICT) telah disemak oleh Urus Setia dan ditetapkan sebagai **Tidak Lengkap**:')
            ->line("**{$this->application->reference_number}** – {$this->application->title}")
            ->line('Ulasan Urus Setia:')
            ->line($this->review->remarks)
            ->line('Sila log masuk ke SPPICT, semak ulasan tersebut, buat pembetulan yang diperlukan dan hantar semula permohonan untuk semakan Urus Setia.')
            ->action('Lihat Permohonan', route('application.view', $this->application->uuid));
    }

    /**
     * Kegagalan penghantaran (queue) tidak menjejaskan keputusan semakan yang telah disimpan.
     */
    public function failed(Throwable $e): void
    {
        Log::error("Notifikasi Permohonan Tidak Lengkap gagal dihantar ({$this->application->reference_number}): " . $e->getMessage());
    }
}
