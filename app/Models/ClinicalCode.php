<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalCode extends Model
{
    public $timestamps = false;

    protected $table = 'clinical_codes';

    protected $fillable = [
        'version_id',
        'system_id',
        'code',
        'display',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ClinicalCodeVersion::class, 'version_id');
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(ClinicalCodeSystem::class, 'system_id');
    }
}
