<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Akaun & agensi contoh untuk PEMBANGUNAN dan UJIAN sahaja (UM7A).
 *
 * - Enggan dijalankan di luar persekitaran `local` / `testing` (termasuk production).
 * - Akaun hanya dicipta jika belum wujud; kata laluan dan peranan akaun sedia ada tidak diubah.
 * - Akaun baharu menerima kata laluan sementara rawak yang mematuhi polisi, wajib ditukar
 *   (`must_change_password`), dan dipaparkan sekali di konsol operator (tidak dilog/disimpan).
 *
 * Bergantung pada RolesAndPermissionsSeeder (peranan mesti wujud).
 */
class DemoAccountsSeeder extends Seeder
{
    public const ALLOWED_ENVIRONMENTS = ['local', 'testing'];

    public function run(): void
    {
        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            $this->command?->warn('DemoAccountsSeeder dilangkau: hanya dibenarkan dalam persekitaran local/testing.');

            return;
        }

        $agencyBTM = Agency::firstOrCreate(
            ['code' => 'BTM'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Bahagian Teknologi Maklumat (BTM)',
                'category' => 'SUK',
                'is_active' => true,
            ]
        );

        $agencyJKR = Agency::firstOrCreate(
            ['code' => 'JKR_NS'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Jabatan Kerja Raya Negeri Sembilan',
                'category' => 'STATE_DEPT',
                'is_active' => true,
            ]
        );

        $accounts = [
            ['admin.sppict@ns.gov.my', 'Pentadbir Sistem SPPICT', $agencyBTM, 'Pegawai Teknologi Maklumat (F48)', '06-7659999', 'superadmin'],
            ['urussetia.mkk@ns.gov.my', 'Urus Setia', $agencyBTM, 'Penolong Pegawai Teknologi Maklumat (FA32)', '06-7658888', 'pentadbir_urus_setia'],
            ['pemohon.jkr@ns.gov.my', 'Pengurus Projek JKR', $agencyJKR, 'Jurutera Awam (J44)', '06-7657777', 'pengguna_biasa'],
        ];

        foreach ($accounts as [$email, $name, $agency, $position, $phone, $role]) {
            $this->seedAccount($email, $name, $agency, $position, $phone, $role);
        }
    }

    private function seedAccount(string $email, string $name, Agency $agency, string $position, string $phone, string $role): void
    {
        // Akaun sedia ada (termasuk soft-deleted) tidak disentuh langsung.
        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->command?->line("Akaun {$email} sudah wujud; tidak diubah.");

            return;
        }

        $temporaryPassword = TemporaryPassword::generate(16);

        $user = new User();
        $user->fill([
            'agency_id' => $agency->id,
            'name' => $name,
            'email' => $email,
            'position' => $position,
            'phone_number' => $phone,
            'password' => Hash::make($temporaryPassword),
        ]);
        $user->forceFill(['must_change_password' => true])->save();
        $user->assignRole($role);

        $this->command?->warn("Akaun demo {$email} ({$role}) dicipta. Kata laluan sementara (dipaparkan sekali): {$temporaryPassword}");
    }
}
