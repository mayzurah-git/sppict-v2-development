<?php

namespace Tests\Feature;

use App\Livewire\ApplicationForm;
use App\Models\ProjectCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

class ApplicationCostSummaryTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    private function step3WithItems(float $ceilingCost, array $unitCosts, float $estimatedCost = 1000)
    {
        config(['sppict.sst_rate' => 8]);

        $items = array_map(fn ($cost) => [
            'technical_specifications' => 'Item ujian',
            'unit_quantity' => 1,
            'unit_cost' => $cost,
        ], $unitCosts);

        return Livewire::test(ApplicationForm::class)
            ->set('form.ceiling_cost', $ceilingCost)
            ->set('form.estimated_cost', $estimatedCost)
            ->set('form.details', [['project_category_id' => $this->masterId(ProjectCategory::class, 'projek_baharu'), 'items' => $items]])
            ->set('currentStep', 3);
    }

    public function test_sst_is_calculated_on_subtotal(): void
    {
        $summary = $this->step3WithItems(100000, [20000.10, 30000.05])->instance()->form->costSummary();

        $this->assertSame(50000.15, $summary['subtotal']);
        $this->assertSame(4000.01, $summary['sst_amount']);
        $this->assertSame(54000.16, $summary['total']);
    }

    public function test_total_below_ceiling_passes(): void
    {
        $this->step3WithItems(100000, [50000])
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 4);
    }

    public function test_total_equal_to_ceiling_passes(): void
    {
        // 50,000 + SST 8% (4,000) = 54,000
        $this->step3WithItems(54000, [50000])
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 4);
    }

    public function test_total_above_ceiling_is_rejected(): void
    {
        // 50,000 + SST 8% (4,000) = 54,000 > 53,999.99
        $this->step3WithItems(53999.99, [50000])
            ->call('nextStep')
            ->assertHasErrors('details_total')
            ->assertSet('currentStep', 3);
    }

    public function test_total_may_exceed_estimated_cost_within_ceiling(): void
    {
        $this->step3WithItems(100000, [60000], estimatedCost: 50000)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 4);
    }

    public function test_estimated_cost_cannot_exceed_ceiling_cost(): void
    {
        Livewire::test(ApplicationForm::class)
            ->set('form.ceiling_cost', 50000)
            ->set('form.estimated_cost', 60000)
            ->set('form.expected_duration_months', 12)
            ->set('form.outcome_code', 'Bajet Mengurus')
            ->set('currentStep', 2)
            ->call('nextStep')
            ->assertHasErrors('form.estimated_cost')
            ->assertSet('currentStep', 2);
    }
}
