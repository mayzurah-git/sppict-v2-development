<?php

namespace App\Notifications;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\DecisionStage;
use App\Models\ApplicationDecision;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PP4.3D: makluman keputusan rasmi Pra JTICT / Mesyuarat JTICT kepada Pendaftar Permohonan dan
 * Pegawai Utama aktif. Dihantar melalui queue selepas transaksi keputusan berjaya (afterCommit).
 *
 * Semua kandungan dibaca daripada rekod keputusan rasmi (application_decisions), bukan lajur legacy.
 * Pautan hanya membawa pengguna ke sistem; log masuk dan kebenaran `view` sedia ada masih terpakai.
 */
class ApplicationDecisionRecorded extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Satu percubaan sahaja: e-mel yang mungkin telah dihantar tidak dihantar semula secara automatik
     * (elak e-mel berganda). Kegagalan dilog melalui failed().
     */
    public int $tries = 1;

    public function __construct(public ApplicationDecision $decision)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $decision = $this->decision->loadMissing([
            'application.agency',
            'meeting.meetingName',
            'meeting.meetingNumber',
            'meeting.meetingYear',
        ]);
        $application = $decision->application;
        $meeting = $decision->meeting;

        return (new MailMessage)
            ->subject(self::subjectFor($decision))
            ->markdown('mail.application-decision', [
                'greeting' => $notifiable instanceof User ? "Salam sejahtera {$notifiable->name}," : 'Salam sejahtera,',
                'heading' => self::headingFor($decision),
                'stageLabel' => self::stageLabel($decision->stage),
                'decisionLabel' => $decision->decision->label(),
                'referenceNumber' => $application->reference_number,
                'title' => $application->title,
                'agencyName' => $application->agency?->name ?? '-',
                'decisionDate' => $decision->decision_date?->format('d/m/Y') ?? '-',
                'meetingName' => $meeting ? trim(($meeting->meetingName?->name ?? '') . ' (' . $meeting->label() . ')') : null,
                'meetingDate' => $meeting?->meeting_date?->format('d/m/Y'),
                'remarks' => $decision->remarks,
                'outcome' => self::outcomeFor($decision->decision),
                'url' => route('application.view', $application->uuid),
            ]);
    }

    public static function stageLabel(DecisionStage $stage): string
    {
        return $stage === DecisionStage::PreJtict ? 'Pra JTICT' : 'Mesyuarat JTICT';
    }

    public static function subjectFor(ApplicationDecision $decision): string
    {
        return '[SPPICT] Keputusan ' . self::stageLabel($decision->stage)
            . ' – ' . $decision->decision->label()
            . ' – ' . $decision->application->reference_number;
    }

    public static function headingFor(ApplicationDecision $decision): string
    {
        return 'Makluman Keputusan ' . self::stageLabel($decision->stage);
    }

    /**
     * Maksud keputusan dan tindakan seterusnya bagi pemohon.
     */
    public static function outcomeFor(Decision $decision): string
    {
        return match ($decision) {
            Decision::Accepted => 'Permohonan ini telah diterima dalam Pra JTICT untuk dibawa ke Mesyuarat JTICT.',
            Decision::AmendmentRequired => 'Permohonan ini memerlukan pindaan. Sila log masuk ke SPPICT, semak ulasan Urus Setia, buat pindaan yang diperlukan dan hantar semula permohonan mengikut proses sedia ada.',
            Decision::Rejected => 'Permohonan ini telah ditolak dalam Pra JTICT dan tidak diteruskan.',
            Decision::Approved => 'Permohonan ini telah diluluskan oleh Mesyuarat JTICT. Permohonan akan diteruskan kepada proses Modul Status Kemajuan Projek apabila modul tersebut tersedia.',
            Decision::NotApproved => 'Permohonan ini tidak diluluskan oleh Mesyuarat JTICT.',
            Decision::Review => 'Permohonan ini memerlukan penelitian semula. Fungsi Salin Permohonan boleh digunakan untuk menyediakan permohonan baharu, tertakluk kepada kelayakan pengguna dan had satu salinan bagi setiap permohonan.',
        };
    }

    /**
     * Kegagalan penghantaran (queue) tidak menjejaskan rekod keputusan yang telah disimpan.
     */
    public function failed(Throwable $e): void
    {
        $reference = $this->decision->application?->reference_number ?? '-';

        Log::error("Notifikasi keputusan {$this->decision->decision->label()} gagal dihantar ({$reference}): " . self::safeError($e));
    }

    /**
     * Mesej ralat tanpa butiran kelayakan (cth. nama pengguna SMTP dalam ralat pengesahan).
     */
    public static function safeError(Throwable $e): string
    {
        $message = preg_replace('/\b(username|user|password|pass)\b(\s*[:=]?\s*)"[^"]*"/i', '$1$2"[dirahsiakan]"', $e->getMessage());

        return class_basename($e) . ': ' . mb_strimwidth((string) $message, 0, 500, '…');
    }
}
