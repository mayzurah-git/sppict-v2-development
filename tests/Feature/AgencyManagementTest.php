<?php

namespace Tests\Feature;

use App\Livewire\Admin\AgencyManagement;
use App\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_can_be_soft_deleted_by_uuid(): void
    {
        $agency = Agency::create([
            'code' => 'TEST',
            'name' => 'Agensi Ujian',
            'category' => 'SUK',
            'is_active' => true,
        ]);

        Livewire::test(AgencyManagement::class)
            ->call('deleteAgency', $agency->uuid)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('agencies', ['id' => $agency->id]);
        $this->assertDatabaseMissing('agencies', [
            'id' => $agency->id,
            'deleted_at' => null,
        ]);
    }
}