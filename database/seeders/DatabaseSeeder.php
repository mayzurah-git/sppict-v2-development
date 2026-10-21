<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Data rujukan sistem (semua persekitaran).
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(MeetingMasterDataSeeder::class);
        $this->call(ApplicationMasterDataSeeder::class);

        // Data contoh pembangunan sahaja. UM7A: akaun demo tidak dicipta dalam production;
        // Superadmin awal diwujudkan melalui `php artisan sppict:create-superadmin`.
        if (app()->isLocal()) {
            $this->call(DemoAccountsSeeder::class);
            $this->call(MeetingSeeder::class);
        }
    }
}
