<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\ProgressCalculator;
use Illuminate\Console\Command;

/**
 * SP4: kemas kini nilai cache kemajuan projek setiap hari. Kemajuan dirancang berubah apabila tarikh
 * sasaran berlalu walaupun tiada kemas kini aktiviti; senarai dan papan pemuka membaca nilai cache.
 * Skrin Maklumat Projek sentiasa mengira semula secara langsung.
 */
class RefreshProjectProgress extends Command
{
    protected $signature = 'sppict:refresh-project-progress';

    protected $description = 'Kira semula nilai cache kemajuan (sebenar, dirancang, varians) bagi projek dalam pemantauan';

    public function handle(ProgressCalculator $calculator): int
    {
        $count = 0;

        Project::query()
            ->whereNotIn('status', [ProjectStatus::Historical->value, ProjectStatus::Closed->value, ProjectStatus::Completed->value])
            ->whereHas('phases')
            ->chunkById(100, function ($projects) use ($calculator, &$count): void {
                foreach ($projects as $project) {
                    $calculator->refresh($project);
                    $count++;
                }
            });

        $this->info("Kemajuan {$count} projek dikemas kini.");

        return self::SUCCESS;
    }
}
