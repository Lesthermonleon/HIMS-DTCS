<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogChangeHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'catalog_change_histories';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'version',
        'action',
        'before_snapshot',
        'after_snapshot',
        'reason',
        'changed_by',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot'  => 'array',
        'version'         => 'integer',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
