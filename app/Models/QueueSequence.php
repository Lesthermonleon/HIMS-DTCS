<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueSequence extends Model
{
    public const CREATED_AT = null;

    protected $table = 'queue_sequences';

    protected $fillable = [
        'queue_type',
        'ticket_date',
        'last_number',
    ];

    protected $casts = [
        'ticket_date' => 'date',
        'last_number' => 'integer',
    ];
}
