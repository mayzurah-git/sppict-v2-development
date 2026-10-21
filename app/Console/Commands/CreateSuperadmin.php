<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\Agency;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Provisioning terkawal akaun Superadmin awal (UM7A), menggantikan akaun Superadmin dalam seeder.
 *
 * - Tiada kata laluan tetap: kata laluan sementara rawak (mematuhi polisi) dipaparkan sekali di
 *   konsol operator dan wajib ditukar semasa log masuk pertama (`must_change_password`, UM6).
 * - Enggan mengubah akaun sedia ada (termasuk soft-deleted) — tiada reset kata laluan/peranan.
 * - Agensi mesti wujud dan aktif (tiada fallback agensi tetap).
 */
class CreateSuperadmin extends Command
{
    protected $signature = 'sppict:create-superadmin
                            {email : E-mel rasmi akaun Superadmin}
                            {--name= : Nama penuh}
                            {--agency= : Kod Jabatan/Agensi (aktif)}';

    protected $description = 'Cipta akaun Superadmin awal dengan kata laluan sementara (wajib ditukar)';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = Str::squish((string) ($this->option('name') ?? ''));
        $agencyCode = trim((string) ($this->option('agency') ?? ''));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'agency' => $agencyCode],
            ['email' => ['required', 'email', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'agency' => ['required', 'string']],
            ['required' => 'Medan :attribute wajib diisi.', 'email' => 'Format e-mel tidak sah.'],
            ['email' => 'e-mel', 'name' => '--name', 'agency' => '--agency'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if (User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->error("Akaun {$email} sudah wujud. Tiada perubahan dibuat (gunakan modul Pengurusan Pengguna untuk reset kata laluan).");

            return self::FAILURE;
        }

        $agency = Agency::active()->where('code', $agencyCode)->first();
        if (! $agency) {
            $this->error("Jabatan/Agensi dengan kod [{$agencyCode}] tidak wujud atau tidak aktif.");

            return self::FAILURE;
        }

        if (! Role::where('name', 'superadmin')->exists()) {
            $this->error('Peranan [superadmin] belum wujud. Jalankan RolesAndPermissionsSeeder terlebih dahulu.');

            return self::FAILURE;
        }

        $temporaryPassword = TemporaryPassword::generate(16);

        $user = DB::transaction(function () use ($email, $name, $agency, $temporaryPassword): User {
            $user = new User();
            $user->fill([
                'name' => $name,
                'email' => $email,
                'agency_id' => $agency->id,
                'password' => Hash::make($temporaryPassword),
            ]);
            $user->forceFill([
                'status' => UserStatus::Active,
                'must_change_password' => true,
            ])->save();
            $user->assignRole('superadmin');

            return $user;
        });

        $this->info("Akaun Superadmin {$user->email} berjaya dicipta ({$agency->code}).");
        $this->warn("Kata laluan sementara (dipaparkan SEKALI sahaja, tidak disimpan): {$temporaryPassword}");
        $this->line('Pengguna wajib menukar kata laluan semasa log masuk pertama.');

        return self::SUCCESS;
    }
}
