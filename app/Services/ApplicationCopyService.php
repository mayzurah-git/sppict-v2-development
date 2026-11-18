<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\ApplicationOfficer;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PP4.3C: Salin Permohonan bagi keputusan JTICT Kaji Semula. Permohonan baharu (Draf, ID / UUID / nombor
 * rujukan baharu) diisi dengan F1–F3 permohonan asal; permohonan asal tidak diubah.
 *
 * Tidak disalin: dokumen (F4), keputusan Pra JTICT / JTICT, sejarah semakan & ulasan, mesyuarat,
 * status / tarikh proses, medan legacy keputusan. Master data tidak aktif / dipadam dikosongkan supaya
 * dipilih semula dalam borang.
 */
class ApplicationCopyService
{
    public function __construct(private ApplicationSubmissionService $submissions)
    {
    }

    /**
     * @throws AuthorizationException  tidak layak (O7), bukan Kaji Semula, atau telah disalin (O8)
     */
    public function copy(Application $source, User $user): Application
    {
        $copy = DB::transaction(function () use ($source, $user) {
            // Kunci rekod asal: permintaan serentak menunggu, kemudian ditolak oleh semakan "telah disalin".
            $locked = Application::withTrashed()->lockForUpdate()->findOrFail($source->id);
            $actor = User::findOrFail($user->id); // peranan, status & agensi terkini

            if ($actor->cannot('copy', $locked)) {
                throw new AuthorizationException('Permohonan ini tidak boleh disalin.');
            }

            $copy = new Application([
                'reference_number' => $this->submissions->nextReferenceNumber(isDraft: true),
                'agency_id' => $locked->agency_id,
                'applicant_id' => $actor->id,
                'status' => ApplicationStatus::Draft->value,
                // F1
                'title' => $locked->title,
                'application_category' => ApplicationCategory::active()->where('code', $locked->application_category)->exists() ? $locked->application_category : null,
                'objectives' => $locked->objectives,
                'project_scope' => $locked->project_scope,
                // F2
                ...$this->activeMaster(ProcurementType::class, $locked->procurement_type_id, 'procurement_type'),
                ...$this->activeMaster(ProcurementMethod::class, $locked->procurement_method_id, 'procurement_method'),
                'ceiling_cost' => $locked->ceiling_cost,
                'estimated_cost' => $locked->estimated_cost,
                'subtotal_cost' => $locked->subtotal_cost,
                'sst_rate' => $locked->sst_rate,
                'sst_amount' => $locked->sst_amount,
                'total_cost' => $locked->total_cost,
                'expected_duration_months' => $locked->expected_duration_months,
                'outcome_code' => $locked->outcome_code,
            ]);
            $copy->forceFill(['copied_from_application_id' => $locked->id])->save();

            // F3: rekod perincian baharu (bukan dikongsi dengan permohonan asal)
            foreach ($locked->details()->orderBy('id')->get() as $detail) {
                $category = $detail->project_category_id ? ProjectCategory::active()->find($detail->project_category_id) : null;

                $copy->details()->create([
                    // item_category NOT NULL: snapshot asal dikekalkan jika kategori tidak aktif (ditulis semula semasa disimpan)
                    'item_category' => $category?->name ?? $detail->item_category,
                    'project_category_id' => $category?->id,
                    'technical_specifications' => $detail->technical_specifications,
                    'unit_quantity' => $detail->unit_quantity,
                    'unit_cost' => $detail->unit_cost,
                    'total_cost' => $detail->total_cost,
                ]);
            }

            // Pegawai Bertanggungjawab aktif yang masih layak (aktif, agensi permohonan); tarikh mula ditetapkan semasa dihantar.
            $eligible = User::eligibleOfficersFor($locked->agency_id)->keyBy('id');
            foreach ($locked->officers()->active()->get() as $officer) {
                if ($officerUser = $eligible->get((int) $officer->user_id)) {
                    $copy->officers()->create(ApplicationOfficer::snapshotFrom($officerUser) + [
                        'role' => $officer->role,
                        'assigned_by' => $actor->id,
                    ]);
                }
            }

            return $copy;
        });

        Log::info("Permohonan disalin: {$source->reference_number} -> {$copy->reference_number} oleh ID Pengguna {$user->id}");

        return $copy;
    }

    /**
     * Rujukan master (ID + snapshot nama) hanya jika masih aktif dan belum dipadam; jika tidak, dikosongkan.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @return array<string, mixed>
     */
    private function activeMaster(string $model, ?int $id, string $field): array
    {
        $master = $id ? $model::active()->find($id) : null;

        return [$field . '_id' => $master?->id, $field => $master?->name];
    }
}
