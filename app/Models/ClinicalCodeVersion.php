<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalCodeVersion extends Model
{
    protected $table = 'clinical_code_versions';

    protected $fillable = [
        'system_id',
        'version_label',
        'effective_date',
        'status',
        'source_filename',
        'source_sha256',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'duplicate_rows',
        'validation_report',
        'imported_by',
        'published_by',
        'published_at',
        'rolled_back_at',
        'rollback_reason',
    ];

    protected $casts = [
        'effective_date'    => 'date',
        'total_rows'        => 'integer',
        'valid_rows'        => 'integer',
        'invalid_rows'      => 'integer',
        'duplicate_rows'    => 'integer',
        'validation_report' => 'array',
        'published_at'      => 'datetime',
        'rolled_back_at'    => 'datetime',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(ClinicalCodeSystem::class, 'system_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ClinicalCode::class, 'version_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
