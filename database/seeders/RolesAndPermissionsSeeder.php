<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Agency;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Cipta Permissions Mengikut Modul
        $permissions = [
            // Dashboard
            'view-agency-dashboard',
            'view-admin-dashboard',
            'view-executive-dashboard',

            // Permohonan Projek
            'create-application',
            'edit-own-application',
            'view-own-application',
            'view-all-applications',
            'review-application-urussetia',
            'delete-application',

            // Mesyuarat (Pra-JTICT / JTICTNS / JPICTNS)
            'manage-meetings',
            'view-meetings',
            'upload-minutes',
            'download-minutes',

            // Status & Keputusan Mesyuarat
            'update-meeting-decisions',
            'recommend-to-jpict',

            // Pemantauan & Kemajuan Projek
            'update-project-progress',
            'verify-project-progress',
            'manage-mid-term-review',

            // Laporan & Sijil
            'generate-reports',
            'print-approval-certificate',

            // Pentadbiran Sistem & Audit
            'manage-users',
            'manage-agencies',
            'view-audit-trails',
            'manage-system-settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Cipta Roles Utama & Tetapkan Permissions
        
        // Role 1: Pengguna Biasa (Pemohon Agensi)
        $rolePenggunaBiasa = Role::firstOrCreate(['name' => 'pengguna_biasa']);
        $rolePenggunaBiasa->syncPermissions([
            'view-agency-dashboard',
            'create-application',
            'edit-own-application',
            'view-own-application',
            'download-minutes',
            'update-project-progress',
            'manage-mid-term-review',
            'print-approval-certificate',
        ]);

        // Role 2: Pentadbir Urus Setia (MKK / BTM)
        $roleUrusSetia = Role::firstOrCreate(['name' => 'pentadbir_urus_setia']);
        $roleUrusSetia->syncPermissions([
            'view-admin-dashboard',
            'view-all-applications',
            'review-application-urussetia',
            'manage-meetings',
            'view-meetings',
            'upload-minutes',
            'download-minutes',
            'update-meeting-decisions',
            'recommend-to-jpict',
            'verify-project-progress',
            'manage-mid-term-review',
            'generate-reports',
            'print-approval-certificate',
            'manage-users',
        ]);

        // Role 3: Pengurusan (Pihak Pengurusan / Ahli Jawatankuasa)
        $rolePengurusan = Role::firstOrCreate(['name' => 'pengurusan']);
        $rolePengurusan->syncPermissions([
            'view-executive-dashboard',
            'view-all-applications',
            'view-meetings',
            'download-minutes',
            'generate-reports',
        ]);

        // Role 4: Superadmin (Pembangun Sistem / Lead Administrator)
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $roleSuperAdmin->syncPermissions(Permission::all());

        // 3. Cipta Agensi Contoh
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

        // 4. Cipta Akaun Pentadbir Awal (Superadmin & Urus Setia)
        $superadminUser = User::firstOrCreate(
            ['email' => 'admin.sppict@ns.gov.my'],
            [
                'uuid' => (string) Str::uuid(),
                'agency_id' => $agencyBTM->id,
                'name' => 'Pentadbir Sistem SPPICT',
                'position' => 'Pegawai Teknologi Maklumat (F48)',
                'phone_number' => '06-7659999',
                'password' => Hash::make('Password123!'),
            ]
        );
        $superadminUser->assignRole($roleSuperAdmin);

        $urusSetiaUser = User::firstOrCreate(
            ['email' => 'urussetia.mkk@ns.gov.my'],
            [
                'uuid' => (string) Str::uuid(),
                'agency_id' => $agencyBTM->id,
                'name' => 'Urus Setia MKK BTM',
                'position' => 'Penolong Pegawai Teknologi Maklumat (FA32)',
                'phone_number' => '06-7658888',
                'password' => Hash::make('Password123!'),
            ]
        );
        $urusSetiaUser->assignRole($roleUrusSetia);

        $pemohonUser = User::firstOrCreate(
            ['email' => 'pemohon.jkr@ns.gov.my'],
            [
                'uuid' => (string) Str::uuid(),
                'agency_id' => $agencyJKR->id,
                'name' => 'Pengurus Projek JKR',
                'position' => 'Jurutera Awam (J44)',
                'phone_number' => '06-7657777',
                'password' => Hash::make('Password123!'),
            ]
        );
        $pemohonUser->assignRole($rolePenggunaBiasa);
    }
}
