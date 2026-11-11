<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialRule extends Model
{
    public const UPDATED_AT = null; // Immutable

    protected $table = 'financial_rules';

    protected $fillable = [
        'currency_code',
        'currency_symbol',
        'tax_name',
        'tax_rate',
        'service_charge_rate',
        'applies_to',
        'notes',
        'effective_from',
        'created_by',
    ];

    protected $casts = [
        'tax_rate'            => 'decimal:4',
        'service_charge_rate' => 'decimal:4',
        'effective_from'      => 'date',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Financial rules are immutable. Create a new effective-dated rule instead of updating.');
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
