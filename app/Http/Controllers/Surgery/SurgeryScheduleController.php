<?php

namespace App\Http\Controllers\Surgery;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSurgeryScheduleRequest;
use App\Models\ActivityLog;
use App\Models\OperatingRoom;
use App\Models\SurgeryRequest;
use App\Models\SurgerySchedule;
use App\Models\SurgicalTeam;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SurgeryScheduleController — OR Coordinator schedules and manages surgery events;
 * OR Staff view assigned schedules and update OR readiness and operational statuses.
 */
class SurgeryScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SurgerySchedule::class);

        /** @var User $user */
        $user = Auth::user();

        $query = SurgerySchedule::with(
            'surgeryRequest.patient',
            'surgeryRequest.doctor',
            'operatingRoom',
            'surgicalTeam.surgeon',
            'surgicalTeam.members.user',
            'scheduledBy',
            'readinessUpdatedBy'
        )->visibleTo($user);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('surgeryRequest', function ($sq) use ($search) {
                    $sq->where('request_no', 'like', "%{$search}%")
                        ->orWhere('procedure_name', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($pq) use ($search) {
                            $pq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('operating_room_id')) {
            $query->where('operating_room_id', $request->operating_room_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->date);
        }

        $schedules      = $query->latest('scheduled_at')->paginate(15)->withQueryString();
        $operatingRooms = OperatingRoom::where('is_active', true)->get();
        $statuses       = ['Scheduled', 'In Progress', 'Completed', 'Postponed', 'Cancelled'];

        return view('surgery.schedules.index', compact('schedules', 'operatingRooms', 'statuses'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SurgerySchedule::class);

        $this->ensureRoomsAndTeamsExist();

        $selectedRequestId = $request->query('request');
        $pendingRequests   = SurgeryRequest::where('status', 'Pending')->with('patient', 'doctor')->get();
        $operatingRooms    = OperatingRoom::where('is_active', true)->get();
        $surgicalTeams     = SurgicalTeam::where('is_active', true)->with('surgeon')->get();

        return view('surgery.schedules.create', compact('pendingRequests', 'operatingRooms', 'surgicalTeams', 'selectedRequestId'));
    }

    public function store(StoreSurgeryScheduleRequest $request): RedirectResponse
    {
        $this->authorize('create', SurgerySchedule::class);

        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $room = OperatingRoom::where('id', $validated['operating_room_id'])->lockForUpdate()->firstOrFail();
            if (! $room->is_active || $room->status === 'Under Maintenance') {
                throw ValidationException::withMessages([
                    'operating_room_id' => 'The selected Operating Room is unavailable or under maintenance.',
                ]);
            }

            $surgeryRequest = SurgeryRequest::where('id', $validated['surgery_request_id'])->lockForUpdate()->firstOrFail();
            if ($surgeryRequest->status !== 'Pending') {
                throw ValidationException::withMessages([
                    'surgery_request_id' => 'Only Pending surgery requests can be scheduled.',
                ]);
            }

            $roomConflict = SurgerySchedule::findRoomConflict(
                (int) $validated['operating_room_id'],
                $validated['scheduled_at'],
                (int) $validated['duration_minutes']
            );
            if ($roomConflict) {
                throw ValidationException::withMessages([
                    'operating_room_id' => "Operating Room conflict: {$room->name} is already booked during this time window.",
                ]);
            }

            $teamConflict = SurgerySchedule::findTeamConflict(
                isset($validated['surgical_team_id']) ? (int) $validated['surgical_team_id'] : null,
                $validated['scheduled_at'],
                (int) $validated['duration_minutes']
            );
            if ($teamConflict) {
                throw ValidationException::withMessages([
                    'surgical_team_id' => 'Surgical Team conflict: the selected team is already booked during this time window.',
                ]);
            }

            $schedule = SurgerySchedule::create(array_merge($validated, [
                'scheduled_by'     => Auth::id(),
                'status'           => 'Scheduled',
                'readiness_status' => 'Pending Prep',
            ]));

            $surgeryRequest->update(['status' => 'Scheduled']);

            $reqLabel = $surgeryRequest->request_no ?? ('#' . $validated['surgery_request_id']);

            ActivityLog::create([
                'user_id'       => Auth::id(),
                'action'        => 'Surgery Scheduled',
                'module'        => 'Surgery',
                'severity'      => ActivityLog::SEVERITY_INFO,
                'result'        => ActivityLog::RESULT_SUCCESS,
                'description'   => "Surgery schedule created for request [{$reqLabel}].",
                'loggable_type' => SurgerySchedule::class,
                'loggable_id'   => $schedule->id,
                'ip_address'    => request()->ip(),
                'logged_at'     => now(),
            ]);
        });

        return redirect()->route('surgery.schedules.index')
            ->with('success', 'Surgery scheduled successfully.');
    }

    public function show(SurgerySchedule $surgerySchedule): View
    {
        $this->authorize('view', $surgerySchedule);

        $surgerySchedule->load(
            'surgeryRequest.patient',
            'surgeryRequest.doctor',
            'operatingRoom',
            'surgicalTeam.surgeon',
            'surgicalTeam.members.user',
            'scheduledBy',
            'readinessUpdatedBy'
        );

        return view('surgery.schedules.show', compact('surgerySchedule'));
    }

    public function edit(SurgerySchedule $surgerySchedule): View
    {
        if (in_array($surgerySchedule->status, ['Completed', 'Cancelled'], true)) {
            abort(422, "Cannot edit a surgery schedule with status [{$surgerySchedule->status}].");
        }

        $this->authorize('update', $surgerySchedule);

        $this->ensureRoomsAndTeamsExist();

        $pendingRequests = SurgeryRequest::whereIn('status', ['Pending', 'Scheduled'])->with('patient')->get();
        $operatingRooms  = OperatingRoom::where('is_active', true)->get();
        $surgicalTeams   = SurgicalTeam::where('is_active', true)->with('surgeon')->get();

        return view('surgery.schedules.edit', compact('surgerySchedule', 'pendingRequests', 'operatingRooms', 'surgicalTeams'));
    }

    public function update(StoreSurgeryScheduleRequest $request, SurgerySchedule $surgerySchedule): RedirectResponse
    {
        if (in_array($surgerySchedule->status, ['Completed', 'Cancelled'], true)) {
            abort(422, "Cannot modify a surgery schedule with status [{$surgerySchedule->status}].");
        }

        $this->authorize('update', $surgerySchedule);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $surgerySchedule) {
            $lockedSchedule = SurgerySchedule::where('id', $surgerySchedule->id)->lockForUpdate()->firstOrFail();
            if (in_array($lockedSchedule->status, ['Completed', 'Cancelled'], true)) {
                abort(422, "Cannot modify a surgery schedule with status [{$lockedSchedule->status}].");
            }

            $oldScheduledAt = $lockedSchedule->scheduled_at ? Carbon::parse($lockedSchedule->scheduled_at)->toDateTimeString() : null;
            $newScheduledAt = isset($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at'])->toDateTimeString() : $oldScheduledAt;
            $oldRoomId      = (int) $lockedSchedule->operating_room_id;
            $newRoomId      = (int) ($validated['operating_room_id'] ?? $oldRoomId);
            $newStatus      = $validated['status'] ?? $lockedSchedule->status;

            $isRescheduled = ($oldScheduledAt !== $newScheduledAt) || ($oldRoomId !== $newRoomId);
            $isCancelled   = ($newStatus === 'Cancelled' && $lockedSchedule->status !== 'Cancelled');

            if (! $isCancelled) {
                $room = OperatingRoom::where('id', $newRoomId)->lockForUpdate()->firstOrFail();
                $roomConflict = SurgerySchedule::findRoomConflict(
                    $newRoomId,
                    $newScheduledAt,
                    (int) ($validated['duration_minutes'] ?? $lockedSchedule->duration_minutes ?? 60),
                    (int) $lockedSchedule->id
                );
                if ($roomConflict) {
                    throw ValidationException::withMessages([
                        'operating_room_id' => "Operating Room conflict: {$room->name} is already booked during this time window.",
                    ]);
                }

                $teamId = array_key_exists('surgical_team_id', $validated)
                    ? ($validated['surgical_team_id'] ? (int) $validated['surgical_team_id'] : null)
                    : ($lockedSchedule->surgical_team_id ? (int) $lockedSchedule->surgical_team_id : null);

                $teamConflict = SurgerySchedule::findTeamConflict(
                    $teamId,
                    $newScheduledAt,
                    (int) ($validated['duration_minutes'] ?? $lockedSchedule->duration_minutes ?? 60),
                    (int) $lockedSchedule->id
                );
                if ($teamConflict) {
                    throw ValidationException::withMessages([
                        'surgical_team_id' => 'Surgical Team conflict: the selected team is already booked during this time window.',
                    ]);
                }
            }

            $lockedSchedule->update($validated);

            if ($isCancelled) {
                if ($lockedSchedule->surgeryRequest) {
                    $lockedSchedule->surgeryRequest->update(['status' => 'Cancelled']);
                }
                $this->releaseRoomIfNoActiveCase($lockedSchedule->operating_room_id);

                ActivityLog::create([
                    'user_id'       => Auth::id(),
                    'action'        => 'Surgery Cancelled',
                    'module'        => 'Surgery',
                    'severity'      => ActivityLog::SEVERITY_WARNING,
                    'result'        => ActivityLog::RESULT_SUCCESS,
                    'description'   => "Surgery schedule #{$lockedSchedule->id} was cancelled.",
                    'loggable_type' => SurgerySchedule::class,
                    'loggable_id'   => $lockedSchedule->id,
                    'ip_address'    => request()->ip(),
                    'logged_at'     => now(),
                ]);
            } elseif ($isRescheduled) {
                if ($newStatus === 'Scheduled' && $lockedSchedule->surgeryRequest && $lockedSchedule->surgeryRequest->status === 'Pending') {
                    $lockedSchedule->surgeryRequest->update(['status' => 'Scheduled']);
                }

                ActivityLog::create([
                    'user_id'       => Auth::id(),
                    'action'        => 'Surgery Rescheduled',
                    'module'        => 'Surgery',
                    'severity'      => ActivityLog::SEVERITY_INFO,
                    'result'        => ActivityLog::RESULT_SUCCESS,
                    'description'   => "Surgery schedule #{$lockedSchedule->id} was rescheduled to {$newScheduledAt} (OR #{$newRoomId}).",
                    'loggable_type' => SurgerySchedule::class,
                    'loggable_id'   => $lockedSchedule->id,
                    'ip_address'    => request()->ip(),
                    'logged_at'     => now(),
                ]);
            } else {
                ActivityLog::create([
                    'user_id'       => Auth::id(),
                    'action'        => 'Surgery Schedule Updated',
                    'module'        => 'Surgery',
                    'severity'      => ActivityLog::SEVERITY_INFO,
                    'result'        => ActivityLog::RESULT_SUCCESS,
                    'description'   => "Surgery schedule #{$lockedSchedule->id} was updated.",
                    'loggable_type' => SurgerySchedule::class,
                    'loggable_id'   => $lockedSchedule->id,
                    'ip_address'    => request()->ip(),
                    'logged_at'     => now(),
                ]);
            }
        });

        return redirect()->route('surgery.schedules.show', $surgerySchedule)
            ->with('success', 'Schedule updated successfully.');
    }

    public function destroy(SurgerySchedule $surgerySchedule): RedirectResponse
    {
        if (! in_array($surgerySchedule->status, ['Scheduled', 'Postponed'], true)) {
            abort(422, "Cannot remove a surgery schedule with status [{$surgerySchedule->status}].");
        }

        $this->authorize('delete', $surgerySchedule);

        DB::transaction(function () use ($surgerySchedule) {
            $lockedSchedule = SurgerySchedule::where('id', $surgerySchedule->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedSchedule->status, ['Scheduled', 'Postponed'], true)) {
                abort(422, "Cannot remove a surgery schedule with status [{$lockedSchedule->status}].");
            }

            $scheduleId = $lockedSchedule->id;

            if ($lockedSchedule->surgeryRequest) {
                $lockedSchedule->surgeryRequest->update(['status' => 'Pending']);
            }
            $lockedSchedule->delete();

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Surgery Schedule Removed',
                'module'      => 'Surgery',
                'severity'    => ActivityLog::SEVERITY_WARNING,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'description' => "Surgery schedule #{$scheduleId} was removed and request returned to Pending.",
                'ip_address'  => request()->ip(),
                'logged_at'   => now(),
            ]);
        });

        return redirect()->route('surgery.schedules.index')
            ->with('success', 'Schedule removed. Request returned to pending.');
    }

    /**
     * Cancel an active schedule (Scheduled / Postponed -> Cancelled).
     */
    public function cancel(SurgerySchedule $surgerySchedule): RedirectResponse
    {
        if (! in_array($surgerySchedule->status, ['Scheduled', 'Postponed'], true)) {
            abort(422, "Only Scheduled or Postponed operations can be cancelled (current status: {$surgerySchedule->status}).");
        }

        $this->authorize('update', $surgerySchedule);

        DB::transaction(function () use ($surgerySchedule) {
            $lockedSchedule = SurgerySchedule::where('id', $surgerySchedule->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedSchedule->status, ['Scheduled', 'Postponed'], true)) {
                abort(422, "Only Scheduled or Postponed operations can be cancelled (current status: {$lockedSchedule->status}).");
            }

            $lockedSchedule->update(['status' => 'Cancelled']);
            if ($lockedSchedule->surgeryRequest) {
                $lockedSchedule->surgeryRequest->update(['status' => 'Cancelled']);
            }

            $this->releaseRoomIfNoActiveCase($lockedSchedule->operating_room_id);

            ActivityLog::create([
                'user_id'       => Auth::id(),
                'action'        => 'Surgery Cancelled',
                'module'        => 'Surgery',
                'severity'      => ActivityLog::SEVERITY_WARNING,
                'result'        => ActivityLog::RESULT_SUCCESS,
                'description'   => "Surgery schedule #{$lockedSchedule->id} was cancelled.",
                'loggable_type' => SurgerySchedule::class,
                'loggable_id'   => $lockedSchedule->id,
                'ip_address'    => request()->ip(),
                'logged_at'     => now(),
            ]);
        });

        return back()->with('success', 'Surgery schedule cancelled.');
    }

    /**
     * Update OR preparation / readiness status and operational notes (OR Coordinator & assigned OR Staff).
     */
    public function updateReadiness(Request $request, SurgerySchedule $surgerySchedule): RedirectResponse
    {
        if (in_array($surgerySchedule->status, ['Completed', 'Cancelled'], true)) {
            abort(422, "Cannot update OR readiness for a [{$surgerySchedule->status}] operation.");
        }

        $this->authorize('updateReadiness', $surgerySchedule);

        $validated = $request->validate([
            'readiness_status' => 'required|in:Pending Prep,Preparing,Ready',
            'readiness_notes'  => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($validated, $surgerySchedule) {
            $lockedSchedule = SurgerySchedule::where('id', $surgerySchedule->id)->lockForUpdate()->firstOrFail();
            if (in_array($lockedSchedule->status, ['Completed', 'Cancelled'], true)) {
                abort(422, "Cannot update OR readiness for a [{$lockedSchedule->status}] operation.");
            }

            $previousStatus = $lockedSchedule->readiness_status ?? 'Pending Prep';
            $newStatus      = $validated['readiness_status'];

            $lockedSchedule->update([
                'previous_readiness_status' => $previousStatus,
                'readiness_status'          => $newStatus,
                'readiness_notes'           => $validated['readiness_notes'] ?? $lockedSchedule->readiness_notes,
                'readiness_updated_by'      => Auth::id(),
                'readiness_updated_at'      => now(),
            ]);

            ActivityLog::create([
                'user_id'       => Auth::id(),
                'action'        => 'OR Readiness Updated',
                'module'        => 'Surgery',
                'severity'      => ActivityLog::SEVERITY_INFO,
                'result'        => ActivityLog::RESULT_SUCCESS,
                'description'   => "OR readiness for schedule #{$lockedSchedule->id} updated from [{$previousStatus}] to [{$newStatus}].",
                'loggable_type' => SurgerySchedule::class,
                'loggable_id'   => $lockedSchedule->id,
                'ip_address'    => request()->ip(),
                'logged_at'     => now(),
            ]);
        });

        return back()->with('success', "OR readiness updated to {$validated['readiness_status']}.");
    }

    public function start(SurgerySchedule $surgerySchedule): RedirectResponse
    {
        $this->authorize('updateStatus', $surgerySchedule);

        if ($surgerySchedule->status !== 'Scheduled') {
            abort(422, "Invalid status transition: Only 'Scheduled' surgeries can be started (current status: {$surgerySchedule->status}).");
        }

        DB::transaction(function () use ($surgerySchedule) {
            $lockedSchedule = SurgerySchedule::where('id', $surgerySchedule->id)->lockForUpdate()->firstOrFail();
            if ($lockedSchedule->status !== 'Scheduled') {
                abort(422, "Invalid status transition: Only 'Scheduled' surgeries can be started (current status: {$lockedSchedule->status}).");
            }

            $lockedSchedule->update([
                'status'       => 'In Progress',
                'actual_start' => now(),
            ]);

            if ($lockedSchedule->surgeryRequest) {
                $lockedSchedule->surgeryRequest->update(['status' => 'In Progress']);
            }

            if ($lockedSchedule->operatingRoom) {
                $lockedSchedule->operatingRoom->update(['status' => 'Occupied']);
            }

            ActivityLog::create([
                'user_id'       => Auth::id(),
                'action'        => 'Surgery Started',
                'module'        => 'Surgery',
                'severity'      => ActivityLog::SEVERITY_INFO,
                'result'        => ActivityLog::RESULT_SUCCESS,
                'description'   => "Surgery schedule #{$lockedSchedule->id} marked as In Progress.",
                'loggable_type' => SurgerySchedule::class,
                'loggable_id'   => $lockedSchedule->id,
                'ip_address'    => request()->ip(),
                'logged_at'     => now(),
            ]);
        });

        return back()->with('success', 'Surgery marked as In Progress.');
    }

    public function complete(SurgerySchedule $surgerySchedule): RedirectResponse
    {
        $this->authorize('updateStatus', $surgerySchedule);

        if ($surgerySchedule->status !== 'In Progress') {
            abort(422, "Invalid status transition: Only 'In Progress' surgeries can be completed (current status: {$surgerySchedule->status}).");
        }

        DB::transaction(function () use ($surgerySchedule) {
            $lockedSchedule = SurgerySchedule::where('id', $surgerySchedule->id)->lockForUpdate()->firstOrFail();
            if ($lockedSchedule->status !== 'In Progress') {
                abort(422, "Invalid status transition: Only 'In Progress' surgeries can be completed (current status: {$lockedSchedule->status}).");
            }

            $lockedSchedule->update([
                'status'     => 'Completed',
                'actual_end' => now(),
            ]);

            if ($lockedSchedule->surgeryRequest) {
                $lockedSchedule->surgeryRequest->update(['status' => 'Completed']);
            }

            $this->releaseRoomIfNoActiveCase($lockedSchedule->operating_room_id);

            ActivityLog::create([
                'user_id'       => Auth::id(),
                'action'        => 'Surgery Completed',
                'module'        => 'Surgery',
                'severity'      => ActivityLog::SEVERITY_INFO,
                'result'        => ActivityLog::RESULT_SUCCESS,
                'description'   => "Surgery schedule #{$lockedSchedule->id} was marked as Completed.",
                'loggable_type' => SurgerySchedule::class,
                'loggable_id'   => $lockedSchedule->id,
                'ip_address'    => request()->ip(),
                'logged_at'     => now(),
            ]);
        });

        return back()->with('success', 'Surgery marked as completed.');
    }

    /** Print-friendly view for surgery schedule. */
    public function print(SurgerySchedule $surgerySchedule): View
    {
        $this->authorize('view', $surgerySchedule);

        $surgerySchedule->load(
            'surgeryRequest.patient',
            'surgeryRequest.doctor',
            'operatingRoom',
            'surgicalTeam.surgeon',
            'surgicalTeam.members.user',
            'scheduledBy',
            'coordinator',
            'readinessUpdatedBy'
        );

        return view('surgery.schedules.print', compact('surgerySchedule'));
    }

    /** FullCalendar JSON events feed. */
    public function calendarEvents(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', SurgerySchedule::class);

        /** @var User $user */
        $user = Auth::user();

        $query = SurgerySchedule::with('surgeryRequest.patient', 'surgeryRequest.doctor', 'operatingRoom', 'surgicalTeam.surgeon')
            ->visibleTo($user)
            ->whereNotIn('status', ['Cancelled']);

        if ($request->filled('operating_room_id')) {
            $query->where('operating_room_id', $request->operating_room_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $events = $query->get()->map(fn ($s) => [
            'id'    => $s->id,
            'title' => ($s->surgeryRequest->patient->last_name ?? '?') . ' — ' . ($s->surgeryRequest->procedure_name ?? 'Procedure'),
            'start' => $s->scheduled_at->toIso8601String(),
            'end'   => $s->scheduled_at->copy()->addMinutes($s->duration_minutes ?? 60)->toIso8601String(),
            'color' => match ($s->status) {
                'Scheduled'   => '#0d6efd',
                'In Progress' => '#fd7e14',
                'Completed'   => '#198754',
                'Postponed'   => '#6c757d',
                default       => '#dc3545',
            },
            'extendedProps' => [
                'or'               => $s->operatingRoom->name ?? 'OR',
                'or_id'            => $s->operating_room_id,
                'status'           => $s->status,
                'readiness_status' => $s->readiness_status ?? 'Pending Prep',
                'patient_name'     => ($s->surgeryRequest->patient->first_name ?? '') . ' ' . ($s->surgeryRequest->patient->last_name ?? ''),
                'patient_no'       => $s->surgeryRequest->patient->patient_no ?? '',
                'surgeon'          => $s->surgicalTeam->surgeon->name ?? 'Surgeon',
                'request_by'       => $s->surgeryRequest->doctor->name ?? 'Doctor',
                'request_no'       => $s->surgeryRequest->request_no ?? '',
                'procedure'        => $s->surgeryRequest->procedure_name ?? 'Procedure',
                'urgency'          => $s->surgeryRequest->urgency ?? 'Elective',
                'duration'         => $s->duration_minutes ?? 60,
                'scheduled_at'     => $s->scheduled_at->format('M d, Y · h:i A'),
                'scheduled_date'   => $s->scheduled_at->format('Y-m-d'),
                'start_time'       => $s->scheduled_at->format('g:i A'),
                'end_time'         => $s->scheduled_at->copy()->addMinutes($s->duration_minutes ?? 60)->format('g:i A'),
            ],
        ]);

        return response()->json($events);
    }

    /** Calendar view page. */
    public function calendar(): View
    {
        $this->authorize('viewAny', SurgerySchedule::class);

        /** @var User $user */
        $user = Auth::user();

        $operatingRooms    = OperatingRoom::where('is_active', true)->get();
        $upcomingSchedules = SurgerySchedule::with('surgeryRequest.patient', 'operatingRoom', 'surgicalTeam.surgeon')
            ->visibleTo($user)
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->whereNotIn('status', ['Cancelled', 'Completed'])
            ->orderBy('scheduled_at', 'asc')
            ->take(30)
            ->get();

        return view('surgery.calendar', compact('operatingRooms', 'upcomingSchedules'));
    }

    /**
     * Release operating room back to 'Available' if no other schedule is 'In Progress' in that room.
     */
    private function releaseRoomIfNoActiveCase(?int $operatingRoomId): void
    {
        if (! $operatingRoomId) {
            return;
        }

        $stillInProgress = SurgerySchedule::where('operating_room_id', $operatingRoomId)
            ->where('status', 'In Progress')
            ->exists();

        if (! $stillInProgress) {
            OperatingRoom::where('id', $operatingRoomId)
                ->where('status', 'Occupied')
                ->update(['status' => 'Available']);
        }
    }

    /** Helper to ensure sample ORs and Teams exist if database lacks them. */
    private function ensureRoomsAndTeamsExist(): void
    {
        if (OperatingRoom::count() === 0) {
            $defaultRooms = [
                ['name' => 'OR-1 (General Surgery)', 'location' => '3rd Floor, Surgical Wing', 'status' => 'Available', 'is_active' => true],
                ['name' => 'OR-2 (Orthopedics)',      'location' => '3rd Floor, Surgical Wing', 'status' => 'Available', 'is_active' => true],
                ['name' => 'OR-3 (Cardiac Center)',   'location' => '4th Floor, Cardiac Center', 'status' => 'Available', 'is_active' => true],
                ['name' => 'OR-4 (Neuro & Trauma)',   'location' => 'Ground Floor, ER Wing',   'status' => 'Available', 'is_active' => true],
            ];
            foreach ($defaultRooms as $room) {
                OperatingRoom::create($room);
            }
        }

        if (SurgicalTeam::count() === 0) {
            $doctor = User::whereHas('roles', fn ($q) => $q->where('slug', 'doctor'))->first() ?? User::first();
            if ($doctor) {
                SurgicalTeam::create([
                    'name'       => 'General Surgery Team Alpha',
                    'surgeon_id' => $doctor->id,
                    'notes'      => 'Primary General Surgery Team',
                    'is_active'  => true,
                ]);
            }
        }
    }
}
