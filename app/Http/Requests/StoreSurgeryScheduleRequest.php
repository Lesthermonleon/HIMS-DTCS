<?php

namespace App\Http\Requests;

use App\Models\OperatingRoom;
use App\Models\SurgeryRequest;
use App\Models\SurgerySchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSurgeryScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var SurgerySchedule|null $existingSchedule */
        $existingSchedule = $this->route('surgerySchedule');
        $isUpdate = $existingSchedule !== null;

        return [
            'surgery_request_id' => $isUpdate
                ? 'sometimes|required|exists:surgery_requests,id'
                : 'required|exists:surgery_requests,id',
            'operating_room_id'  => 'required|exists:operating_rooms,id',
            'surgical_team_id'   => 'nullable|exists:surgical_teams,id',
            'scheduled_at'       => $isUpdate ? 'required|date' : 'required|date|after:now',
            'duration_minutes'   => 'required|integer|min:15|max:600',
            'status'             => 'sometimes|in:Scheduled,Postponed,Cancelled',
            'notes'              => 'nullable|string|max:1000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            /** @var SurgerySchedule|null $existingSchedule */
            $existingSchedule = $this->route('surgerySchedule');
            $ignoreId = $existingSchedule?->id;

            $targetStatus = $this->input('status', $existingSchedule?->status ?? 'Scheduled');
            $roomId       = (int) $this->input('operating_room_id');
            $teamId       = $this->filled('surgical_team_id') ? (int) $this->input('surgical_team_id') : null;
            $scheduledAt  = $this->input('scheduled_at');
            $duration     = (int) $this->input('duration_minutes', 60);

            // 1. Validate Operating Room availability & active status
            $room = OperatingRoom::find($roomId);
            if (! $room || ! $room->is_active || $room->status === 'Under Maintenance') {
                $v->errors()->add(
                    'operating_room_id',
                    'The selected operating room is currently unavailable or under maintenance.'
                );
                return;
            }

            // 2. When creating a new schedule, ensure the SurgeryRequest is Pending and not already scheduled
            if (! $existingSchedule) {
                $requestId = (int) $this->input('surgery_request_id');
                $surgeryRequest = SurgeryRequest::find($requestId);

                if (! $surgeryRequest || $surgeryRequest->status !== 'Pending') {
                    $v->errors()->add(
                        'surgery_request_id',
                        'Only pending surgery requests can be scheduled.'
                    );
                    return;
                }

                $alreadyScheduled = SurgerySchedule::where('surgery_request_id', $requestId)
                    ->whereIn('status', ['Scheduled', 'In Progress'])
                    ->exists();

                if ($alreadyScheduled) {
                    $v->errors()->add(
                        'surgery_request_id',
                        'This surgery request already has an active operating room schedule.'
                    );
                    return;
                }
            }

            // 3. Check Operating Room and Surgical Team time-window conflicts for active schedules
            if (in_array($targetStatus, ['Scheduled', 'In Progress'], true)) {
                $roomConflict = SurgerySchedule::findRoomConflict($roomId, $scheduledAt, $duration, $ignoreId);
                if ($roomConflict) {
                    $conflictReqNo = $roomConflict->surgeryRequest?->request_no ?? ('#' . $roomConflict->id);
                    $v->errors()->add(
                        'operating_room_id',
                        "Scheduling conflict: {$room->name} is already booked during this time window (Case {$conflictReqNo})."
                    );
                }

                if ($teamId) {
                    $teamConflict = SurgerySchedule::findTeamConflict($teamId, $scheduledAt, $duration, $ignoreId);
                    if ($teamConflict) {
                        $conflictReqNo = $teamConflict->surgeryRequest?->request_no ?? ('#' . $teamConflict->id);
                        $v->errors()->add(
                            'surgical_team_id',
                            "Scheduling conflict: The selected surgical team is already assigned to another operation during this time window (Case {$conflictReqNo})."
                        );
                    }
                }
            }
        });
    }
}
