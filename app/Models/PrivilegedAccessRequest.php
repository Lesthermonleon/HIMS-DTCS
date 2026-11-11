<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivilegedAccessRequest extends Model
{
    protected $table = 'privileged_access_requests';

    protected $fillable = [
        'request_type',
        'payload',
        'status',
        'requested_by',
        'requested_reason',
        'decided_by',
        'decided_at',
        'decision_reason',
        'result_user_id',
    ];

    protected $casts = [
        'payload'    => 'array',
        'decided_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function resultUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'result_user_id');
    }
}
