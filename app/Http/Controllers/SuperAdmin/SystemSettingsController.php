<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SettingHistory;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Display system & security configuration dashboard.
     */
    public function index(): View
    {
        $current = $this->settings->getForScope(SettingsRegistry::SCOPE_SUPER_ADMIN);

        $histories = SettingHistory::where('scope', SettingsRegistry::SCOPE_SUPER_ADMIN)
            ->with('changedBy')
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('super_admin.settings.index', compact('current', 'histories'));
    }

    /**
     * Update Super Admin system & security settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'security_password_min_length'        => ['required', 'integer', 'min:8', 'max:64'],
            'security_password_require_mixed_case'=> ['nullable', 'boolean'],
            'security_password_require_numbers'   => ['nullable', 'boolean'],
            'security_password_require_symbols'   => ['nullable', 'boolean'],
            'security_session_inactivity_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'security_otp_expires_minutes'        => ['nullable', 'integer', 'min:1', 'max:15'],
            'maintenance_enabled'                 => ['nullable', 'boolean'],
            'maintenance_message'                 => ['required', 'string', 'max:500'],
            'maintenance_expected_back'           => ['nullable', 'string', 'max:100'],
            'backup_enabled'                      => ['nullable', 'boolean'],
            'backup_time'                         => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'backup_retention_days'               => ['required', 'integer', 'min:1', 'max:365'],
            'reason'                              => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $settingsToUpdate = [
            'security.password_min_length'         => (int) $validated['security_password_min_length'],
            'security.password_require_mixed_case' => $request->boolean('security_password_require_mixed_case'),
            'security.password_require_numbers'    => $request->boolean('security_password_require_numbers'),
            'security.password_require_symbols'    => $request->boolean('security_password_require_symbols'),
            'security.session_inactivity_minutes'  => $validated['security_session_inactivity_minutes'] ? (int) $validated['security_session_inactivity_minutes'] : null,
            'security.otp_expires_minutes'         => $validated['security_otp_expires_minutes'] ? (int) $validated['security_otp_expires_minutes'] : null,
            'maintenance.enabled'                  => $request->boolean('maintenance_enabled'),
            'maintenance.message'                  => $validated['maintenance_message'],
            'maintenance.expected_back'            => $validated['maintenance_expected_back'] ?? null,
            'backup.enabled'                       => $request->boolean('backup_enabled'),
            'backup.time'                          => $validated['backup_time'],
            'backup.retention_days'                => (int) $validated['backup_retention_days'],
        ];

        try {
            $this->settings->updateMany(
                $settingsToUpdate,
                SettingsRegistry::SCOPE_SUPER_ADMIN,
                Auth::id(),
                $validated['reason'],
                $request->ip()
            );

            return back()->with('success', 'System and security settings updated successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to update settings: ' . $e->getMessage());
        }
    }
}
