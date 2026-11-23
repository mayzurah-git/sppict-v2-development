<?php

namespace App\Console\Commands;

use App\Enums\InactiveReason;
use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * UM11A: nyahaktif automatik akaun yang tidak log masuk melebihi tempoh (lalai 6 bulan).
 *
 * Dasar (keputusan pengguna 2026-10-21):
 * - Aktiviti = log masuk terakhir (`last_login_at`).
 * - Tarikh rujukan = terkemudian antara log masuk terakhir, akaun dicipta, akaun diluluskan,
 *   status terakhir ditukar (cth. diaktifkan semula) dan tarikh ciri dilancar (`sppict.dormancy.baseline`).
 * - Hanya akaun `active`; Superadmin dikecualikan.
 * - Dijadualkan setiap minggu (Isnin 1:00 pagi) — routes/console.php.
 *
 * Diaudit sebagai perubahan oleh sistem (audit `updated` OwenIt, `user_id` NULL; `status_changed_by` NULL).
 */
class DeactivateDormantUsers extends Command
{
    protected $signature = 'sppict:deactivate-dormant-users {--dry-run : Senaraikan akaun tanpa membuat perubahan}';

    protected $description = 'Nyahaktif akaun yang tidak log masuk melebihi tempoh ditetapkan (Tidak Aktif Melebihi 6 Bulan)';

    public function handle(): int
    {
        $months = max(1, (int) config('sppict.dormancy.months', 6));
        $cutoff = CarbonImmutable::now()->subMonths($months);
        $baseline = CarbonImmutable::parse((string) config('sppict.dormancy.baseline'));
        $dryRun = (bool) $this->option('dry-run');

        // Penyahaktifan oleh sistem mesti diaudit walaupun dijalankan dari konsol.
        $previousConsoleAuditing = config('audit.console');
        config(['audit.console' => true]);

        $candidates = $this->candidates($cutoff);
        $deactivated = [];

        try {
            foreach ($candidates as $user) {
                if ($this->referenceDate($user, $baseline)->greaterThanOrEqualTo($cutoff)) {
                    continue;
                }

                if ($dryRun) {
                    $deactivated[] = $user;

                    continue;
                }

                $changed = DB::transaction(function () use ($user, $cutoff, $baseline): bool {
                    // Semak semula dengan kunci rekod (status/log masuk mungkin berubah serentak).
                    $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();

                    if (! $fresh?->isActive() || $fresh->hasRole('superadmin')
                        || $this->referenceDate($fresh, $baseline)->greaterThanOrEqualTo($cutoff)) {
                        return false;
                    }

                    $fresh->forceFill([
                        'status' => UserStatus::Inactive,
                        'inactive_reason' => InactiveReason::InactiveOver6Months,
                        'status_changed_at' => now(),
                        'status_changed_by' => null,
                    ])->save();

                    return true;
                });

                if ($changed) {
                    $deactivated[] = $user;
                }
            }
        } finally {
            config(['audit.console' => $previousConsoleAuditing]);
        }

        foreach ($deactivated as $user) {
            $this->line(($dryRun ? '[dry-run] ' : '') . "Tidak Aktif: {$user->email} (log masuk terakhir: " . ($user->last_login_at?->format('d/m/Y') ?? 'tiada rekod') . ')');
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . count($deactivated) . ' akaun ' . ($dryRun ? 'akan dinyahaktifkan.' : 'dinyahaktifkan.'));

        return self::SUCCESS;
    }

    /**
     * Pra-tapis di pangkalan data: akaun aktif bukan Superadmin yang dicipta sebelum tarikh potong
     * dan tiada log masuk selepas tarikh potong. Penentuan akhir dibuat oleh referenceDate().
     */
    protected function candidates(CarbonImmutable $cutoff)
    {
        return User::query()
            ->active()
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'superadmin'))
            ->where('created_at', '<', $cutoff)
            ->where(fn ($query) => $query->whereNull('last_login_at')->orWhere('last_login_at', '<', $cutoff))
            ->orderBy('id')
            ->get();
    }

    protected function referenceDate(User $user, CarbonImmutable $baseline): CarbonImmutable
    {
        return collect([$user->last_login_at, $user->created_at, $user->approved_at, $user->status_changed_at, $baseline])
            ->filter()
            ->map(fn ($date) => CarbonImmutable::parse($date))
            ->max();
    }
}
