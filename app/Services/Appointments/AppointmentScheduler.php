<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AppointmentScheduler — single authority for booking rules and status transitions.
 *
 * Guarantees:
 *  - the provider is an active user holding the `doctor` role;
 *  - no overlapping active appointment exists for the same doctor or the same patient;
 *  - concurrent bookings for one doctor are serialised (the doctor row is locked
 *    inside a transaction before the overlap check);
 *  - status changes follow the allowed transition map (terminal states are final).
 */
class AppointmentScheduler
{
    /** Statuses that occupy a time slot. */
    public const ACTIVE_STATUSES = [
        Appointment::STATUS_REQUESTED,
        Appointment::STATUS_CONFIRMED,
        Appointment::STATUS_CHECKED_IN,
        Appointment::STATUS_IN_CONSULTATION,
    ];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        Appointment::STATUS_REQUESTED => [
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_NO_SHOW,
        ],
        Appointment::STATUS_CONFIRMED => [
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_NO_SHOW,
        ],
        Appointment::STATUS_CHECKED_IN => [
            Appointment::STATUS_IN_CONSULTATION,
            Appointment::STATUS_CANCELLED,
        ],
        Appointment::STATUS_IN_CONSULTATION => [
            Appointment::STATUS_COMPLETED,
        ],
        Appointment::STATUS_COMPLETED => [],
        Appointment::STATUS_CANCELLED => [],
        Appointment::STATUS_NO_SHOW   => [],
    ];

    /** Targets a Doctor may set on their OWN appointments. Admin may use any valid transition. */
    public const DOCTOR_TARGETS = [
        Appointment::STATUS_CONFIRMED,
        Appointment::STATUS_IN_CONSULTATION,
        Appointment::STATUS_COMPLETED,
    ];

    /**
     * Transitions the given actor may perform from a status.
     *
     * @return list<string>
     */
    public function allowedTransitions(string $from, User $actor): array
    {
        $targets = self::TRANSITIONS[$from] ?? [];

        if ($actor->hasRole('admin')) {
            return $targets;
        }

        if ($actor->hasRole('doctor')) {
            return array_values(array_intersect($targets, self::DOCTOR_TARGETS));
        }

        return [];
    }

    /**
     * Create an appointment after validating provider, patient and slot availability.
     *
     * @param  array{patient_id:int,doctor_id:int,scheduled_at:string,duration_minutes?:int,type:string,reason?:?string,department?:?string}  $data
     *
     * @throws ValidationException
     */
    public function book(array $data, string $initialStatus): Appointment
    {
        $duration = (int) ($data['duration_minutes'] ?? 30);

        return DB::transaction(function () use ($data, $duration, $initialStatus) {
            // Serialise concurrent bookings for this doctor.
            $doctor = User::whereKey($data['doctor_id'])->lockForUpdate()->first();

            if (! $doctor || ! $doctor->is_active || ! $doctor->hasRole('doctor')) {
                throw ValidationException::withMessages([
                    'doctor_id' => 'The selected provider is not an active doctor.',
                ]);
            }

            $start = Carbon::parse($data['scheduled_at']);
            $end   = $start->copy()->addMinutes($duration);

            if ($this->overlaps('doctor_id', (int) $doctor->id, $start, $end)) {
                throw ValidationException::withMessages([
                    'scheduled_at' => 'This doctor already has an appointment that overlaps the requested time.',
                ]);
            }

            if ($this->overlaps('patient_id', (int) $data['patient_id'], $start, $end)) {
                throw ValidationException::withMessages([
                    'scheduled_at' => 'The patient already has an appointment that overlaps the requested time.',
                ]);
            }

            return Appointment::create([
                'appointment_no'   => $this->generateAppointmentNo(),
                'patient_id'       => $data['patient_id'],
                'doctor_id'        => $doctor->id,
                'department'       => $data['department'] ?? $doctor->department ?? 'General Clinic',
                'scheduled_at'     => $start->format('Y-m-d H:i:s'),
                'duration_minutes' => $duration,
                'type'             => $data['type'],
                'reason'           => $data['reason'] ?? null,
                'status'           => $initialStatus,
            ]);
        });
    }

    /**
     * Apply a status transition under a row lock.
     *
     * @return array{0:Appointment,1:string} the updated appointment and its previous status
     *
     * @throws ValidationException
     */
    public function transition(Appointment $appointment, string $to, ?string $notes, User $actor): array
    {
        return DB::transaction(function () use ($appointment, $to, $notes, $actor) {
            $locked = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $from   = $locked->status;

            if (! in_array($to, $this->allowedTransitions($from, $actor), true)) {
                throw ValidationException::withMessages([
                    'status' => "You cannot change an appointment from [{$from}] to [{$to}].",
                ]);
            }

            $locked->status = $to;
            if ($notes !== null && $notes !== '') {
                $locked->notes = $notes;
            }
            $locked->save();

            return [$locked, $from];
        });
    }

    /** True when an ACTIVE appointment for the given doctor/patient overlaps [start, end). */
    private function overlaps(string $column, int $id, Carbon $start, Carbon $end): bool
    {
        return Appointment::query()
            ->where($column, $id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('scheduled_at', '<', $end->format('Y-m-d H:i:s'))
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$start->format('Y-m-d H:i:s')])
            ->lockForUpdate()
            ->exists();
    }

    private function generateAppointmentNo(): string
    {
        do {
            $number = 'APT-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Appointment::withTrashed()->where('appointment_no', $number)->exists());

        return $number;
    }
}
