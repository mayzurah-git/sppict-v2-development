<?php

namespace Tests\Feature;

use App\Livewire\ApplicationForm;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationFormTest extends TestCase
{
    public function test_can_move_from_step_one_to_step_two_without_losing_form_data(): void
    {
        $component = Livewire::test(ApplicationForm::class);

        $component->set('form.title', 'Ujian Projek');
        $component->set('form.project_category', 'System Development');
        $component->set('form.objectives', 'Objektif ujian untuk memastikan navigasi berfungsi.');
        $component->set('form.project_scope', 'Skop ujian untuk memastikan maklumat dikekalkan semasa navigasi.');

        $component->call('nextStep');

        $component->assertSet('currentStep', 2);
        $component->assertSet('form.title', 'Ujian Projek');
        $component->assertSet('form.objectives', 'Objektif ujian untuk memastikan navigasi berfungsi.');
        $component->assertSet('form.project_scope', 'Skop ujian untuk memastikan maklumat dikekalkan semasa navigasi.');
    }
}
