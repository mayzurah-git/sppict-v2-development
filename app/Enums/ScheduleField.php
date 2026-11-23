<?php

namespace App\Enums;

/**
 * SP2: medan jadual yang boleh dipinda (keputusan I.3), direkodkan dalam project_schedule_changes.
 */
enum ScheduleField: string
{
    case PlannedStartDate = 'PLANNED_START_DATE';
    case PlannedEndDate = 'PLANNED_END_DATE';
    case TargetDate = 'TARGET_DATE';

    public function label(): string
    {
        return match ($this) {
            self::PlannedStartDate => 'Tarikh Mula Projek',
            self::PlannedEndDate => 'Tarikh Tamat Projek',
            self::TargetDate => 'Tarikh Sasaran Aktiviti',
        };
    }
}
