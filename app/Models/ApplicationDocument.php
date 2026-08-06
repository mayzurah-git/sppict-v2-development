<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use OwenIt\Auditing\Contracts\Auditable;

class ApplicationDocument extends Model implements Auditable
{
    use HasFactory, SoftDeletes, HasUuids, \OwenIt\Auditing\Auditable;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'application_id',
        'document_type',
        'file_name',
        'file_path',
        'mime_type',
        'file_size_kb',
    ];

    // --- RELATIONSHIPS ---

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
