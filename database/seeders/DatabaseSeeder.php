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
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(MeetingMasterDataSeeder::class);
        $this->call(ApplicationMasterDataSeeder::class);

        // Data contoh pembangunan sahaja (Mesyuarat JTICT yang dibuka)
        if (app()->isLocal()) {
            $this->call(MeetingSeeder::class);
        }
    }
}
