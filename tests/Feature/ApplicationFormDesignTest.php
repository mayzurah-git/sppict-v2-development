<?php

namespace Tests\Feature;

use App\Livewire\ApplicationForm;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * Reka bentuk Borang Permohonan Projek: penunjuk fasa boleh diklik, ringkasan kos di lajur kanan,
 * butang "Simpan", warna ikut peranan, serta Objektif & Skop sebagai teks berformat (Trix) yang
 * dibersihkan sebelum disimpan dan dipaparkan.
 */
class ApplicationFormDesignTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $this->applicant = User::factory()->create(['agency_id' => $agency->id]);
        $this->applicant->assignRole(Role::findOrCreate('pengguna_biasa'));
    }

    public function test_form_shows_stepper_cost_summary_and_role_theme(): void
    {
        Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->assertSee('Permohonan Projek ICT')
            ->assertSeeInOrder(['Maklumat Am', 'Perolehan', 'Perincian Projek', 'Muat Naik Dokumen', 'Pegawai Bertanggungjawab'])
            ->assertSee('Ringkasan Kos Projek')
            ->assertSee('Seterusnya: Perolehan')
            ->assertSee('Simpan')
            ->assertDontSee('Simpan Draf')
            ->assertDontSee('Fasa 1')
            ->assertSee('bg-sky-50/60', false);
    }

    public function test_stepper_goes_back_freely_but_validates_before_going_forward(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->call('goToStep', 4)
            ->assertSet('currentStep', 1)
            ->assertHasErrors(['form.title']);

        $component
            ->set('form.title', 'Projek Ujian')
            ->set('form.application_category', 'pembangunan_sistem')
            ->set('form.objectives', '<div>Objektif projek ujian yang lengkap.</div>')
            ->set('form.project_scope', '<div>Skop projek ujian yang lengkap.</div>')
            ->call('goToStep', 3)
            ->assertSet('currentStep', 2)
            ->assertHasErrors(['form.ceiling_cost']);

        $component
            ->set('form.procurement_type_id', $this->masterId(ProcurementType::class, 'pembekalan_perkhidmatan_one_off'))
            ->set('form.procurement_method_id', $this->masterId(ProcurementMethod::class, 'sebut_harga'))
            ->set('form.ceiling_cost', 20000)
            ->set('form.estimated_cost', 10000)
            ->set('form.expected_duration_months', 12)
            ->set('form.outcome_code', 'Bajet Mengurus')
            ->call('goToStep', 3)
            ->assertHasNoErrors()
            ->assertSet('currentStep', 3)
            ->call('goToStep', 1)
            ->assertSet('currentStep', 1);
    }

    public function test_rich_text_minimum_length_counts_text_not_markup(): void
    {
        Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->set('form.title', 'Projek Ujian')
            ->set('form.application_category', 'pembangunan_sistem')
            ->set('form.objectives', '<div><strong>Ok</strong><br></div>')
            ->set('form.project_scope', '<div>Skop projek ujian yang lengkap.</div>')
            ->call('nextStep')
            ->assertHasErrors(['form.objectives'])
            ->assertHasNoErrors(['form.project_scope'])
            ->assertSee('Objektif projek hendaklah sekurang-kurangnya 10 aksara.');
    }

    public function test_rich_text_is_sanitised_when_saved_and_displayed(): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class)
            ->set('form.title', 'Projek Ujian')
            ->set('form.objectives', '<div>Objektif <strong>utama</strong><script>alert(1)</script></div><ul><li onclick="x()">Satu</li></ul>')
            ->set('form.project_scope', '<div>Skop <a href="javascript:alert(1)">pautan</a></div>')
            ->call('saveDraft');

        $application = Application::findOrFail($component->get('applicationId'));

        $this->assertStringContainsString('<strong>utama</strong>', $application->objectives);
        $this->assertStringContainsString('<li>Satu</li>', $application->objectives);
        $this->assertStringNotContainsString('script', $application->objectives);
        $this->assertStringNotContainsString('onclick', $application->objectives);
        $this->assertStringNotContainsString('javascript', $application->project_scope);

        $this->actingAs($this->applicant)->get(route('application.view', $application->uuid))
            ->assertOk()
            ->assertSee('<strong>utama</strong>', false)
            ->assertSee('Skop Projek')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_legacy_plain_text_is_converted_for_editor_and_display(): void
    {
        $html = RichText::forEditor("Baris pertama\nBaris <kedua>");

        $this->assertStringContainsString('Baris pertama<br>', $html);
        $this->assertStringContainsString('&lt;kedua&gt;', $html);
        $this->assertSame('Baris pertama Baris <kedua>', RichText::plain($html));
        $this->assertSame('', RichText::forEditor(null));
    }
}
