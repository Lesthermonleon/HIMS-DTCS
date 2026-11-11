<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PrivilegedAccessRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DoctorProvisioningController extends Controller
{
    /**
     * Display pending and past doctor provisioning authorization requests.
     */
    public function index(): View
    {
        $requests = PrivilegedAccessRequest::where('request_type', 'doctor_provisioning')
            ->with(['requestedBy', 'decidedBy', 'resultUser'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('super_admin.doctor_provisioning.index', compact('requests'));
    }

    /**
     * Approve a Doctor provisioning request and initialize clinician account.
     */
    public function approve(Request $request, PrivilegedAccessRequest $accessRequest): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($accessRequest->status !== 'pending') {
            return back()->with('error', 'This provisioning request has already been processed.');
        }

        $payload = $accessRequest->payload ?? [];
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? null;

        if (! $email || ! $name) {
            return back()->with('error', 'Request payload is missing required clinician email or name.');
        }

        // Check for duplicate email
        if (User::where('email', $email)->exists()) {
            return back()->with('error', "An account with email [{$email}] already exists.");
        }

        $doctorRole = Role::where('slug', 'doctor')->firstOrFail();

        $newUser = DB::transaction(function () use ($accessRequest, $payload, $email, $name, $doctorRole, $validated) {
            $randomSecret = Str::random(32);

            $user = User::create([
                'name'              => $name,
                'email'             => $email,
                'password'          => Hash::make($randomSecret),
                'department'        => $payload['department'] ?? 'Clinical Medicine',
                'employee_id'       => $payload['employee_id'] ?? null,
                'phone'             => $payload['phone'] ?? null,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);

            $user->roles()->sync([$doctorRole->id]);

            $accessRequest->update([
                'status'          => 'approved',
                'decided_by'      => Auth::id(),
                'decided_at'      => now(),
                'decision_reason' => $validated['reason'],
                'result_user_id'  => $user->id,
            ]);

            // Dispatch password setup notification
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Doctor Provisioning Approved',
                'module'      => ActivityLog::MODULE_SECURITY,
                'description' => sprintf(
                    'Super Admin approved Doctor provisioning request #%d for Dr. %s [%s]. Account created (ID: %d) and password invitation sent.',
                    $accessRequest->id,
                    $user->name,
                    $user->email,
                    $user->id
                ),
                'severity'    => ActivityLog::SEVERITY_CRITICAL,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);

            return $user;
        });

        return back()->with('success', "Doctor account for [{$newUser->name}] approved and initialized.");
    }

    /**
     * Reject a Doctor provisioning request.
     */
    public function reject(Request $request, PrivilegedAccessRequest $accessRequest): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($accessRequest->status !== 'pending') {
            return back()->with('error', 'This provisioning request has already been processed.');
        }

        $accessRequest->update([
            'status'          => 'rejected',
            'decided_by'      => Auth::id(),
            'decided_at'      => now(),
            'decision_reason' => $validated['reason'],
        ]);

        try {
            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Doctor Provisioning Rejected',
                'module'      => ActivityLog::MODULE_SECURITY,
                'description' => sprintf(
                    'Super Admin rejected Doctor provisioning request #%d for [%s]. Reason: %s',
                    $accessRequest->id,
                    $accessRequest->payload['name'] ?? 'Unknown',
                    $validated['reason']
                ),
                'severity'    => ActivityLog::SEVERITY_WARNING,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);
        } catch (\Throwable $e) {}

        return back()->with('success', "Provisioning request #{$accessRequest->id} was rejected.");
    }
}
