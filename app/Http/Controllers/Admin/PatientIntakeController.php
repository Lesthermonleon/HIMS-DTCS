<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Patient;
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

/**
 * PatientIntakeController — Patient Portal Account Linking (adapter only).
 *
 * SCOPE: patient master records are owned by the upstream patient registration
 * system. This controller therefore NEVER creates or edits a patient record. It
 * only links an EXISTING patient to a portal login (users.patient_id <-> patients.user_id),
 * after the administrator attests that the person's identity was verified.
 *
 * No default or guessable password is ever set: the account is created with a
 * random secret and the patient receives a password-reset link to choose their own.
 */
class PatientIntakeController extends Controller
{
    public function create(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $patients = Patient::query()
            ->with('user:id,email,is_active')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('patient_no', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.patients.intake', compact('patients', 'search'));
    }

    /**
     * Link an existing patient record to a new portal account.
     */
    public function linkAccount(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'email'             => ['required', 'email', 'max:255', 'unique:users,email'],
            'identity_verified' => ['accepted'],
        ], [
            'identity_verified.accepted' => 'You must confirm that the patient\'s identity was verified before creating portal access.',
        ]);

        if ($patient->user_id !== null || User::where('patient_id', $patient->id)->exists()) {
            return back()->withErrors(['email' => 'This patient already has a portal account.']);
        }

        // If the patient master already holds an email, the portal login must use that address,
        // so portal access cannot be pointed at an unrelated mailbox.
        if (! empty($patient->email) && strcasecmp($patient->email, $validated['email']) !== 0) {
            return back()->withErrors([
                'email' => 'The email must match the contact email on the patient record.',
            ])->withInput();
        }

        $patientRole = Role::where('slug', 'patient')->first();
        if (! $patientRole) {
            return back()->withErrors(['email' => 'The Patient role is not configured. Apply the panelist redesign schema patch first.']);
        }

        $user = DB::transaction(function () use ($patient, $validated, $patientRole) {
            $user = User::create([
                'name'       => $patient->full_name,
                'email'      => $validated['email'],
                'password'   => Hash::make(Str::random(40)), // unknown to everyone; patient sets their own via reset link
                'department' => 'Patient Portal',
                'patient_id' => $patient->id,
                'phone'      => $patient->phone,
                'is_active'  => true,
            ]);

            $user->roles()->sync([$patientRole->id]);
            $patient->update(['user_id' => $user->id]);

            return $user;
        });

        $mailNotice = 'A password-setup link was emailed to the patient.';
        try {
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);
        } catch (\Throwable $e) {
            $mailNotice = 'The setup email could not be sent; use "Forgot password" on the login page once mail is available.';
        }

        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => 'Patient Portal Account Linked',
            'module'        => 'Patient Records',
            'description'   => "Portal account linked to patient [{$patient->patient_no}] after identity verification attestation.",
            'loggable_type' => Patient::class,
            'loggable_id'   => $patient->id,
            'ip_address'    => $request->ip(),
            'severity'      => ActivityLog::SEVERITY_WARNING,
            'result'        => ActivityLog::RESULT_SUCCESS,
            'logged_at'     => now(),
        ]);

        return redirect()->route('admin.patients.intake')
            ->with('success', "Portal account created for patient {$patient->patient_no}. {$mailNotice}");
    }
}
