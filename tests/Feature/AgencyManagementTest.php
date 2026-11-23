<?php

namespace Tests\Feature;

use App\Livewire\Admin\AgencyManagement;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgencyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_management_is_accessible_to_urus_setia_only(): void
    {
        Role::create(['name' => 'pentadbir_urus_setia']);
        Role::create(['name' => 'superadmin']);

        $urusSetia = User::factory()->create();
        $urusSetia->assignRole('pentadbir_urus_setia');

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $this->actingAs($urusSetia)
            ->get(route('admin.agencies'))
            ->assertOk();

        $this->actingAs($superadmin)
            ->get(route('admin.agencies'))
            ->assertForbidden();
    }

    public function test_agency_can_be_soft_deleted_by_uuid(): void
    {
        Role::create(['name' => 'pentadbir_urus_setia']);
        $urusSetia = User::factory()->create();
        $urusSetia->assignRole('pentadbir_urus_setia');

        $agency = Agency::create([
            'code' => 'TEST',
            'name' => 'Agensi Ujian',
            'category' => 'SUK',
            'is_active' => true,
        ]);

        Livewire::actingAs($urusSetia)
            ->test(AgencyManagement::class)
            ->call('deleteAgency', $agency->uuid)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('agencies', ['id' => $agency->id]);
        $this->assertDatabaseMissing('agencies', [
            'id' => $agency->id,
            'deleted_at' => null,
        ]);
    }
}
