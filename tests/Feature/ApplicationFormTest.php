<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Spatie\Permission\Models\Role;
use App\Livewire\ApplicationForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

class ApplicationFormTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    public function test_can_move_from_step_one_to_step_two_without_losing_form_data(): void
    {
        $component = Livewire::actingAs($this->applicant())->test(ApplicationForm::class);

        $component->set('form.title', 'Ujian Projek');
        $component->set('form.application_category', 'pembangunan_sistem');
        $component->set('form.objectives', 'Objektif ujian untuk memastikan navigasi berfungsi.');
        $component->set('form.project_scope', 'Skop ujian untuk memastikan maklumat dikekalkan semasa navigasi.');

        $component->call('nextStep');

        $component->assertSet('currentStep', 2);
        $component->assertSet('form.title', 'Ujian Projek');
        $component->assertSet('form.objectives', 'Objektif ujian untuk memastikan navigasi berfungsi.');
        $component->assertSet('form.project_scope', 'Skop ujian untuk memastikan maklumat dikekalkan semasa navigasi.');
    }
    /**
     * UM7A: borang permohonan baharu memerlukan peranan pengguna_biasa / pentadbir_urus_setia.
     */
    private function applicant(): User
    {
        $user = User::factory()->create(['agency_id' => Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true])->id]);
        $user->assignRole(Role::findOrCreate('pengguna_biasa'));

        return $user;
    }
}
