<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueTicket extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'queue_tickets';

    protected $fillable = [
        'queue_type',
        'ticket_date',
        'sequence',
        'prefix',
        'ticket_no',
        'issued_by',
    ];

    protected $casts = [
        'ticket_date' => 'date',
        'sequence'    => 'integer',
    ];

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
