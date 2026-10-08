<?php

namespace Tests\Concerns;

use Database\Seeders\ApplicationMasterDataSeeder;
use Illuminate\Database\Eloquent\Model;

/**
 * Master data Permohonan Projek untuk ujian borang (Kategori Permohonan, Kategori Projek,
 * Jenis Perolehan, Kaedah Perolehan). Dipanggil secara automatik oleh setUpTraits().
 */
trait UsesApplicationMasterData
{
    protected function setUpUsesApplicationMasterData(): void
    {
        $this->seed(ApplicationMasterDataSeeder::class);
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function masterId(string $model, string $code): int
    {
        return $model::withTrashed()->where('code', $code)->value('id');
    }
}
