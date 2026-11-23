<?php

namespace Tests\Unit;

use App\Enums\ScheduleStatus;
use App\Services\ProgressCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * SP4: pengiraan kemajuan tulen (audit SP0 L.2, L.3; keputusan I.5). Tiada pangkalan data.
 */
class ProgressCalculatorTest extends TestCase
{
    private ProgressCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ProgressCalculator();
    }

    public function test_l2_example_template_a_on_15_march(): void
    {
        // Tender (5), Perolehan (10), SST (5), Kickoff (10) bersasar sebelum / pada 15 Mac; baki 70 selepasnya.
        $phase = [
            $this->activity(5, '2027-01-15', '2027-01-14'),
            $this->activity(10, '2027-02-15', '2027-02-20'),
            $this->activity(5, '2027-03-01', '2027-03-01'),
            $this->activity(10, '2027-03-15'),           // Kickoff: lewat
            $this->activity(40, '2027-06-15'),
            $this->activity(30, '2027-09-15'),
        ];

        $result = $this->calculator->compute([1 => $phase], Carbon::parse('2027-03-15'));

        $this->assertSame(20.0, $result->actual);
        $this->assertSame(30.0, $result->planned);
        $this->assertSame(-10.0, $result->variance());
        $this->assertSame(ScheduleStatus::Late, $result->status());
        $this->assertSame('AMBER', $result->indicator());
    }

    public function test_l3_example_two_phases_averaged_with_proportional_phase(): void
    {
        // Fasa 1: semua aktiviti Templat D (100), siap 5+10+5+5+5+30 = 60.
        $phase1 = [];
        foreach ([5, 10, 5, 5, 5, 30] as $weight) {
            $phase1[] = $this->activity($weight, '2027-01-15', '2027-01-15');
        }
        foreach ([20, 10, 5, 5] as $weight) {
            $phase1[] = $this->activity($weight, '2027-12-15');
        }

        // Fasa 2: Pembangunan (30, siap), UAT/PAT (20), FAT (10): jumlah 60, berkadar => 50%.
        $phase2 = [
            $this->activity(30, '2027-06-15', '2027-06-01'),
            $this->activity(20, '2027-12-15'),
            $this->activity(10, '2027-12-20'),
        ];

        $result = $this->calculator->compute([1 => $phase1, 2 => $phase2], Carbon::parse('2027-07-01'));

        $this->assertSame(60.0, $result->phases[1]->actual);
        $this->assertSame(50.0, $result->phases[2]->actual);
        $this->assertSame(55.0, $result->actual);

        $this->assertSame(60.0, $result->phases[1]->planned);
        $this->assertSame(50.0, $result->phases[2]->planned);
        $this->assertSame(55.0, $result->planned);
        $this->assertSame(0.0, $result->variance());
        $this->assertSame(ScheduleStatus::OnTrack, $result->status());
        $this->assertSame('GREEN', $result->indicator());
    }

    public function test_single_phase_project_equals_its_phase(): void
    {
        $phase = [$this->activity(40, '2027-02-01', '2027-01-30'), $this->activity(60, '2027-05-01')];

        $result = $this->calculator->compute([1 => $phase], Carbon::parse('2027-03-01'));

        $this->assertSame($result->phases[1]->actual, $result->actual);
        $this->assertSame($result->phases[1]->planned, $result->planned);
        $this->assertSame(40.0, $result->actual);
        $this->assertSame(40.0, $result->planned);
    }

    public function test_target_date_boundaries(): void
    {
        $phase = [$this->activity(50, '2027-03-15'), $this->activity(50, '2027-12-31')];

        $this->assertSame(0.0, $this->calculator->compute([1 => $phase], Carbon::parse('2027-03-14'))->planned);
        $this->assertSame(50.0, $this->calculator->compute([1 => $phase], Carbon::parse('2027-03-15'))->planned);  // pada tarikh sasaran
        $this->assertSame(50.0, $this->calculator->compute([1 => $phase], Carbon::parse('2027-03-16 18:30'))->planned);
    }

    public function test_completion_after_evaluation_date_is_not_counted_and_time_does_not_add_actual_progress(): void
    {
        $phase = [$this->activity(30, '2027-02-15', '2027-03-10'), $this->activity(70, '2027-04-15')];

        $this->assertSame(0.0, $this->calculator->compute([1 => $phase], Carbon::parse('2027-03-09'))->actual);
        $this->assertSame(30.0, $this->calculator->compute([1 => $phase], Carbon::parse('2027-03-10'))->actual);

        // Masa berlalu tanpa kemas kini: sebenar kekal, dirancang meningkat, varians menjadi negatif.
        $later = $this->calculator->compute([1 => $phase], Carbon::parse('2027-12-31'));
        $this->assertSame(30.0, $later->actual);
        $this->assertSame(100.0, $later->planned);
        $this->assertSame(-70.0, $later->variance());
        $this->assertSame('RED', $later->indicator());
    }

    public function test_missing_target_dates_give_no_planned_value(): void
    {
        // Rekod sejarah v1: tarikh sebenar siap sahaja.
        $phase = [$this->activity(40, null, '2020-03-01'), $this->activity(60, null)];

        $result = $this->calculator->compute([1 => $phase], Carbon::parse('2027-01-01'));

        $this->assertSame(40.0, $result->actual);
        $this->assertNull($result->planned);
        $this->assertNull($result->variance());
        $this->assertNull($result->status());
        $this->assertNull($result->indicator());

        // Satu fasa tanpa jadual lengkap: projek tiada nilai dirancang.
        $complete = [$this->activity(100, '2026-12-01', '2026-12-01')];
        $this->assertNull($this->calculator->compute([1 => $complete, 2 => $phase], Carbon::parse('2027-01-01'))->planned);
    }

    public function test_rounding_and_average_from_unrounded_phase_values(): void
    {
        // Fasa 1: 20 / 60 = 33.333…; Fasa 2: 40 / 60 = 66.666…; purata tepat 50.00.
        $phase1 = [$this->activity(20, '2027-01-01', '2027-01-01'), $this->activity(40, '2027-12-01')];
        $phase2 = [$this->activity(40, '2027-01-01', '2027-01-01'), $this->activity(20, '2027-12-01')];

        $result = $this->calculator->compute([1 => $phase1, 2 => $phase2], Carbon::parse('2027-02-01'));

        $this->assertSame(33.33, $result->phases[1]->actual);
        $this->assertSame(66.67, $result->phases[2]->actual);
        $this->assertSame(50.0, $result->actual);
    }

    public function test_schedule_status_thresholds(): void
    {
        $this->assertSame([ScheduleStatus::Ahead, 'GREEN'], [ScheduleStatus::fromVariance(5.0), ScheduleStatus::indicatorFor(5.0)]);
        $this->assertSame([ScheduleStatus::OnTrack, 'GREEN'], [ScheduleStatus::fromVariance(0.0), ScheduleStatus::indicatorFor(0.0)]);
        $this->assertSame([ScheduleStatus::Late, 'AMBER'], [ScheduleStatus::fromVariance(-0.01), ScheduleStatus::indicatorFor(-0.01)]);
        $this->assertSame('AMBER', ScheduleStatus::indicatorFor(-19.99));
        $this->assertSame('RED', ScheduleStatus::indicatorFor(-20.0));
        $this->assertSame('RED', ScheduleStatus::indicatorFor(-55.5));
    }

    public function test_empty_structure(): void
    {
        $result = $this->calculator->compute([], Carbon::parse('2027-01-01'));

        $this->assertSame(0.0, $result->actual);
        $this->assertNull($result->planned);
    }

    private function activity(float $weight, ?string $target, ?string $completedOn = null): array
    {
        return [
            'weight' => $weight,
            'target_date' => $target ? Carbon::parse($target) : null,
            'is_completed' => $completedOn !== null,
            'actual_completion_date' => $completedOn ? Carbon::parse($completedOn) : null,
        ];
    }
}
