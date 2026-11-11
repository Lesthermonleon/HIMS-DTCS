<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingHistory extends Model
{
    public const UPDATED_AT = null; // Only created_at is maintained

    protected $table = 'setting_histories';

    protected $fillable = [
        'setting_key',
        'scope',
        'old_value',
        'new_value',
        'changed_by',
        'reason',
        'ip_address',
    ];

    protected $casts = [];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
