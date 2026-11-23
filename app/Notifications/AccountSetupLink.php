<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * UM13: pautan "Tetapkan Kata Laluan" bagi akaun yang didaftar atau diset semula oleh pentadbir.
 *
 * Sengaja TIDAK `ShouldQueue`: token teks biasa tidak boleh tersimpan dalam jadual `jobs` /
 * `failed_jobs`. Dihantar serta-merta (`notifyNow`) supaya kegagalan e-mel dapat dipaparkan terus.
 */
class AccountSetupLink extends Notification
{
    public function __construct(public string $token, public bool $isReset = false)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hours = (int) ceil(config('auth.passwords.account_setup.expire') / 60);
        $url = route('password.setup', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        $mail = (new MailMessage)
            ->subject($this->isReset
                ? '[SPPICT] Set Semula Kata Laluan Akaun'
                : '[SPPICT] Pendaftaran Akaun Pengguna')
            ->greeting("Salam sejahtera {$notifiable->name},");

        $mail = $this->isReset
            ? $mail->line('Kata laluan akaun SPPICT anda telah diset semula oleh pentadbir sistem.')
            : $mail->line('Akaun SPPICT anda telah didaftarkan oleh Urus Setia.')
                ->line("ID Log Masuk: **{$notifiable->email}**");

        return $mail
            ->line('Sila klik butang di bawah untuk menetapkan kata laluan anda.')
            ->action('Tetapkan Kata Laluan', $url)
            ->line("Pautan ini sah selama {$hours} jam dan hanya boleh digunakan sekali.")
            ->line('Jika pautan telah tamat tempoh, sila hubungi Urus Setia untuk pautan baharu.');
    }
}
