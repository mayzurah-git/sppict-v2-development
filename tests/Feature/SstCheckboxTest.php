<?php

namespace Tests\Feature;

use App\Livewire\ApplicationForm;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * PP4.5: SST hanya dikira apabila pemohon menanda kotak SST pada Ringkasan Kos Projek.
 */
class SstCheckboxTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sppict.sst_rate' => 8]);

        $agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $this->user = User::factory()->create(['agency_id' => $agency->id]);
        $this->user->assignRole(Role::findOrCreate('pengguna_biasa'));
    }

    public function test_new_application_has_sst_unchecked_and_no_sst(): void
    {
        $summary = $this->form(50000)
            ->assertSet('form.sst_applicable', false)
            ->assertSee('Tidak dikenakan')
            ->instance()->form->costSummary();

        $this->assertFalse($summary['sst_applicable']);
        $this->assertSame(0.0, (float) $summary['sst_amount']);
        $this->assertSame(50000.0, (float) $summary['total']);
    }

    public function test_ticking_checkbox_calculates_sst_on_subtotal(): void
    {
        $summary = $this->form(50000)
            ->set('form.sst_applicable', true)
            ->assertSee('RM 4,000.00')
            ->instance()->form->costSummary();

        $this->assertSame(4000.0, (float) $summary['sst_amount']);
        $this->assertSame(54000.0, (float) $summary['total']);
    }

    public function test_ceiling_check_follows_checkbox(): void
    {
        // 50,000 tanpa SST <= 53,999.99; dengan SST 54,000 > 53,999.99
        $this->form(50000, ceiling: 53999.99)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 4);

        $this->form(50000, ceiling: 53999.99)
            ->set('form.sst_applicable', true)
            ->call('nextStep')
            ->assertHasErrors('details_total')
            ->assertSet('currentStep', 3);
    }

    public function test_unchecked_draft_is_saved_without_sst(): void
    {
        $this->form(50000)->call('saveDraft');

        $application = Application::latest('id')->firstOrFail();

        $this->assertFalse($application->sst_applicable);
        $this->assertNull($application->sst_rate);
        $this->assertSame('0.00', $application->sst_amount);
        $this->assertSame('50000.00', $application->total_cost);
    }

    public function test_checked_draft_is_saved_with_sst_and_restored_when_reopened(): void
    {
        $this->form(50000)->set('form.sst_applicable', true)->call('saveDraft');

        $application = Application::latest('id')->firstOrFail();

        $this->assertTrue($application->sst_applicable);
        $this->assertSame('8.00', $application->sst_rate);
        $this->assertSame('4000.00', $application->sst_amount);
        $this->assertSame('54000.00', $application->total_cost);

        Livewire::actingAs($this->user)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->assertSet('form.sst_applicable', true)
            ->set('form.sst_applicable', false)
            ->call('saveDraft');

        $application->refresh();
        $this->assertFalse($application->sst_applicable);
        $this->assertSame('50000.00', $application->total_cost);
    }

    public function test_manual_sst_item_warning_only_when_checkbox_ticked(): void
    {
        $component = $this->form(1000, spec: 'SST 8%')->set('currentStep', 3);

        $this->assertFalse($component->instance()->form->hasManualSstItem());

        $component->set('form.sst_applicable', true);
        $this->assertTrue($component->instance()->form->hasManualSstItem());
    }

    private function form(float $unitCost, float $ceiling = 1000000, string $spec = 'Pelayan aplikasi')
    {
        return Livewire::actingAs($this->user)->test(ApplicationForm::class)
            ->set('form.title', 'Projek Ujian SST')
            ->set('form.ceiling_cost', $ceiling)
            ->set('form.estimated_cost', 1000)
            ->set('form.details', [[
                'project_category_id' => $this->masterId(ProjectCategory::class, 'projek_baharu'),
                'items' => [['technical_specifications' => $spec, 'unit_quantity' => 1, 'unit_cost' => $unitCost]],
            ]])
            ->set('currentStep', 3);
    }
}
