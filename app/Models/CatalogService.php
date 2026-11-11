<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogService extends Model
{
    protected $table = 'catalog_services';

    protected $fillable = [
        'code',
        'name',
        'module',
        'category',
        'description',
        'base_price',
        'status',
        'effective_from',
        'effective_to',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'base_price'     => 'decimal:2',
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'version'        => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
