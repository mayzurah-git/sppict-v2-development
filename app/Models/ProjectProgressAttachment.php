<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * SP3: dokumen sokongan kemajuan (PDF, storan private; audit SP0 F.7, keputusan I.8). Dipautkan kepada
 * aktiviti projek atau laporan bulanan. Dibuka melalui ProjectAttachmentController (ProjectPolicy@view).
 */
class ProjectProgressAttachment extends Model implements Auditable
{
    use HasUuids, SoftDeletes, \OwenIt\Auditing\Auditable;

    public const DISK = 'local';

    /** I.8: saiz maksimum 25 MB. */
    public const MAX_FILE_KB = 25600;

    protected $fillable = [
        'project_id',
        'project_progress_activity_id',
        'project_progress_report_id',
        'document_label',
        'file_name',
        'file_path',
        'mime_type',
        'file_size_kb',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size_kb' => 'integer',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function activity()
    {
        return $this->belongsTo(ProjectProgressActivity::class, 'project_progress_activity_id')->withTrashed();
    }

    public function report()
    {
        return $this->belongsTo(ProjectProgressReport::class, 'project_progress_report_id')->withTrashed();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
