<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'appointment_no',
        'patient_id',
        'doctor_id',
        'department',
        'scheduled_at',
        'duration_minutes',
        'type',
        'reason',
        'notes',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    // Status Constants
    public const STATUS_REQUESTED       = 'Requested';
    public const STATUS_CONFIRMED       = 'Confirmed';
    public const STATUS_CHECKED_IN      = 'CheckedIn';
    public const STATUS_IN_CONSULTATION = 'In Consultation';
    public const STATUS_COMPLETED       = 'Completed';
    public const STATUS_CANCELLED       = 'Cancelled';
    public const STATUS_NO_SHOW         = 'No Show';

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
