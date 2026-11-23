<?php

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 15) as $value) {
            $code = (string) $value;

            Grade::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'description' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
