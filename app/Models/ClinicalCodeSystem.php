<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalCodeSystem extends Model
{
    protected $table = 'clinical_code_systems';

    protected $fillable = [
        'system_key',
        'name',
        'kind',
        'license_status',
        'license_reference',
        'license_notes',
        'jurisdiction',
        'license_verified_by',
        'license_verified_at',
        'active_version_id',
    ];

    protected $casts = [
        'license_verified_at' => 'datetime',
    ];

    public function licenseVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'license_verified_by');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(ClinicalCodeVersion::class, 'active_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ClinicalCodeVersion::class, 'system_id');
    }

    public function isVerified(): bool
    {
        return $this->license_status === 'verified';
    }
}
