<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogItemState extends Model
{
    protected $table = 'catalog_item_states';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'status',
        'description',
        'effective_from',
        'effective_to',
        'version',
        'retired_at',
        'updated_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'retired_at'     => 'datetime',
        'version'        => 'integer',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
