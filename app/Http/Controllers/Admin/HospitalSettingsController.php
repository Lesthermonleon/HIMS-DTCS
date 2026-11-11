<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialRule;
use App\Models\SettingHistory;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class HospitalSettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Display hospital operations, branding, and business rules settings.
     */
    public function index(): View
    {
        $current = $this->settings->getForScope(SettingsRegistry::SCOPE_ADMIN);

        $financialRules = FinancialRule::with('createdBy')
            ->orderBy('effective_from', 'desc')
            ->take(5)
            ->get();

        $activeFinancialRule = FinancialRule::where('effective_from', '<=', now()->toDateString())
            ->orderBy('effective_from', 'desc')
            ->first();

        $histories = SettingHistory::where('scope', SettingsRegistry::SCOPE_ADMIN)
            ->with('changedBy')
            ->orderBy('id', 'desc')
            ->take(15)
            ->get();

        return view('admin.settings.hospital', compact('current', 'financialRules', 'activeFinancialRule', 'histories'));
    }

    /**
     * Update hospital branding and metadata.
     */
    public function updateBranding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hospital_name'            => ['required', 'string', 'max:150'],
            'hospital_tagline'         => ['nullable', 'string', 'max:200'],
            'hospital_address'         => ['nullable', 'string', 'max:300'],
            'hospital_phone'           => ['nullable', 'string', 'max:50'],
            'hospital_email'           => ['nullable', 'email', 'max:100'],
            'hospital_registration_no' => ['nullable', 'string', 'max:100'],
            'hospital_logo'            => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'], // 1MB max
            'reason'                   => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $settingsToUpdate = [
            'hospital.name'            => $validated['hospital_name'],
            'hospital.tagline'         => $validated['hospital_tagline'] ?? null,
            'hospital.address'         => $validated['hospital_address'] ?? null,
            'hospital.phone'           => $validated['hospital_phone'] ?? null,
            'hospital.email'           => $validated['hospital_email'] ?? null,
            'hospital.registration_no' => $validated['hospital_registration_no'] ?? null,
        ];

        // Handle safe logo upload
        if ($request->hasFile('hospital_logo')) {
            $file = $request->file('hospital_logo');
            $logoDir = storage_path('app/branding');
            if (! File::exists($logoDir)) {
                File::makeDirectory($logoDir, 0755, true);
            }

            $filename = 'hospital_logo_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($logoDir, $filename);

            $settingsToUpdate['hospital.logo_path'] = 'branding/' . $filename;
        }

        try {
            $this->settings->updateMany(
                $settingsToUpdate,
                SettingsRegistry::SCOPE_ADMIN,
                Auth::id(),
                $validated['reason'],
                $request->ip()
            );

            return back()->with('success', 'Hospital branding and organization metadata updated successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    /**
     * Update operating hours.
     */
    public function updateOperatingHours(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hours'  => ['required', 'array'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $settingsToUpdate = [
            'hours.weekly' => $validated['hours'],
        ];

        try {
            $this->settings->updateMany(
                $settingsToUpdate,
                SettingsRegistry::SCOPE_ADMIN,
                Auth::id(),
                $validated['reason'],
                $request->ip()
            );

            return back()->with('success', 'Hospital operating hours updated successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    /**
     * Update queue prefixes and operational thresholds.
     */
    public function updateQueueSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'queue_prefix_diagnostic' => ['required', 'string', 'regex:/^[A-Z]{2,6}-$/', 'different:queue_prefix_treatment'],
            'queue_prefix_treatment'  => ['required', 'string', 'regex:/^[A-Z]{2,6}-$/', 'different:queue_prefix_diagnostic'],
            'queue_delay_threshold'   => ['required', 'integer', 'min:15', 'max:1440'],
            'reason'                  => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $settingsToUpdate = [
            'queue.prefix_diagnostic'            => strtoupper($validated['queue_prefix_diagnostic']),
            'queue.prefix_treatment'             => strtoupper($validated['queue_prefix_treatment']),
            'ops.queue_delay_threshold_minutes'  => (int) $validated['queue_delay_threshold'],
        ];

        try {
            $this->settings->updateMany(
                $settingsToUpdate,
                SettingsRegistry::SCOPE_ADMIN,
                Auth::id(),
                $validated['reason'],
                $request->ip()
            );

            return back()->with('success', 'Queue prefixes and operational thresholds updated successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a new immutable, effective-dated financial rule.
     */
    public function storeFinancialRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency_code'       => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'currency_symbol'     => ['required', 'string', 'max:8'],
            'tax_name'            => ['nullable', 'string', 'max:60'],
            'tax_rate'            => ['required', 'numeric', 'min:0', 'max:1'], // e.g. 0.1200 for 12%
            'service_charge_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'effective_from'      => ['required', 'date'],
            'notes'               => ['nullable', 'string', 'max:500'],
            'reason'              => ['required', 'string', 'min:5', 'max:500'],
        ]);

        FinancialRule::create([
            'currency_code'       => strtoupper($validated['currency_code']),
            'currency_symbol'     => $validated['currency_symbol'],
            'tax_name'            => $validated['tax_name'] ?? null,
            'tax_rate'            => $validated['tax_rate'],
            'service_charge_rate' => $validated['service_charge_rate'],
            'applies_to'          => 'none',
            'notes'               => $validated['notes'] ?? null,
            'effective_from'      => $validated['effective_from'],
            'created_by'          => Auth::id(),
        ]);

        return back()->with('success', "New financial rule effective from {$validated['effective_from']} created successfully.");
    }
}
